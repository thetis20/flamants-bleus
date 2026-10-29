<?php

namespace App\Repository\InMemory;

use App\Model\Venue;
use App\Repository\VenueRepositoryInterface;

final class InMemoryVenueRepository implements VenueRepositoryInterface
{
    /** @var array<string, Venue> */
    private array $venues = [];

    public function all(): array
    {
        $venues = array_values(array_map(fn (Venue $v) => clone $v, $this->venues));
        usort($venues, fn (Venue $a, Venue $b) => strcasecmp($a->name, $b->name));

        return $venues;
    }

    public function onHome(): array
    {
        return array_values(array_filter($this->all(), fn (Venue $v) => $v->onHome));
    }

    public function find(string $id): ?Venue
    {
        return isset($this->venues[$id]) ? clone $this->venues[$id] : null;
    }

    public function findBySlug(string $slug): ?Venue
    {
        foreach ($this->venues as $venue) {
            if ($venue->slug === $slug) {
                return clone $venue;
            }
        }

        return null;
    }

    public function save(Venue $venue): void
    {
        $this->venues[$venue->id] = clone $venue;
    }

    public function delete(string $id): void
    {
        unset($this->venues[$id]);
    }
}
