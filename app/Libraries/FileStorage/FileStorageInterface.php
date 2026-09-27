<?php

namespace App\Libraries\FileStorage;

use CodeIgniter\Files\File;

/**
 * Every module that accepts uploads (task attachments, documents,
 * bank account proofs, compliance attachments, ...) goes through
 * this interface instead of touching the filesystem/S3 SDK directly,
 * so STORAGE_DRIVER=local|s3 is a one-line .env change, not a code change.
 */
interface FileStorageInterface
{
    /**
     * Stores an uploaded file under the given logical folder
     * (e.g. "tasks/42") and returns the relative path to persist in the DB.
     */
    public function store(File $file, string $folder): string;

    /**
     * A URL (or route) the browser can use to download/view the file.
     */
    public function url(string $relativePath): string;

    public function delete(string $relativePath): bool;
}
