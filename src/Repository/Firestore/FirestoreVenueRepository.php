<?php

namespace App\Repository\Firestore;

use App\Model\Venue;
use App\Repository\VenueRepositoryInterface;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FirestoreClient;

final class FirestoreVenueRepository implements VenueRepositoryInterface
{
    private const COLLECTION = 'venues';

    public function __construct(private FirestoreClient $db)
    {
    }

    public function all(): array
    {
        $venues = [];
        foreach ($this->db->collection(self::COLLECTION)->documents() as $doc) {
            $venues[] = $this->hydrate($doc);
        }
        usort($venues, fn (Venue $a, Venue $b) => strcasecmp($a->name, $b->name));

        return $venues;
    }

    public function onHome(): array
    {
        return array_values(array_filter($this->all(), fn (Venue $v) => $v->onHome));
    }

    public function find(string $id): ?Venue
    {
        $doc = $this->db->collection(self::COLLECTION)->document($id)->snapshot();

        return $doc->exists() ? $this->hydrate($doc) : null;
    }

    public function findBySlug(string $slug): ?Venue
    {
        foreach ($this->db->collection(self::COLLECTION)->where('slug', '=', $slug)->limit(1)->documents() as $doc) {
            return $this->hydrate($doc);
        }

        return null;
    }

    public function save(Venue $venue): void
    {
        $this->db->collection(self::COLLECTION)->document($venue->id)->set([
            'name' => $venue->name,
            'slug' => $venue->slug,
            'address' => $venue->address,
            'websiteUrl' => $venue->websiteUrl,
            'logoPath' => $venue->logoPath,
            'onHome' => $venue->onHome,
        ]);
    }

    public function delete(string $id): void
    {
        $this->db->collection(self::COLLECTION)->document($id)->delete();
    }

    private function hydrate(DocumentSnapshot $doc): Venue
    {
        $venue = new Venue($doc->id());
        $venue->name = $doc['name'];
        $venue->slug = $doc['slug'];
        $venue->address = $doc['address'] ?? null;
        $venue->websiteUrl = $doc['websiteUrl'] ?? null;
        $venue->logoPath = $doc['logoPath'] ?? null;
        $venue->onHome = (bool) ($doc['onHome'] ?? false);

        return $venue;
    }
}
