<?php

namespace App\Services;

use Aws\Exception\AwsException;
use Aws\S3\S3MultiRegionClient;
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
    private ?S3MultiRegionClient $s3Client = null;

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
        $key = implode('/', [$directory, date('Y/m'), $filename]);
        $tempName = $file->getTempName();

        if ($tempName === '' || ! is_file($tempName) || ! is_readable($tempName)) {
            throw new RuntimeException('Unable to read the uploaded media file.');
        }

        try {
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
        if ($bucket === '' || $key === '' || $bucket !== $this->bucket || ! $this->canUseS3()) {
            return $this->urlCache[$path] = '';
        }

        try {
            $command = $this->client()->getCommand('GetObject', [
                'Bucket' => $this->bucket,
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
        if (! $path || ! str_starts_with($path, 's3://') || ! $this->canUseS3()) {
            return;
        }

        [$bucket, $key] = $this->parseS3Path($path);
        if ($bucket === '' || $key === '' || $bucket !== $this->bucket) {
            return;
        }

        try {
            $this->client()->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
        } catch (Throwable $exception) {
            log_message('warning', 'Unable to delete S3 media {path}: {message}', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function client(): S3MultiRegionClient
    {
        if ($this->s3Client instanceof S3MultiRegionClient) {
            return $this->s3Client;
        }

        $this->assertS3Configured();

        if (! class_exists(S3MultiRegionClient::class)) {
            throw new RuntimeException('AWS S3 support requires the aws/aws-sdk-php Composer package. Run "composer install".');
        }

        return $this->s3Client = new S3MultiRegionClient([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
                'key' => $this->accessKeyId,
                'secret' => $this->secretAccessKey,
            ],
        ]);
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
        return $this->isS3Configured() && class_exists(S3MultiRegionClient::class);
    }

    private function assertS3Configured(): void
    {
        if (! $this->isS3Configured()) {
            throw new RuntimeException(
                'AWS S3 storage requires AWS_REGION, AWS_S3_BUCKET, AWS_ACCESS_KEY_ID, and AWS_SECRET_ACCESS_KEY.',
            );
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
