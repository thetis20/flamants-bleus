<?php

namespace App\Repository\InMemory;

use App\Model\Event;
use App\Repository\EventRepositoryInterface;

final class InMemoryEventRepository implements EventRepositoryInterface
{
    /** @var array<string, Event> */
    private array $events = [];

    public function upcoming(\DateTimeImmutable $now): array
    {
        $events = array_filter($this->events, fn (Event $e) => $e->startsAt >= $now);
        usort($events, fn (Event $a, Event $b) => $a->startsAt <=> $b->startsAt);

        return array_map(fn (Event $e) => clone $e, $events);
    }

    public function past(\DateTimeImmutable $now, int $limit): array
    {
        $events = array_filter($this->events, fn (Event $e) => $e->startsAt < $now);
        usort($events, fn (Event $a, Event $b) => $b->startsAt <=> $a->startsAt);

        return array_map(fn (Event $e) => clone $e, \array_slice($events, 0, $limit));
    }

    public function find(string $id): ?Event
    {
        return isset($this->events[$id]) ? clone $this->events[$id] : null;
    }

    public function save(Event $event): void
    {
        $this->events[$event->id] = clone $event;
    }

    public function delete(string $id): void
    {
        unset($this->events[$id]);
    }
}
