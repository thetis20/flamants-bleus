<?php

namespace App;

use App\Model\Event;
use App\Model\Format;
use App\Model\Venue;
use App\Repository\EventRepositoryInterface;
use App\Repository\FormatRepositoryInterface;
use App\Repository\VenueRepositoryInterface;
use Psr\Clock\ClockInterface;

/**
 * Upcoming events joined with their format and venue, as the public pages show them.
 */
final readonly class Agenda
{
    public function __construct(
        private EventRepositoryInterface $events,
        private FormatRepositoryInterface $formats,
        private VenueRepositoryInterface $venues,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<array{event: Event, format: ?Format, venue: ?Venue}> */
    public function upcoming(?Format $onlyFormat = null, ?Venue $onlyVenue = null): array
    {
        $formats = [];
        foreach ($this->formats->all() as $format) {
            $formats[$format->id] = $format;
        }
        $venues = [];
        foreach ($this->venues->all() as $venue) {
            $venues[$venue->id] = $venue;
        }

        // An event stays listed for the evening it is played on.
        $since = $this->clock->now()->setTime(0, 0);

        $rows = [];
        foreach ($this->events->upcoming($since) as $event) {
            if (null !== $onlyFormat && $event->formatId !== $onlyFormat->id) {
                continue;
            }
            if (null !== $onlyVenue && $event->venueId !== $onlyVenue->id) {
                continue;
            }
            $rows[] = [
                'event' => $event,
                'format' => $formats[$event->formatId] ?? null,
                'venue' => null !== $event->venueId ? $venues[$event->venueId] ?? null : null,
            ];
        }

        return $rows;
    }
}
