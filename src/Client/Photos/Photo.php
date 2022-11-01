<?php

namespace App\Client\Photos;

class Photo
{
    private string $ouuid;
    private string $filename;
    private string $libraryType;
    private string $source;
    /** @var mixed[]|null */
    private ?array $previewFile = null;

    public function __construct(string $libraryType, string $source, string $ouuid, string $filename)
    {
        $this->libraryType = $libraryType;
        $this->source = $source;
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
    public function getData(): array
    {
        return \array_filter([
            'library-type' => $this->libraryType,
            'source' => $this->source,
            'filename' => $this->filename,
            'preview-file' => $this->previewFile,
        ]);
    }

    /**
     * @param mixed[] $previewFile
     */
    public function setPreviewFile(array $previewFile): void
    {
        $this->previewFile = $previewFile;
    }
}
