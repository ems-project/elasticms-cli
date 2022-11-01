<?php

namespace App\Client\Photos;

class ApplePhotosLibrary implements PhotosLibraryInterface
{
    private string $libraryPath;
    private \SQLite3 $photosDatabase;

    public function __construct(string $libraryPath)
    {
        $this->libraryPath = $libraryPath;
        $this->photosDatabase = new \SQLite3($this->libraryPath.'/database/Photos.sqlite', SQLITE3_OPEN_READONLY);
    }

    public function photosCount(): int
    {
        return \intval($this->photosDatabase->querySingle('select count(*) from ZASSET'));
    }

    /**
     * @return iterable<Photo>
     */
    public function getPhotos(): iterable
    {
        $results = $this->photosDatabase->query('SELECT * FROM ZASSET');
        if (false === $results) {
            throw new \RuntimeException('Unexpected false result');
        }
        while ($row = $results->fetchArray()) {
            $photo = new Photo();
            yield $photo;
        }
    }
}
