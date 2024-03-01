<?php

declare(strict_types=1);

namespace App\Command\Emoji;

use App\DTO\ActivityPub\EmojiDto;
use App\Entity\Emoji;
use App\Repository\EmojiRepository;
use App\Service\EmojiManager;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\Schema;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'mbin:emoji:add:pack',
    description: 'Import emoji from a misskey/pleroma zip pack',
)]
class AddPackCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EmojiRepository $emojiRepository,
        private readonly EmojiManager $emojiManager,
        private readonly MessageBusInterface $bus,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'path to the zip pack file'
            )
            ->addOption(
                'remote', null,
                InputOption::VALUE_NONE,
                'the supplied path will be treated as remote file to download'
            )
            ->addOption(
                'category', 'c',
                InputOption::VALUE_REQUIRED,
                'assign specified category to stolen emoji'
            )
            ->addOption(
                '--overwrite', null,
                InputOption::VALUE_NONE,
                'existing emoji with same shortcode will be updated with new data'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = $io = new SymfonyStyle($input, $output);

        $path = $input->getArgument('path');
        $overwrite = $input->getOption('overwrite');
        $category = $input->getOption('category');

        /** @var ?string $isRemote */
        if ($isRemote = filter_var($path, FILTER_VALIDATE_URL, FILTER_NULL_ON_FAILURE)) {
            $this->io->info("Given path {$path} appears to be url and will be downloaded");
        }

        $zipFile = $isRemote ? $this->downloadZip($path) : $path;
        if (!$zipFile || !file_exists($zipFile)) {
            $io->error('Failed to open or retrieve the provided zip');

            return Command::FAILURE;
        }

        $zip = $this->openZip($zipFile);
        if (!$zip) {
            return Command::FAILURE;
        }

        $metaIndex = $zip->locateName('meta.json');
        if (false === $metaIndex) {
            $io->error("Unable to get the pack manifest 'meta.json'");
            $zip->close();

            return Command::INVALID;
        }

        $meta = $this->getManifest($zip->getFromIndex($metaIndex));

        $emojis = $meta['emojis'];
        foreach ($io->progressIterate($emojis) as $emoji) {
            $entity = $this->addEmoji($zip, $emoji, $category, $overwrite);

            if (!$entity) {
                $io->warning("emoji {$emoji['emoji']['name']} not addded");
            }
        }

        $this->em->flush();

        $zip->close();
        if ($isRemote) {
            unlink($zipFile);
        }

        return Command::SUCCESS;
    }

    private function getMetaSchema(): Schema
    {
        return Expect::structure([
            'emojis' => Expect::listOf(
                Expect::structure([
                    'downloaded' => Expect::bool(),
                    'fileName' => Expect::string(),
                    'emoji' => Expect::structure([
                        'name' => Expect::string(),
                        'category' => Expect::string(),
                        'aliases' => Expect::listOf(Expect::string()),
                    ])->otherItems()->castTo('array'),
                ])->otherItems()->castTo('array')
            ),
        ])->otherItems()->castTo('array');
    }

    private function downloadZip(string $url): ?string
    {
        $indicator = new ProgressIndicator($this->io);

        $outFile = @tempnam('/', 'mbin-');
        if (false === $outFile) {
            $this->logger->error("Couldn't create temporary file");

            return null;
        }

        try {
            $fh = fopen($outFile, 'wb');
            $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);

            $indicator->start("downloading zip from {$url} ...");
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($fh, $chunk->getContent());
                $indicator->advance();
            }

            fclose($fh);

            $indicator->finish('download completed');

            return $outFile;
        } catch (\Exception $e) {
            $indicator->finish('ERROR!');
            $this->io->error("error occurred while downloading zip: {$e->getMessage()}");

            if ($fh && \is_resource($fh)) {
                fclose($fh);
            }
            unlink($outFile);

            return null;
        }
    }

    private function openZip(string $path): ?\ZipArchive
    {
        $zip = new \ZipArchive();

        if (true !== $zip->open($path, \ZipArchive::RDONLY | \ZipArchive::CHECKCONS)) {
            $this->io->error('Unable to open the zip: '.$zip->getStatusString());

            return null;
        }

        return $zip;
    }

    /** @return ?string temp file path to extracted icon file or null if failed */
    private function extractIcon(\ZipArchive $zip, array $emoji): ?string
    {
        $info = $emoji['emoji'];

        $filename = $emoji['fileName'];
        $shortcode = $info['name'];

        $tmpFile = @tempnam('/', 'mbin-emoji-');
        if (false === $tmpFile) {
            $this->io->warning("Failed to create temporary file while importing emoji {$shortcode}");

            return null;
        }

        // copy file from inside zip to tmp file
        $in = $zip->getStream($filename);
        $out = fopen($tmpFile, 'wb');

        if (false === $in || false === $out) {
            $this->io->warning("Failed to open required files while importing emoji {$shortcode}");

            return null;
        }

        if (false === stream_copy_to_stream($in, $out)) {
            $this->io->warning("Failed to extract icon file while importing emoji {$shortcode}");

            return null;
        }

        fclose($in);
        fclose($out);

        return $tmpFile;
    }

    /** load and parse manifest into usable array */
    private function getManifest(string $content): array
    {
        $manifest = json_decode($content, true);

        return (new Processor())->process($this->getMetaSchema(), $manifest);
    }

    private function addEmoji(\ZipArchive $zip, array $emoji, string $category = null, ?bool $overwrite = false): ?Emoji
    {
        if (!$emoji['downloaded']) {
            return null;
        }

        $info = $emoji['emoji'];

        $shortcode = $info['name'];
        $category = $category ?: ($info['category'] ?? null);

        $tmpFile = $this->extractIcon($zip, $emoji);
        if (!$tmpFile) {
            return null;
        }

        $dto = new EmojiDto($shortcode, $category, sourceFile: $tmpFile);
        $emoji = $this->emojiRepository->findOneByShortcode($shortcode);

        if (!$emoji) {
            $emoji = $this->emojiManager->createEmojiFromDto($dto);
            $this->emojiRepository->save($emoji);

            $this->io->success("emoji {$shortcode} added");
        } elseif ($emoji && $overwrite) {
            $this->io->note("found existing emoji at {$shortcode}, it will be overwritten");
            $this->emojiManager->updateEmojiFromDto($dto);

            $this->io->success("emoji {$shortcode} updated");
        } else {
            $this->io->comment("found existing emoji at {$shortcode}, skipping");
        }

        unlink($tmpFile);

        return $emoji;
    }
}
