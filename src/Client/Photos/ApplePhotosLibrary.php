<?php

namespace App\Client\Photos;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

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
        while ($tmpRow = $results->fetchArray()) {
            /** @var array{Z_PK: int, ZUUID: string} $row */
            $row = $tmpRow;
            yield $this->generatePhoto($row);
        }
    }

    /**
     * @param array{Z_PK: int, ZUUID: string} $row
     */
    private function generatePhoto(array $row): Photo
    {
        $additionalInfo = $this->getAdditionalInfo($row['Z_PK']);
        $photo = new Photo('ApplePhotos', $this->libraryPath, \strtolower($row['ZUUID']), $additionalInfo['ZORIGINALFILENAME']);
        $photo->addMemberOf($this->getAlbums($row['Z_PK']));

        return $photo;
    }

    /**
     * @return array{ZORIGINALFILENAME: string}
     */
    private function getAdditionalInfo(int $assetId): array
    {
        /** @var array{ZORIGINALFILENAME: string} $result */
        $result = $this->photosDatabase->querySingle("SELECT * FROM ZADDITIONALASSETATTRIBUTES WHERE ZASSET = $assetId", true);
        if (!\is_array($result)) {
            throw new \RuntimeException('Unexpected not array result');
        }

        return $result;
    }

    public function getPreviewFile(Photo $photo): ?SplFileInfo
    {
        $zuuid = \strtoupper($photo->getOuuid());
        $firstChar = \substr($zuuid, 0, 1);
        $finder = new Finder();
        $finder->name("$zuuid*");
        foreach ($finder->in("$this->libraryPath/resources/derivatives/$firstChar") as $file) {
            return $file;
        }

        return null;
    }

    public function getOriginalFile(Photo $photo): ?SplFileInfo
    {
        $zuuid = \strtoupper($photo->getOuuid());
        $firstChar = \substr($zuuid, 0, 1);
        $finder = new Finder();
        $finder->name("$zuuid*");
        foreach ($finder->in("$this->libraryPath/originals/$firstChar") as $file) {
            return $file;
        }

        return null;
    }

    /**
     * @return mixed[][]
     */
    private function getAlbums(int $assetId): array
    {
        $results = $this->photosDatabase->query("SELECT * FROM Z_28ASSETS, ZGENERICALBUM WHERE Z_3ASSETS = $assetId AND Z_PK = Z_28ALBUMS");
        if (false === $results) {
            throw new \RuntimeException('Unexpected false result');
        }
        $albums = [];
        while ($row = $results->fetchArray()) {
            if (!isset($row['ZTITLE'])) {
                continue;
            }
            $albums[] = [
                'type' => 'album',
                'name' => $row['ZTITLE'],
                'parent' => 'photo_album:'.\strtolower($row['ZUUID']),
                'order' => $row['Z_FOK_3ASSETS'],
            ];
        }

        return $albums;
    }
}
