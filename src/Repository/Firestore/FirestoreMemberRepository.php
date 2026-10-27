<?php

namespace App\Repository\Firestore;

use App\Model\Member;
use App\Repository\MemberRepositoryInterface;
use Google\Cloud\Core\Timestamp;
use Google\Cloud\Firestore\FirestoreClient;

final class FirestoreMemberRepository implements MemberRepositoryInterface
{
    private const COLLECTION = 'members';

    public function __construct(private FirestoreClient $db)
    {
    }

    public function all(): array
    {
        $members = [];
        foreach ($this->db->collection(self::COLLECTION)->documents() as $doc) {
            $members[] = new Member(
                $doc->id(),
                $doc['addedBy'],
                \DateTimeImmutable::createFromInterface($doc['addedAt']->get()),
            );
        }
        usort($members, fn (Member $a, Member $b) => strcmp($a->email, $b->email));

        return $members;
    }

    public function has(string $email): bool
    {
        return $this->db->collection(self::COLLECTION)->document(mb_strtolower(trim($email)))->snapshot()->exists();
    }

    public function add(Member $member): void
    {
        $this->db->collection(self::COLLECTION)->document($member->email)->set([
            'addedBy' => $member->addedBy,
            'addedAt' => new Timestamp($member->addedAt),
        ]);
    }

    public function remove(string $email): void
    {
        $this->db->collection(self::COLLECTION)->document(mb_strtolower(trim($email)))->delete();
    }
}
