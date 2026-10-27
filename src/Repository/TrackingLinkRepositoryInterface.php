<?php

namespace App\Repository;

use App\Model\TrackingLink;

interface TrackingLinkRepositoryInterface
{
    /** @return list<TrackingLink> sorted by label */
    public function all(): array;

    public function find(string $slug): ?TrackingLink;

    public function save(TrackingLink $link): void;

    public function delete(string $slug): void;

    /** Counts one visit on $day; returns false when the slug is unknown. */
    public function hit(string $slug, \DateTimeImmutable $day): bool;

    /** @return array<string, int> visits per day (Y-m-d) since $since, days without visit omitted */
    public function dailyCounts(string $slug, \DateTimeImmutable $since): array;
}
