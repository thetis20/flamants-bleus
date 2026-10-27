<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class TrackingLink
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    public ?string $label = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 60)]
    #[Assert\Regex('/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Minuscules, chiffres et tirets uniquement.')]
    public ?string $slug = null;

    // Internal path only, so a tracking link can never become an open redirect.
    #[Assert\NotBlank]
    #[Assert\Regex('#^/(?!/)[^\s]*$#', message: 'Chemin du site commençant par « / », par exemple « / » ou « /formats ».')]
    public ?string $target = '/';

    public int $total = 0;

    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
