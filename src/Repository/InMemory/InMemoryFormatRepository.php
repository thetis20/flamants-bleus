<?php

namespace App\Repository\InMemory;

use App\Model\Format;
use App\Repository\FormatRepositoryInterface;

final class InMemoryFormatRepository implements FormatRepositoryInterface
{
    /** @var array<string, Format> */
    private array $formats = [];

    public function all(): array
    {
        $formats = array_values(array_map(fn (Format $f) => clone $f, $this->formats));
        usort($formats, fn (Format $a, Format $b) => strcasecmp($a->title, $b->title));

        return $formats;
    }

    public function find(string $id): ?Format
    {
        return isset($this->formats[$id]) ? clone $this->formats[$id] : null;
    }

    public function findBySlug(string $slug): ?Format
    {
        foreach ($this->formats as $format) {
            if ($format->slug === $slug) {
                return clone $format;
            }
        }

        return null;
    }

    public function save(Format $format): void
    {
        $this->formats[$format->id] = clone $format;
    }

    public function delete(string $id): void
    {
        unset($this->formats[$id]);
    }
}
