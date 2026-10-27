<?php

namespace App\Repository\Firestore;

use App\Model\Event;
use App\Repository\EventRepositoryInterface;
use Google\Cloud\Core\Timestamp;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\Query;

final class FirestoreEventRepository implements EventRepositoryInterface
{
    private const COLLECTION = 'events';

    public function __construct(private FirestoreClient $db)
    {
    }

    public function upcoming(\DateTimeImmutable $now): array
    {
        $query = $this->db->collection(self::COLLECTION)
            ->where('startsAt', '>=', new Timestamp($now))
            ->orderBy('startsAt');

        return $this->hydrateAll($query);
    }

    public function past(\DateTimeImmutable $now, int $limit): array
    {
        $query = $this->db->collection(self::COLLECTION)
            ->where('startsAt', '<', new Timestamp($now))
            ->orderBy('startsAt', Query::DIR_DESCENDING)
            ->limit($limit);

        return $this->hydrateAll($query);
    }

    public function find(string $id): ?Event
    {
        $doc = $this->db->collection(self::COLLECTION)->document($id)->snapshot();

        return $doc->exists() ? $this->hydrate($doc) : null;
    }

    public function save(Event $event): void
    {
        $this->db->collection(self::COLLECTION)->document($event->id)->set([
            'title' => $event->title,
            'startsAt' => new Timestamp($event->startsAt),
            'formatId' => $event->formatId,
            'venue' => $event->venue,
            'price' => $event->price,
            'bookingUrl' => $event->bookingUrl,
        ]);
    }

    public function delete(string $id): void
    {
        $this->db->collection(self::COLLECTION)->document($id)->delete();
    }

    /** @return list<Event> */
    private function hydrateAll(Query $query): array
    {
        $events = [];
        foreach ($query->documents() as $doc) {
            $events[] = $this->hydrate($doc);
        }

        return $events;
    }

    private function hydrate(DocumentSnapshot $doc): Event
    {
        $event = new Event($doc->id());
        $event->title = $doc['title'];
        $event->startsAt = \DateTimeImmutable::createFromInterface($doc['startsAt']->get())
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $event->formatId = $doc['formatId'];
        $event->venue = $doc['venue'];
        $event->price = $doc['price'] ?? null;
        $event->bookingUrl = $doc['bookingUrl'] ?? null;

        return $event;
    }
}
