<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ActivityPub\EmojiDto;
use App\Entity\Emoji;
use App\Entity\EmojiIcon;
use App\Exception\CorruptedFileException;
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
    public const PATH_PREFIX = 'emoji';

    public function __construct(
        private readonly string $storageUrl,
        private readonly FilesystemOperator $publicUploadsFilesystem,
        private readonly HttpClientInterface $httpClient,
        private readonly MimeTypesInterface $mimeTypeGuesser,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmojiIconRepository $iconRepository,
        private readonly EmojiRepository $emojiRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    private function prefixPath(string $path): string
    {
        return self::PATH_PREFIX.'/'.$path;
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
     * create new emoji entity from DTO.
     *
     * this *does not* persist the created entity automatically
     */
    public function createEmojiFromDto(EmojiDto $dto): ?Emoji
    {
        $errors = $this->validator->validate($dto);
        if (\count($errors) > 0) {
            $this->logger->info('not creating new emoji: dto validation failed', [
                'dto' => $dto,
                'errors' => (string) $errors,
            ]);

            return null;
        }

        $shortcode = $dto->shortcode;
        $apId = $dto->apId;
        $apDomain = $dto->apDomain;

        if ($existed = $this->emojiRepository->findOneByShortcode($shortcode, $apDomain)) {
            // @todo handle updated emoji with updated time set
            return $existed;
        }

        $icon = $this->createIconFromFile($dto->sourceFile);

        $emoji = new Emoji($icon, $shortcode, null, $apId, $dto->sourceUrl);
        $emoji->category = $dto->category;

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

    public function updateEmojiFromDto(EmojiDto $dto, bool $flush = true)
    {
        $emoji = $this->emojiRepository->findOneByShortcode($dto->shortcode, $dto->apDomain);
        if (!$emoji) {
            return null;
        }

        $emoji->category = $dto->category;
        $emoji->apId = $dto->apId;
        $emoji->apDomain = $dto->apDomain;

        if ($dto->sourceFile) {
            $icon = $this->createIconFromFile($dto->sourceFile);
            if ($icon !== $emoji->icon) {
                $emoji->icon = $icon;
                $emoji->iconUrl = $dto->sourceUrl;
            }
        }

        if ($flush) {
            $this->entityManager->flush();
        }

        return $emoji;
    }

    /**
     * @param array $object `toot:Emoji` object to create Emoji from
     */
    public function createEmojiFromObject(array $object, string $category = null): ?Emoji
    {
        $sourceUrl = $object['icon']['url'] ?? null;
        if (!$sourceUrl) {
            return null;
        }

        if ($sourceFile = $this->download($sourceUrl)) {
            $dto = new EmojiDto(
                shortcode: trim($object['name'], ':'),
                category: $category,
                apId: $object['id'],
                sourceFile: $sourceFile,
                sourceUrl: $sourceUrl,
            );

            return $this->createEmojiFromDto($dto);
        }

        return null;
    }

    /**
     * store emoji file from source file to flysystem.
     *
     * the stored file path will be automatically prefixed with `EmojiManager::PATH_PREFIX`.
     */
    public function store(string $sourceFile, string $filePath)
    {
        $fh = fopen($sourceFile, 'rb');
        $storedPath = $this->prefixPath($filePath);

        try {
            $this->validate($sourceFile);

            $this->publicUploadsFilesystem->writeStream($storedPath, $fh);

            if (!$this->publicUploadsFilesystem->has($storedPath)) {
                throw new \Exception('File not found, failed to save the emoji icon file');
            }
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
        $this->publicUploadsFilesystem->delete($this->prefixPath($path));
    }

    public function getPath(Emoji $emoji): string
    {
        return $this->publicUploadsFilesystem->read($this->prefixPath($emoji->getIconPath()));
    }

    public function getUrl(?Emoji $emoji): ?string
    {
        if (!$emoji) {
            return null;
        }

        return "{$this->storageUrl}/{$this->prefixPath($emoji->getIconPath())}";
    }

    public function getMimetype(Emoji $emoji): string
    {
        try {
            return $this->publicUploadsFilesystem->mimeType($this->prefixPath($emoji->getIconPath()));
        } catch (\Exception $e) {
            return 'none';
        }
    }
}
