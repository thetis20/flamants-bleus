<?php

namespace App\Repository\InMemory;

use App\Model\Member;
use App\Repository\MemberRepositoryInterface;

final class InMemoryMemberRepository implements MemberRepositoryInterface
{
    /** @var array<string, Member> */
    private array $members = [];

    public function all(): array
    {
        ksort($this->members);

        return array_values($this->members);
    }

    public function has(string $email): bool
    {
        return isset($this->members[mb_strtolower(trim($email))]);
    }

    public function add(Member $member): void
    {
        $this->members[$member->email] = $member;
    }

    public function remove(string $email): void
    {
        unset($this->members[mb_strtolower(trim($email))]);
    }
}
