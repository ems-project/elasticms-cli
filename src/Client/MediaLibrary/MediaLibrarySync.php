<?php

declare(strict_types=1);

namespace App\CLI\Client\MediaLibrary;

use Elastica\Query\Terms;
use EMS\CommonBundle\Contracts\CoreApi\CoreApiExceptionInterface;
use EMS\CommonBundle\Contracts\CoreApi\CoreApiInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Search\Search;
use GuzzleHttp\Psr7\Stream;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

final class MediaLibrarySync
{
    /**
     * @param array{content_type: string, folder_field: string, path_field: string, file_field: string} $config
     */
    public function __construct(private readonly string $folder, private readonly array $config, private readonly SymfonyStyle $io, private readonly bool $dryRun, private readonly CoreApiInterface $coreApi)
    {
    }

    public function execute(): self
    {
        $this->io->title('MediaLibrary sync files located in a folder');

        $finder = new Finder();
        $finder->files()->in($this->folder);

        if (!$finder->hasResults()) {
            throw new \RuntimeException('No files found!');
        }

        $this->io->comment(\sprintf('%d files located', $finder->count()));

        $progressBar = $this->io->createProgressBar($finder->count());

        foreach ($finder as $file) {
            try {
                $position = \strpos($file->getRealPath(), $this->folder);
                $path = \substr($file->getRealPath(), $position + \strlen($this->folder));
                if (!\str_starts_with($path, '/')) {
                    $path = '/'.$path;
                }
                $this->uploadMediaFile($file, $path);
            } catch (\Throwable $e) {
                $this->io->error(\sprintf('Upload failed for "%s" (%s)', $file->getRealPath(), $e->getMessage()));
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->io->newLine();

        return $this;
    }

    private function uploadMediaFile(\SplFileInfo $file, string $path): string
    {
        $exploded = \explode('/', $path);
        $ouuid = null;
        $defaultAlias = $this->coreApi->meta()->getDefaultContentTypeEnvironmentAlias($this->config['content_type']);
        $contentTypeApi = $this->coreApi->data($this->config['content_type']);
        while (\count($exploded) > 1) {
            $path = \implode('/', $exploded);
            \array_pop($exploded);
            $folder = \implode('/', $exploded).'/';

            $data = [
                $this->config['path_field'] => $path,
                $this->config['folder_field'] => $folder,
            ];
            if (null === $ouuid) {
                $data[$this->config['file_field']] = $this->urlToAssetArray($file);
            }

            $term = new Terms($this->config['path_field'], [$path]);
            $search = new Search([$defaultAlias], $term->toArray());
            $search->setContentTypes([$this->config['content_type']]);
            $result = $this->coreApi->search()->search($search);
            $document = null;
            foreach ($result->getDocuments() as $item) {
                $document = $item;
                break;
            }

            if (!$this->dryRun) {
                if (null === $document) {
                    $draft = $contentTypeApi->create($data);
                } elseif (\is_array($source = $data[$this->config['file_field']] ?? null) && \is_array($target = $document->getSource()[$this->config['file_field']] ?? null) && empty(\array_diff($source, $target)) && $data[$this->config['folder_field']] === ($document->getSource()[$this->config['folder_field']] ?? null)) {
                    $ouuid ??= $document->getId();
                    continue;
                } else {
                    $draft = $contentTypeApi->update($document->getId(), $data);
                }

                if (null === $ouuid) {
                    $ouuid = $contentTypeApi->finalize($draft->getRevisionId());
                } else {
                    $contentTypeApi->finalize($draft->getRevisionId());
                }
            }
        }

        return \sprintf('ems://file:%s:%s', $this->config['content_type'], $ouuid);
    }

    /**
     * @return array{sha1: string, filename: string, mimetype: string, filesize: int|null }|array{}
     */
    public function urlToAssetArray(\SplFileInfo $file): array
    {
        $mimeType = \mime_content_type($file->getRealPath());
        $mimeType = $mimeType ?: 'application/bin';
        $hash = '';

        $filename = $file->getFilename();
        if (null == $filename) {
            $this->io->error(\sprintf('No filename for "%s"', $file->getRealPath()));

            return [];
        }

        $resource = \fopen($file->getRealPath(), 'rb');
        if (false === $resource) {
            $this->io->error(\sprintf('Not able to open filename for "%s"', $file->getRealPath()));

            return [];
        }

        $stream = new Stream($resource);
        $stream->seek(0);
        if (!$this->dryRun) {
            try {
                $hash = $this->coreApi->file()->uploadStream($stream, $file->getFilename(), $mimeType);
            } catch (CoreApiExceptionInterface $e) {
                $this->io->error(\sprintf('Asset failed for "%s" (%s)', $file->getRealPath(), $e->getMessage()));

                return [];
            }
            if (0 === \strlen($hash)) {
                $this->io->error(\sprintf('Unexpected empty hash for "%s"', $file->getRealPath()));

                return [];
            }
        }

        return [
            EmsFields::CONTENT_FILE_HASH_FIELD => $hash,
            EmsFields::CONTENT_FILE_NAME_FIELD => $filename,
            EmsFields::CONTENT_MIME_TYPE_FIELD => $mimeType,
            EmsFields::CONTENT_FILE_SIZE_FIELD => $file->getSize() ? $file->getSize() : null,
        ];
    }
}