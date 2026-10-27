<?php

namespace App\Repository;

use App\Model\Format;

interface FormatRepositoryInterface
{
    /** @return list<Format> sorted by title */
    public function all(): array;

    public function find(string $id): ?Format;

    public function findBySlug(string $slug): ?Format;

    public function save(Format $format): void;

    public function delete(string $id): void;
}
