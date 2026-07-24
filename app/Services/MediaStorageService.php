<?php

namespace App\Services;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;
use Throwable;

class MediaStorageService
{
    private const SIGNED_URL_TTL_SECONDS = 3600;

    private string $region;
    private string $bucket;
    private string $accessKeyId;
    private string $secretAccessKey;
    private ?S3Client $s3Client = null;

    /** @var array<string, true> */
    private array $regionCheckedBuckets = [];

    /** @var array<string, string> */
    private array $urlCache = [];

    public function __construct()
    {
        $this->region = trim((string) env('AWS_REGION', ''));
        $this->bucket = trim((string) env('AWS_S3_BUCKET', ''));
        $this->accessKeyId = trim((string) env('AWS_ACCESS_KEY_ID', ''));
        $this->secretAccessKey = trim((string) env('AWS_SECRET_ACCESS_KEY', ''));
    }

    public function store(UploadedFile $file, string $directory = 'media'): string
    {
        $directory = $this->normalizeDirectory($directory);
        $filename = bin2hex(random_bytes(16)) . '.' . $this->extensionFor($file);

        if (! $this->canUseS3()) {
            return $this->storeLocally($file, $directory, $filename);
        }

        $key = implode('/', [$directory, date('Y/m'), $filename]);
        $tempName = $file->getTempName();

        if ($tempName === '' || ! is_file($tempName) || ! is_readable($tempName)) {
            throw new RuntimeException('Unable to read the uploaded media file.');
        }

        try {
            $this->ensureBucketRegion($this->bucket);
            $this->client()->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'SourceFile' => $tempName,
                'ContentType' => $file->getMimeType(),
                'ContentDisposition' => 'inline',
                'CacheControl' => 'public, max-age=31536000, immutable',
                'ServerSideEncryption' => 'AES256',
            ]);
        } catch (AwsException $exception) {
            $message = $exception->getAwsErrorMessage() ?: $exception->getMessage();
            throw new RuntimeException('AWS S3 upload failed: ' . $message, 0, $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('AWS S3 upload failed: ' . $exception->getMessage(), 0, $exception);
        }

        return sprintf('s3://%s/%s', $this->bucket, $key);
    }

    public function url(?string $path): string
    {
        if (! $path) {
            return '';
        }

        if (isset($this->urlCache[$path])) {
            return $this->urlCache[$path];
        }

        if (preg_match('#^https?://#i', $path)) {
            return $this->urlCache[$path] = $path;
        }

        if (! str_starts_with($path, 's3://')) {
            return $this->urlCache[$path] = base_url(ltrim($path, '/'));
        }

        [$bucket, $key] = $this->parseS3Path($path);
        if ($bucket === '' || $key === '' || ! $this->canUseS3()) {
            return $this->urlCache[$path] = '';
        }

        try {
            $this->ensureBucketRegion($bucket);
            $command = $this->client()->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key' => $key,
            ]);
            $request = $this->client()->createPresignedRequest(
                $command,
                '+' . self::SIGNED_URL_TTL_SECONDS . ' seconds',
            );

            return $this->urlCache[$path] = (string) $request->getUri();
        } catch (Throwable $exception) {
            log_message('error', 'Unable to create an S3 media URL for {path}: {message}', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return $this->urlCache[$path] = '';
        }
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (str_starts_with($path, 's3://')) {
            if (! $this->canUseS3()) {
                return;
            }

            [$bucket, $key] = $this->parseS3Path($path);
            if ($bucket === '' || $key === '') {
                return;
            }

            try {
                $this->ensureBucketRegion($bucket);
                $this->client()->deleteObject([
                    'Bucket' => $bucket,
                    'Key' => $key,
                ]);
            } catch (Throwable $exception) {
                log_message('warning', 'Unable to delete S3 media {path}: {message}', [
                    'path' => $path,
                    'message' => $exception->getMessage(),
                ]);
            }

            return;
        }

        if (preg_match('#^https?://#i', $path)) {
            return;
        }

        $absolutePath = rtrim(FCPATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function storeLocally(UploadedFile $file, string $directory, string $filename): string
    {
        $relativeDirectory = implode('/', ['uploads', $directory, date('Y/m')]);
        $targetDirectory = rtrim(FCPATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);

        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0775, true) && ! is_dir($targetDirectory)) {
            throw new RuntimeException('Unable to create the local media directory.');
        }

        if (! $file->move($targetDirectory, $filename)) {
            throw new RuntimeException('Unable to save the uploaded media file.');
        }

        return $relativeDirectory . '/' . $filename;
    }

    private function client(): S3Client
    {
        if ($this->s3Client instanceof S3Client) {
            return $this->s3Client;
        }

        $this->assertS3Configured();

        if (! class_exists(S3Client::class)) {
            throw new RuntimeException('AWS S3 support requires the aws/aws-sdk-php Composer package. Run "composer install".');
        }

        return $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
                'key' => $this->accessKeyId,
                'secret' => $this->secretAccessKey,
            ],
        ]);
    }

    private function ensureBucketRegion(string $bucket): void
    {
        if (isset($this->regionCheckedBuckets[$bucket])) {
            return;
        }

        try {
            $this->client()->headBucket(['Bucket' => $bucket]);
        } catch (AwsException $exception) {
            $detectedRegion = trim((string) $exception->getResponse()?->getHeaderLine('x-amz-bucket-region'));
            if ($detectedRegion !== '' && $detectedRegion !== $this->region) {
                $this->region = $detectedRegion;
                $this->s3Client = null;
            }
        }

        $this->regionCheckedBuckets[$bucket] = true;
    }

    private function isS3Configured(): bool
    {
        return $this->region !== ''
            && $this->bucket !== ''
            && $this->accessKeyId !== ''
            && $this->secretAccessKey !== '';
    }

    private function canUseS3(): bool
    {
        return $this->isS3Configured() && class_exists(S3Client::class);
    }

    private function assertS3Configured(): void
    {
        if (! $this->isS3Configured()) {
            throw new RuntimeException('AWS S3 storage is not fully configured.');
        }
    }

    /** @return array{0: string, 1: string} */
    private function parseS3Path(string $path): array
    {
        $parts = parse_url($path);

        return [
            trim((string) ($parts['host'] ?? '')),
            ltrim((string) ($parts['path'] ?? ''), '/'),
        ];
    }

    private function normalizeDirectory(string $directory): string
    {
        $segments = array_filter(
            explode('/', trim($directory, '/')),
            static fn (string $segment): bool => $segment !== '',
        );
        $segments = array_map(static function (string $segment): string {
            $normalized = preg_replace('/[^A-Za-z0-9_-]+/', '-', $segment) ?? '';

            return trim($normalized, '-');
        }, $segments);
        $segments = array_filter($segments, static fn (string $segment): bool => $segment !== '');

        return $segments === [] ? 'media' : implode('/', $segments);
    }

    private function extensionFor(UploadedFile $file): string
    {
        return match (strtolower($file->getMimeType())) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Unsupported media type.'),
        };
    }
}
