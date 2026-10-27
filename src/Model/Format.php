<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class Format
{
    public string $id;

    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    public ?string $title = null;

    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Minuscules, chiffres et tirets uniquement.')]
    public ?string $slug = null;

    #[Assert\Length(max: 3000)]
    public ?string $description = null;

    public ?string $logoPath = null;

    public function __construct(?string $id = null)
    {
        $this->id = $id ?? bin2hex(random_bytes(10));
    }
}
