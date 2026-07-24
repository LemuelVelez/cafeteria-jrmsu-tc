<?php

namespace App\Services;

use Aws\S3\S3Client;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;
use Throwable;

class MediaStorageService
{
    private string $region;
    private string $bucket;
    private string $accessKeyId;
    private string $secretAccessKey;

    public function __construct()
    {
        $this->region = trim((string) env('AWS_REGION', 'ap-southeast-2')) ?: 'ap-southeast-2';
        $this->bucket = trim((string) env('AWS_S3_BUCKET', ''));
        $this->accessKeyId = trim((string) env('AWS_ACCESS_KEY_ID', ''));
        $this->secretAccessKey = trim((string) env('AWS_SECRET_ACCESS_KEY', ''));
    }

    public function store(UploadedFile $file, string $directory = 'media'): string
    {
        $directory = trim($directory, '/');
        $extension = $this->extensionFor($file);
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        if ($this->bucket !== '') {
            $key = $directory . '/' . date('Y/m') . '/' . $filename;
            $stream = fopen($file->getTempName(), 'rb');
            if ($stream === false) {
                throw new RuntimeException('Unable to read the uploaded media file.');
            }

            try {
                $this->client()->putObject([
                    'Bucket' => $this->bucket,
                    'Key' => $key,
                    'Body' => $stream,
                    'ContentType' => $file->getMimeType(),
                    'CacheControl' => 'public, max-age=31536000, immutable',
                ]);
            } finally {
                fclose($stream);
            }

            return sprintf('s3://%s/%s', $this->bucket, $key);
        }

        $relativeDirectory = 'uploads/' . $directory;
        $absoluteDirectory = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (! is_dir($absoluteDirectory) && ! mkdir($absoluteDirectory, 0775, true) && ! is_dir($absoluteDirectory)) {
            throw new RuntimeException('Unable to create the local media directory.');
        }

        $file->move($absoluteDirectory, $filename, true);

        return $relativeDirectory . '/' . $filename;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (str_starts_with($path, 's3://')) {
            $parts = parse_url($path);
            $bucket = $parts['host'] ?? '';
            $key = ltrim($parts['path'] ?? '', '/');
            if ($bucket === '' || $key === '') {
                return;
            }

            try {
                $this->client()->deleteObject(['Bucket' => $bucket, 'Key' => $key]);
            } catch (Throwable) {
                // Media cleanup must not block product updates or deletion.
            }

            return;
        }

        if (preg_match('#^https?://#i', $path)) {
            return;
        }

        $absolutePath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function client(): S3Client
    {
        if (! class_exists(S3Client::class)) {
            throw new RuntimeException('AWS S3 support requires the aws/aws-sdk-php Composer package.');
        }

        $options = [
            'version' => 'latest',
            'region' => $this->region,
        ];

        if ($this->accessKeyId !== '' && $this->secretAccessKey !== '') {
            $options['credentials'] = [
                'key' => $this->accessKeyId,
                'secret' => $this->secretAccessKey,
            ];
        }

        return new S3Client($options);
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
