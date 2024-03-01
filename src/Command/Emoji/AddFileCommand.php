<?php

declare(strict_types=1);

namespace App\Command\Emoji;

use App\Entity\Emoji;
use App\Repository\EmojiRepository;
use App\Service\EmojiManager;
use Doctrine\ORM\EntityManagerInterface;
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
    name: 'mbin:emoji:add:file',
    description: 'Create an emoji from an icon file or url',
)]
class AddFileCommand extends Command
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
                'path to the emoji icon file'
            )
            ->addArgument(
                'shortcode',
                InputArgument::REQUIRED,
                'the shortcode for the new emoji'
            )
            ->addArgument(
                'category',
                InputArgument::OPTIONAL,
                'assign specified category to the new emoji'
            )
            ->addOption(
                '--overwrite', null,
                InputOption::VALUE_NONE,
                'existing emoji with same shortcode will be overwritten'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $path = $input->getArgument('path');
        $shortcode = $input->getArgument('shortcode');
        $category = $input->getArgument('category');
        $overwrite = $input->getOption('overwrite');

        if ($isRemote = filter_var($path, FILTER_VALIDATE_URL)) {
            $this->io->info("Given path {$path} appears to be url and will be downloaded");
        }

        $iconFile = $isRemote ? $this->downloadFile($path) : $path;

        $icon = $this->emojiManager->createIconFromFile($iconFile);

        $emoji = $this->emojiRepository->findOneByShortcode($shortcode);
        if (!$emoji) {
            $emoji = new Emoji($icon, $shortcode, $category);
        } elseif ($emoji && $overwrite) {
            $this->io->note("existing emoji with shortcode {$shortcode} found and will be overwritten");

            $emoji->icon = $icon;
            $emoji->category = $category;
        } else {
            $this->io->info("existing emoji with shortcode {$shortcode} found but not overwritten");
        }

        $this->emojiRepository->save($emoji);

        $this->io->success("emoji {$shortcode} added");

        if ($isRemote) {
            unlink($iconFile);
        }

        return Command::SUCCESS;
    }

    private function downloadFile(string $url): ?string
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

            $indicator->start("downloading icon from {$url} ...");
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($fh, $chunk->getContent());
                $indicator->advance();
            }

            fclose($fh);

            $indicator->finish('download completed');

            return $outFile;
        } catch (\Exception $e) {
            $indicator->finish('ERROR!');
            $this->io->error("error occurred while downloading icon file: {$e->getMessage()}");

            if ($fh && \is_resource($fh)) {
                fclose($fh);
            }
            unlink($outFile);

            return null;
        }
    }
}
