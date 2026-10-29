<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class Venue
{
    public string $id;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Minuscules, chiffres et tirets uniquement.')]
    public ?string $slug = null;

    #[Assert\Length(max: 300)]
    public ?string $address = null;

    #[Assert\Url(requireTld: true)]
    public ?string $websiteUrl = null;

    public ?string $logoPath = null;

    public bool $onHome = false;

    public function __construct(?string $id = null)
    {
        $this->id = $id ?? bin2hex(random_bytes(10));
    }
}
