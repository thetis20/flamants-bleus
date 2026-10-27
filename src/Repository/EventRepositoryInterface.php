<?php

namespace App\Repository;

use App\Model\Event;

interface EventRepositoryInterface
{
    /** @return list<Event> starting at or after $now, soonest first */
    public function upcoming(\DateTimeImmutable $now): array;

    /** @return list<Event> started before $now, most recent first */
    public function past(\DateTimeImmutable $now, int $limit): array;

    public function find(string $id): ?Event;

    public function save(Event $event): void;

    public function delete(string $id): void;
}
