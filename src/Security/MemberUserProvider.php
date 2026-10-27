<?php

namespace App\Security;

use App\Repository\MemberRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Reloading the member on each request logs out someone whose email was removed from the list.
 *
 * @implements UserProviderInterface<MemberUser>
 */
final readonly class MemberUserProvider implements UserProviderInterface
{
    public function __construct(private MemberRepositoryInterface $members)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (!$this->members->has($identifier)) {
            throw new UserNotFoundException();
        }

        return new MemberUser(mb_strtolower($identifier));
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof MemberUser) {
            throw new UnsupportedUserException();
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return MemberUser::class === $class;
    }
}
