<?php

namespace App\Libraries\FileStorage;

use Aws\S3\S3Client;
use CodeIgniter\Files\File;

class S3FileStorage implements FileStorageInterface
{
    protected S3Client $client;
    protected string $bucket;

    public function __construct()
    {
        $this->bucket = env('storage.s3.bucket');

        $this->client = new S3Client([
            'version'     => 'latest',
            'region'      => env('storage.s3.region', 'ap-south-1'),
            'credentials' => [
                'key'    => env('storage.s3.key'),
                'secret' => env('storage.s3.secret'),
            ],
        ]);
    }

    public function store(File $file, string $folder): string
    {
        $key = trim($folder, '/') . '/' . $file->getRandomName();

        $this->client->putObject([
            'Bucket'     => $this->bucket,
            'Key'        => $key,
            'SourceFile' => $file->getRealPath(),
            'ACL'        => 'private',
        ]);

        return $key;
    }

    public function url(string $relativePath): string
    {
        $cmd     = $this->client->getCommand('GetObject', ['Bucket' => $this->bucket, 'Key' => $relativePath]);
        $request = $this->client->createPresignedRequest($cmd, '+15 minutes');

        return (string) $request->getUri();
    }

    public function delete(string $relativePath): bool
    {
        $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $relativePath]);

        return true;
    }
}
