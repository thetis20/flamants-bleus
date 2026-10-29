<?php

namespace App\Repository\Firestore;

use App\Model\Format;
use App\Repository\FormatRepositoryInterface;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FirestoreClient;
use Symfony\Contracts\Service\ResetInterface;

final class FirestoreFormatRepository implements FormatRepositoryInterface, ResetInterface
{
    private const COLLECTION = 'formats';

    /** A page asks for every format more than once (agenda, then the list); one read per request is enough. */
    private ?array $all = null;

    public function __construct(private FirestoreClient $db)
    {
    }

    public function all(): array
    {
        if (null !== $this->all) {
            return $this->all;
        }

        $formats = [];
        foreach ($this->db->collection(self::COLLECTION)->documents() as $doc) {
            $formats[] = $this->hydrate($doc);
        }
        usort($formats, fn (Format $a, Format $b) => strcasecmp($a->title, $b->title));

        return $this->all = $formats;
    }

    public function reset(): void
    {
        $this->all = null;
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
        $this->reset();
        $this->db->collection(self::COLLECTION)->document($format->id)->set([
            'title' => $format->title,
            'slug' => $format->slug,
            'description' => $format->description,
            'logoPath' => $format->logoPath,
        ]);
    }

    public function delete(string $id): void
    {
        $this->reset();
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
