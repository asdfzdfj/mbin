<?php

declare(strict_types=1);

namespace App\Command\Emoji;

use App\Repository\EmojiRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'mbin:emoji:steal',
    description: 'Steal (import) remote emoji(s) into local custom emoji',
)]
class StealEmojiCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EmojiRepository $emojiRepository,
        private readonly MessageBusInterface $bus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('domain', InputArgument::REQUIRED, 'domain to search for emoji')
            ->addArgument('shortcode', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'shortcode(s) of emoji to steal')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, 'assign specified category to stolen emoji')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'overwrite local data from stolen emoji if already exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $domain = $input->getArgument('domain');
        $shortcodes = array_unique($input->getArgument('shortcode'));
        $category = $input->getOption('category');
        $overwrite = $input->getOption('overwrite');

        if ('local' === $domain) {
            $io->warning('Cannot steal emoji from itself');

            return Command::INVALID;
        }

        $remoteEmojis = $this->emojiRepository->findAllByDomain($domain);
        $localEmojis = $this->emojiRepository->findAllLocal();

        $stolen = 0;
        foreach ($io->progressIterate($shortcodes) as $shortcode) {
            $emoji = $remoteEmojis[$shortcode] ?? null;
            if (!$emoji) {
                $io->warning("Unable to find the specified emoji {$shortcode} at domain {$domain}, skipping");
                continue;
            }

            if (!empty($localEmojis[$shortcode]) && !$overwrite) {
                $io->info("Local emoji with shortcode '{$shortcode}' already exist, skipping");
                continue;
            } elseif (!empty($localEmojis[$shortcode]) && $overwrite) {
                $stolenEmoji = $localEmojis[$shortcode];
            } else {
                $stolenEmoji = clone $emoji;
            }

            $stolenEmoji->category = $category;
            $stolenEmoji->iconUrl = null;
            $stolenEmoji->apId = null;
            $stolenEmoji->apDomain = 'local';

            $this->em->persist($stolenEmoji);
            ++$stolen;
        }

        if ($stolen > 0) {
            $this->em->flush();
            $io->success("Successfully stolen {$stolen} emoji(s)");
        }

        return Command::SUCCESS;
    }
}
