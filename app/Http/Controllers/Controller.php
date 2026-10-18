<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Store uploaded files on the public disk and return their relative paths. */
    protected function storeUploads(array $files, string $folder): array
    {
        return array_values(array_map(fn ($file) => $file->store($folder, 'public'), $files));
    }
}
