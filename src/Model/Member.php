<?php

namespace App\Model;

final readonly class Member
{
    public string $email;

    public function __construct(
        string $email,
        public string $addedBy,
        public \DateTimeImmutable $addedAt,
    ) {
        $this->email = mb_strtolower(trim($email));
    }
}
