<?php

namespace App;

use App\Model\Event;
use App\Model\Format;
use App\Repository\EventRepositoryInterface;
use App\Repository\FormatRepositoryInterface;
use Psr\Clock\ClockInterface;

/**
 * Upcoming events joined with their format, as the public pages show them.
 */
final readonly class Agenda
{
    public function __construct(
        private EventRepositoryInterface $events,
        private FormatRepositoryInterface $formats,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<array{event: Event, format: ?Format}> */
    public function upcoming(?Format $onlyFormat = null): array
    {
        $formats = [];
        foreach ($this->formats->all() as $format) {
            $formats[$format->id] = $format;
        }

        // An event stays listed for the evening it is played on.
        $since = $this->clock->now()->setTime(0, 0);

        $rows = [];
        foreach ($this->events->upcoming($since) as $event) {
            if (null !== $onlyFormat && $event->formatId !== $onlyFormat->id) {
                continue;
            }
            $rows[] = ['event' => $event, 'format' => $formats[$event->formatId] ?? null];
        }

        return $rows;
    }
}
