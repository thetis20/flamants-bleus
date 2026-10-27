<?php

namespace App\Repository\Firestore;

use App\Model\TrackingLink;
use App\Repository\TrackingLinkRepositoryInterface;
use Google\Cloud\Core\Exception\NotFoundException;
use Google\Cloud\Core\Timestamp;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FieldValue;
use Google\Cloud\Firestore\FirestoreClient;

final class FirestoreTrackingLinkRepository implements TrackingLinkRepositoryInterface
{
    private const COLLECTION = 'tracking_links';
    private const DAYS = 'days';

    public function __construct(private FirestoreClient $db)
    {
    }

    public function all(): array
    {
        $links = [];
        foreach ($this->db->collection(self::COLLECTION)->documents() as $doc) {
            $links[] = $this->hydrate($doc);
        }
        usort($links, fn (TrackingLink $a, TrackingLink $b) => strcasecmp($a->label, $b->label));

        return $links;
    }

    public function find(string $slug): ?TrackingLink
    {
        $doc = $this->db->collection(self::COLLECTION)->document($slug)->snapshot();

        return $doc->exists() ? $this->hydrate($doc) : null;
    }

    public function save(TrackingLink $link): void
    {
        $this->db->collection(self::COLLECTION)->document($link->slug)->set([
            'label' => $link->label,
            'target' => $link->target,
            'createdAt' => new Timestamp($link->createdAt),
        ], ['merge' => true]);
    }

    public function delete(string $slug): void
    {
        $doc = $this->db->collection(self::COLLECTION)->document($slug);
        foreach ($doc->collection(self::DAYS)->listDocuments() as $day) {
            $day->delete();
        }
        $doc->delete();
    }

    public function hit(string $slug, \DateTimeImmutable $day): bool
    {
        $doc = $this->db->collection(self::COLLECTION)->document($slug);
        try {
            // update() fails on a missing document, which saves a read per visit.
            $doc->update([['path' => 'total', 'value' => FieldValue::increment(1)]]);
        } catch (NotFoundException) {
            return false;
        }

        $key = $day->format('Y-m-d');
        $doc->collection(self::DAYS)->document($key)->set([
            'day' => $key,
            'count' => FieldValue::increment(1),
        ], ['merge' => true]);

        return true;
    }

    public function dailyCounts(string $slug, \DateTimeImmutable $since): array
    {
        $query = $this->db->collection(self::COLLECTION)->document($slug)->collection(self::DAYS)
            ->where('day', '>=', $since->format('Y-m-d'))
            ->orderBy('day');

        $counts = [];
        foreach ($query->documents() as $doc) {
            $counts[$doc['day']] = $doc['count'];
        }

        return $counts;
    }

    private function hydrate(DocumentSnapshot $doc): TrackingLink
    {
        $link = new TrackingLink();
        $link->slug = $doc->id();
        $link->label = $doc['label'];
        $link->target = $doc['target'];
        $link->total = $doc['total'] ?? 0;
        $link->createdAt = \DateTimeImmutable::createFromInterface($doc['createdAt']->get());

        return $link;
    }
}
