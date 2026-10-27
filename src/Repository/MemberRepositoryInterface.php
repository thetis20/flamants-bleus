<?php

namespace App\Repository;

use App\Model\Member;

interface MemberRepositoryInterface
{
    /** @return list<Member> sorted by email */
    public function all(): array;

    public function has(string $email): bool;

    public function add(Member $member): void;

    public function remove(string $email): void;
}
