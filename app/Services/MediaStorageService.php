<?php

namespace App\Services;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;
use Throwable;

class MediaStorageService
{
    private string $driver;
    private string $region;
    private string $bucket;
    private string $accessKeyId;
    private string $secretAccessKey;
    private string $sessionToken;
    private string $endpoint;
    private bool $usePathStyleEndpoint;
    private string $keyPrefix;
    private string $publicUrl;
    private bool $publicObjects;
    private int $signedUrlTtl;
    private string $objectAcl;
    private string $serverSideEncryption;
    private ?S3Client $s3Client = null;

    /** @var array<string, string> */
    private array $urlCache = [];

    public function __construct()
    {
        $this->bucket = trim((string) env('AWS_S3_BUCKET', ''));
        $defaultDriver = $this->bucket !== '' ? 's3' : 'local';
        $this->driver = strtolower(trim((string) env('MEDIA_STORAGE_DRIVER', $defaultDriver))) ?: $defaultDriver;
        $this->region = trim((string) env('AWS_REGION', 'ap-southeast-2')) ?: 'ap-southeast-2';
        $this->accessKeyId = trim((string) env('AWS_ACCESS_KEY_ID', ''));
        $this->secretAccessKey = trim((string) env('AWS_SECRET_ACCESS_KEY', ''));
        $this->sessionToken = trim((string) env('AWS_SESSION_TOKEN', ''));
        $this->endpoint = rtrim(trim((string) env('AWS_S3_ENDPOINT', '')), '/');
        $this->usePathStyleEndpoint = $this->envBool('AWS_S3_PATH_STYLE', false);
        $this->keyPrefix = trim((string) env('AWS_S3_PREFIX', ''), '/');
        $this->publicUrl = rtrim(trim((string) env('AWS_S3_PUBLIC_URL', '')), '/');
        $this->publicObjects = $this->envBool('AWS_S3_PUBLIC', false);
        $this->signedUrlTtl = max(60, min(604800, (int) env('AWS_S3_SIGNED_URL_TTL', 3600)));
        $this->objectAcl = trim((string) env('AWS_S3_ACL', ''));
        $this->serverSideEncryption = trim((string) env('AWS_S3_SERVER_SIDE_ENCRYPTION', ''));
    }

    public function store(UploadedFile $file, string $directory = 'media'): string
    {
        $directory = $this->normalizeDirectory($directory);
        $filename = bin2hex(random_bytes(16)) . '.' . $this->extensionFor($file);

        return match ($this->driver) {
            's3' => $this->storeOnS3($file, $directory, $filename),
            'local' => $this->storeLocally($file, $directory, $filename),
            default => throw new RuntimeException(sprintf('Unsupported media storage driver "%s".', $this->driver)),
        };
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
        if ($bucket === '' || $key === '') {
            return $this->urlCache[$path] = '';
        }

        if ($this->publicUrl !== '') {
            return $this->urlCache[$path] = $this->publicUrl . '/' . $this->encodeKey($key);
        }

        if ($this->publicObjects) {
            return $this->urlCache[$path] = $this->publicObjectUrl($bucket, $key);
        }

        try {
            $command = $this->client()->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key' => $key,
            ]);
            $request = $this->client()->createPresignedRequest($command, '+' . $this->signedUrlTtl . ' seconds');

            return $this->urlCache[$path] = (string) $request->getUri();
        } catch (Throwable $exception) {
            log_message('error', 'Unable to create an S3 media URL for {path}: {message}', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return $this->urlCache[$path] = $this->publicObjectUrl($bucket, $key);
        }
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (str_starts_with($path, 's3://')) {
            [$bucket, $key] = $this->parseS3Path($path);
            if ($bucket === '' || $key === '') {
                return;
            }

            try {
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

    private function storeOnS3(UploadedFile $file, string $directory, string $filename): string
    {
        $this->assertS3Configured();

        $keyParts = array_filter([$this->keyPrefix, $directory, date('Y/m'), $filename], static fn (string $part): bool => $part !== '');
        $key = implode('/', $keyParts);
        $tempName = $file->getTempName();

        if ($tempName === '' || ! is_file($tempName) || ! is_readable($tempName)) {
            throw new RuntimeException('Unable to read the uploaded media file.');
        }

        $parameters = [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SourceFile' => $tempName,
            'ContentType' => $file->getMimeType(),
            'ContentDisposition' => 'inline',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ];

        if ($this->objectAcl !== '') {
            $parameters['ACL'] = $this->objectAcl;
        }

        if ($this->serverSideEncryption !== '') {
            $parameters['ServerSideEncryption'] = $this->serverSideEncryption;
        }

        try {
            $this->client()->putObject($parameters);
        } catch (AwsException $exception) {
            $message = $exception->getAwsErrorMessage() ?: $exception->getMessage();
            throw new RuntimeException('AWS S3 upload failed: ' . $message, 0, $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('AWS S3 upload failed: ' . $exception->getMessage(), 0, $exception);
        }

        return sprintf('s3://%s/%s', $this->bucket, $key);
    }

    private function storeLocally(UploadedFile $file, string $directory, string $filename): string
    {
        $relativeDirectory = 'uploads/' . $directory;
        $absoluteDirectory = rtrim(FCPATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);

        if (! is_dir($absoluteDirectory) && ! mkdir($absoluteDirectory, 0775, true) && ! is_dir($absoluteDirectory)) {
            throw new RuntimeException('Unable to create the local media directory.');
        }

        $file->move($absoluteDirectory, $filename, true);

        return $relativeDirectory . '/' . $filename;
    }

    private function client(): S3Client
    {
        if ($this->s3Client instanceof S3Client) {
            return $this->s3Client;
        }

        if (! class_exists(S3Client::class)) {
            throw new RuntimeException('AWS S3 support requires the aws/aws-sdk-php Composer package. Run "composer install".');
        }

        $options = [
            'version' => 'latest',
            'region' => $this->region,
        ];

        if ($this->endpoint !== '') {
            $options['endpoint'] = $this->endpoint;
            $options['use_path_style_endpoint'] = $this->usePathStyleEndpoint;
        }

        if ($this->accessKeyId !== '' || $this->secretAccessKey !== '') {
            if ($this->accessKeyId === '' || $this->secretAccessKey === '') {
                throw new RuntimeException('Both AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY must be configured together.');
            }

            $credentials = [
                'key' => $this->accessKeyId,
                'secret' => $this->secretAccessKey,
            ];

            if ($this->sessionToken !== '') {
                $credentials['token'] = $this->sessionToken;
            }

            $options['credentials'] = $credentials;
        }

        return $this->s3Client = new S3Client($options);
    }

    private function assertS3Configured(): void
    {
        if ($this->bucket === '') {
            throw new RuntimeException('AWS_S3_BUCKET is required when MEDIA_STORAGE_DRIVER is set to s3.');
        }

        if ($this->region === '') {
            throw new RuntimeException('AWS_REGION is required when MEDIA_STORAGE_DRIVER is set to s3.');
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

    private function publicObjectUrl(string $bucket, string $key): string
    {
        $encodedKey = $this->encodeKey($key);

        if ($this->endpoint !== '') {
            if ($this->usePathStyleEndpoint) {
                return $this->endpoint . '/' . rawurlencode($bucket) . '/' . $encodedKey;
            }

            $parts = parse_url($this->endpoint);
            if (isset($parts['scheme'], $parts['host'])) {
                $port = isset($parts['port']) ? ':' . $parts['port'] : '';
                $basePath = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

                return sprintf(
                    '%s://%s.%s%s%s/%s',
                    $parts['scheme'],
                    $bucket,
                    $parts['host'],
                    $port,
                    $basePath,
                    $encodedKey,
                );
            }
        }

        return sprintf('https://%s.s3.%s.amazonaws.com/%s', $bucket, $this->region, $encodedKey);
    }

    private function encodeKey(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    private function normalizeDirectory(string $directory): string
    {
        $segments = array_filter(explode('/', trim($directory, '/')), static fn (string $segment): bool => $segment !== '');
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

    private function envBool(string $key, bool $default): bool
    {
        $value = env($key, $default);
        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }
}
