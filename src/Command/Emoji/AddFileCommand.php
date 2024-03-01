<?php

declare(strict_types=1);

namespace App\Command\Emoji;

use App\DTO\ActivityPub\EmojiDto;
use App\Repository\EmojiRepository;
use App\Service\EmojiManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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

        /** @var ?string $url */
        if ($url = filter_var($path, FILTER_VALIDATE_URL, FILTER_NULL_ON_FAILURE)) {
            $this->io->info("Given path {$url} appears to be url and will be downloaded");
        }

        $iconFile = $url ? $this->emojiManager->download($url) : $path;
        if ($url && !$iconFile) {
            $this->io->error("Failed to download remote image from '{$path}'");

            return Command::FAILURE;
        }

        $dto = new EmojiDto($shortcode, $category, sourceFile: $iconFile);
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

        if ($url) {
            unlink($iconFile);
        }

        return Command::SUCCESS;
    }
}
