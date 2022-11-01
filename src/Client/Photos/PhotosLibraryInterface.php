<?php

namespace App\Client\Photos;

use Symfony\Component\Finder\SplFileInfo;

interface PhotosLibraryInterface
{
    public function photosCount(): int;

    /**
     * @return iterable<Photo>
     */
    public function getPhotos(): iterable;

    public function getPreviewFile(Photo $photo): ?SplFileInfo;
}
