<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Emoji;
use App\Entity\EmojiIcon;
use App\Exception\CorruptedFileException;
use App\Markdown\CommonMark\Node\Emoji as EmojiNode;
use App\Markdown\MarkdownConverter;
use App\Repository\EmojiIconRepository;
use App\Repository\EmojiRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EmojiManager
{
    public const MAX_TIMEOUT_SECONDS = 8;
    public const MAX_DOWNLOAD_SECONDS = 10;

    public function __construct(
        private readonly string $storageUrl,
        private readonly FilesystemOperator $emojiFilesystem,
        private readonly HttpClientInterface $httpClient,
        private readonly MimeTypesInterface $mimeTypeGuesser,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmojiIconRepository $iconRepository,
        private readonly EmojiRepository $emojiRepository,
        private readonly MarkdownConverter $markdownConverter,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string $source path to emoji icon source file
     */
    public function createIconFromFile(string $source): ?EmojiIcon
    {
        $fileName = $this->getFileName($source);
        $sha256 = hash_file('sha256', $source, true);

        if ($icon = $this->iconRepository->findOneBySha256($sha256)) {
            return $icon;
        }

        $icon = new EmojiIcon($fileName, $sha256);

        try {
            $this->store($source, $icon->filePath);
        } catch (\Exception $e) {
            $this->logger->error('findOrCreateFromFile: failed to store emoji file: {err}', ['err' => $e]);

            return null;
        }

        return $icon;
    }

    /**
     * @param array $object `toot:Emoji` object to create Emoji from
     */
    public function createEmojiFromObject(array $object, string $category = null): ?Emoji
    {
        $shortcode = trim($object['name'], ':');
        $apId = $object['id'];
        $apDomain = parse_url($apId, PHP_URL_HOST);
        $iconUrl = $object['icon']['url'];

        if (
            ($existed = $this->emojiRepository->findOneByApId($apId))
            || ($existed = $this->emojiRepository->findOneByShortcode($shortcode, $apDomain))
        ) {
            return $existed;
        }

        if ($iconFile = $this->download($iconUrl)) {
            $icon = $this->createIconFromFile($iconFile);

            $emoji = new Emoji($icon, $shortcode, null, $apId, $iconUrl);
            $emoji->category = $category;

            $this->logger->debug('creating new emoji: {emoji}', [
                'emoji' => [
                    'shortcode' => $emoji->shortcode,
                    'domain' => $emoji->apDomain,
                    'apId' => $emoji->apId,
                    'icon' => $emoji->icon->fileName,
                ],
            ]);

            return $emoji;
        }

        return null;
    }

    public function store(string $sourceFile, string $filePath): bool
    {
        $fh = fopen($sourceFile, 'rb');

        try {
            $this->validate($sourceFile);

            $this->emojiFilesystem->writeStream($filePath, $fh);

            if (!$this->emojiFilesystem->has($filePath)) {
                throw new \Exception('File not found');
            }

            return true;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        } finally {
            \is_resource($fh) and fclose($fh);
        }
    }

    private function validate(string $filePath): bool
    {
        $violations = $this->validator->validate($filePath, [
            new Image([
                'detectCorrupted' => 'image/webp' !== $this->mimeTypeGuesser->guessMimeType($filePath),
            ]),
        ]);

        if (\count($violations) > 0) {
            throw new CorruptedFileException((string) $violations);
        }

        return true;
    }

    public function download(string $url): ?string
    {
        $tempFile = @tempnam('/', 'mbin-');

        if (false === $tempFile) {
            throw new UnrecoverableMessageHandlingException("Couldn't create temporary file");
        }

        $fh = fopen($tempFile, 'wb');

        try {
            $response = $this->httpClient->request(
                'GET',
                $url,
                [
                    'timeout' => self::MAX_TIMEOUT_SECONDS,
                    'max_duration' => self::MAX_DOWNLOAD_SECONDS,
                    'headers' => [
                        'Accept' => implode(', ', ImageManager::IMAGE_MIMETYPES),
                    ],
                ]
            );

            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($fh, $chunk->getContent());
            }

            fclose($fh);

            $this->validate($tempFile);
        } catch (\Exception $e) {
            $this->logger->info('error occurred while downloading emoji image: {err}', ['err' => $e]);

            if ($fh && \is_resource($fh)) {
                fclose($fh);
            }
            unlink($tempFile);

            return null;
        }

        return $tempFile;
    }

    public function getFileName(string $sourceFile): string
    {
        $hash = hash_file('sha256', $sourceFile);

        $mimeType = $this->mimeTypeGuesser->guessMimeType($sourceFile);
        if (!$mimeType) {
            throw new \RuntimeException('unable to guess MIME type of emoji');
        }

        $ext = $this->mimeTypeGuesser->getExtensions($mimeType)[0] ?? null;
        if (!$ext) {
            throw new \RuntimeException('unable to guess extension of emoji');
        }

        return sprintf('%s.%s', $hash, $ext);
    }

    public function remove(string $path): void
    {
        $this->emojiFilesystem->delete($path);
    }

    public function getPath(Emoji $emoji): string
    {
        return $this->emojiFilesystem->read($emoji->getIconPath());
    }

    public function getUrl(?Emoji $emoji): ?string
    {
        if (!$emoji) {
            return null;
        }

        return "{$this->storageUrl}/emoji/{$emoji->getIconPath()}";
    }

    public function getMimetype(Emoji $emoji): string
    {
        try {
            return $this->emojiFilesystem->mimeType($emoji->getIconPath());
        } catch (\Exception $e) {
            return 'none';
        }
    }

    public function extractFromBody(string $markdown): ?array
    {
        $emojis = [];
        $document = $this->markdownConverter->parse($markdown);

        foreach ($document->iterator() as $node) {
            if ($node instanceof EmojiNode) {
                $emojis[] = $node->data->get('shortcode', trim($node->getLiteral(), ':'));
            }
        }

        return array_unique($emojis) ?: null;
    }
}
