<?php

namespace App\Libraries\FileStorage;

use CodeIgniter\Files\File;

class LocalFileStorage implements FileStorageInterface
{
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = rtrim(WRITEPATH . 'uploads', '/\\');
    }

    public function store(File $file, string $folder): string
    {
        $folder    = trim($folder, '/\\');
        $targetDir = $this->basePath . DIRECTORY_SEPARATOR . $folder;

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $newName = $file->getRandomName();
        $file->move($targetDir, $newName);

        return $folder . '/' . $newName;
    }

    public function url(string $relativePath): string
    {
        // Served through a controller-gated download route, not a public
        // static path, so RBAC applies to file access too (see FileController).
        return site_url('files/download?path=' . urlencode($relativePath));
    }

    public function delete(string $relativePath): bool
    {
        $full = $this->basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);

        return is_file($full) ? unlink($full) : true;
    }

    public function absolutePath(string $relativePath): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }
}
