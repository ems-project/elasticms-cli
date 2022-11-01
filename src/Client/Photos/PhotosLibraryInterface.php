<?php

namespace App\Client\Photos;

interface PhotosLibraryInterface
{
    public function photosCount(): int;

    /**
     * @return iterable<Photo>
     */
    public function getPhotos(): iterable;
}
