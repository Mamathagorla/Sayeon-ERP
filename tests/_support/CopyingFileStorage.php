<?php

namespace Tests\Support;

use App\Libraries\FileStorage\LocalFileStorage;
use CodeIgniter\Files\File;

/**
 * Test double for the local disk adapter. LocalFileStorage::store() moves a file with rename(); on a Windows
 * dev machine with a real-time scanner rename() of a freshly written PDF is refused ("Access is denied"),
 * which makes TemplatedDocument::save() silently skip archiving. Copy + delete has the same observable
 * result (same relative path scheme, same files under writable/uploads) without the file-lock race.
 * Only the storage adapter is replaced; document generation, archiving and history logic stay real.
 */
final class CopyingFileStorage extends LocalFileStorage
{
    public function store(File $file, string $folder): string
    {
        $folder    = trim(str_replace(DIRECTORY_SEPARATOR, '/', $folder), '/');
        $targetDir = $this->basePath . DIRECTORY_SEPARATOR . $folder;
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $newName = $file->getRandomName();
        if (! copy($file->getPathname(), $targetDir . DIRECTORY_SEPARATOR . $newName)) {
            throw new \RuntimeException('Could not store file.');
        }

        return $folder . '/' . $newName;
    }
}
