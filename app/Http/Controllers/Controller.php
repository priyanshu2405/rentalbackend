<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Store uploaded files (Cloudinary when configured, else the public disk) and return what to save in the DB. */
    protected function storeUploads(array $files, string $folder): array
    {
        return app(\App\Services\MediaStorage::class)->storeMany($files, $folder);
    }
}
