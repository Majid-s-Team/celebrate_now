<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ImageUploadTrait
{
    /**
     * Upload image based on key and return the URL
     */
    public function uploadImageByKey(UploadedFile $image, string $key): string
    {
        $folders = [
            'profile' => 'profile_images',
            'cover'   => 'cover_images',
            'post'    => 'post_images',
            'gallery' => 'gallery_images',
            'logo'    => 'logo_images',
            'video'   => 'videos', 
        ];


        $folder = $folders[$key] ?? 'others';

        $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();

        $path = $image->storeAs($folder, $filename, 'public');

        return asset('storage/' . $path);
    }
}
