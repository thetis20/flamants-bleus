<?php

namespace App\Repository\Firestore;

use App\Model\Format;
use App\Repository\FormatRepositoryInterface;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FirestoreClient;

final class FirestoreFormatRepository implements FormatRepositoryInterface
{
    private const COLLECTION = 'formats';

    public function __construct(private FirestoreClient $db)
    {
    }

    public function all(): array
    {
        $formats = [];
        foreach ($this->db->collection(self::COLLECTION)->documents() as $doc) {
            $formats[] = $this->hydrate($doc);
        }
        usort($formats, fn (Format $a, Format $b) => strcasecmp($a->title, $b->title));

        return $formats;
    }

    public function find(string $id): ?Format
    {
        $doc = $this->db->collection(self::COLLECTION)->document($id)->snapshot();

        return $doc->exists() ? $this->hydrate($doc) : null;
    }

    public function findBySlug(string $slug): ?Format
    {
        foreach ($this->db->collection(self::COLLECTION)->where('slug', '=', $slug)->limit(1)->documents() as $doc) {
            return $this->hydrate($doc);
        }

        return null;
    }

    public function save(Format $format): void
    {
        $this->db->collection(self::COLLECTION)->document($format->id)->set([
            'title' => $format->title,
            'slug' => $format->slug,
            'description' => $format->description,
            'logoPath' => $format->logoPath,
        ]);
    }

    public function delete(string $id): void
    {
        $this->db->collection(self::COLLECTION)->document($id)->delete();
    }

    private function hydrate(DocumentSnapshot $doc): Format
    {
        $format = new Format($doc->id());
        $format->title = $doc['title'];
        $format->slug = $doc['slug'];
        $format->description = $doc['description'] ?? null;
        $format->logoPath = $doc['logoPath'] ?? null;

        return $format;
    }
}
