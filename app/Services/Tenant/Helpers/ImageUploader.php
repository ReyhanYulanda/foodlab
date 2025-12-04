<?php


namespace App\Services\Tenant\Helpers;


use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;


class ImageUploader
{
    public static function upload(UploadedFile $file)
    {
        $path = $file->store('public/images');
        return Storage::url($path);
    }
}
