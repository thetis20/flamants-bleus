<?php

namespace App\Storage;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;

final class LogoStorage
{
    public function __construct(
        #[Autowire(service: 'app.logo_filesystem')]
        private FilesystemOperator $filesystem,
        #[Autowire(env: 'LOGO_BASE_URL')]
        private string $baseUrl,
    ) {
    }

    /** @return string the stored path, to keep on the format */
    public function store(File $file, string $slug): string
    {
        $path = \sprintf('logos/%s-%s.%s', $slug, bin2hex(random_bytes(4)), $file->guessExtension() ?? 'bin');
        $stream = fopen($file->getPathname(), 'r');
        try {
            // The random suffix makes every upload a new URL, so browsers can keep it forever.
            $this->filesystem->writeStream($path, $stream, [
                'mimetype' => $file->getMimeType(),
                'metadata' => ['cacheControl' => 'public, max-age=31536000, immutable'],
            ]);
        } finally {
            \is_resource($stream) && fclose($stream);
        }

        return $path;
    }

    public function remove(?string $path): void
    {
        if (null !== $path) {
            $this->filesystem->delete($path);
        }
    }

    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.$path;
    }
}
