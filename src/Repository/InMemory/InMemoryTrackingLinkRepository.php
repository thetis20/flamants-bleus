<?php

namespace App\Repository\InMemory;

use App\Model\TrackingLink;
use App\Repository\TrackingLinkRepositoryInterface;

final class InMemoryTrackingLinkRepository implements TrackingLinkRepositoryInterface
{
    /** @var array<string, TrackingLink> */
    private array $links = [];

    /** @var array<string, array<string, int>> */
    private array $days = [];

    public function all(): array
    {
        $links = array_values(array_map(fn (TrackingLink $l) => clone $l, $this->links));
        usort($links, fn (TrackingLink $a, TrackingLink $b) => strcasecmp($a->label, $b->label));

        return $links;
    }

    public function find(string $slug): ?TrackingLink
    {
        return isset($this->links[$slug]) ? clone $this->links[$slug] : null;
    }

    public function save(TrackingLink $link): void
    {
        $this->links[$link->slug] = clone $link;
    }

    public function delete(string $slug): void
    {
        unset($this->links[$slug], $this->days[$slug]);
    }

    public function hit(string $slug, \DateTimeImmutable $day): bool
    {
        if (!isset($this->links[$slug])) {
            return false;
        }

        ++$this->links[$slug]->total;
        $key = $day->format('Y-m-d');
        $this->days[$slug][$key] = ($this->days[$slug][$key] ?? 0) + 1;

        return true;
    }

    public function dailyCounts(string $slug, \DateTimeImmutable $since): array
    {
        $from = $since->format('Y-m-d');
        $counts = array_filter($this->days[$slug] ?? [], fn (string $day) => $day >= $from, \ARRAY_FILTER_USE_KEY);
        ksort($counts);

        return $counts;
    }
}
