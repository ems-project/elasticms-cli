<?php

namespace App\Client\Photos;

class Photo
{
    private string $ouuid;
    private string $filename;

    public function __construct(string $ouuid, string $filename)
    {
        $this->ouuid = $ouuid;
        $this->filename = $filename;
    }

    public function getOuuid(): string
    {
        return $this->ouuid;
    }

    /**
     * @return mixed[]
     */
    public function getJson(): array
    {
        return [
            'filename' => $this->filename,
        ];
    }
}
