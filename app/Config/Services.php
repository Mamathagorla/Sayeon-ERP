<?php

namespace Config;

use App\Libraries\FileStorage\FileStorageInterface;
use App\Libraries\FileStorage\LocalFileStorage;
use App\Libraries\FileStorage\S3FileStorage;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function fileStorage(bool $getShared = true): FileStorageInterface
    {
        if ($getShared) {
            return static::getSharedInstance('fileStorage');
        }

        return env('storage.driver', 'local') === 's3' ? new S3FileStorage() : new LocalFileStorage();
    }
}
