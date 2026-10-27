<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class MemberUser implements UserInterface
{
    public function __construct(private string $email)
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return ['ROLE_MEMBER'];
    }

    public function eraseCredentials(): void
    {
    }
}
