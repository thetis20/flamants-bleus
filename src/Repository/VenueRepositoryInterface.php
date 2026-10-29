<?php

namespace App\Repository;

use App\Model\Venue;

interface VenueRepositoryInterface
{
    /** @return list<Venue> sorted by name */
    public function all(): array;

    /** @return list<Venue> shown on the home page, sorted by name */
    public function onHome(): array;

    public function find(string $id): ?Venue;

    public function findBySlug(string $slug): ?Venue;

    public function save(Venue $venue): void;

    public function delete(string $id): void;
}
