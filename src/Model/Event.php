<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class Event
{
    public string $id;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public ?string $title = null;

    #[Assert\NotNull]
    public ?\DateTimeImmutable $startsAt = null;

    #[Assert\NotBlank(message: 'Choisissez un format.')]
    public ?string $formatId = null;

    #[Assert\NotBlank(message: 'Choisissez un lieu.')]
    public ?string $venueId = null;

    /** Free-text venue of dates saved before venues were managed, shown until a venue is chosen. */
    public ?string $venue = null;

    #[Assert\Length(max: 60)]
    public ?string $price = null;

    #[Assert\Url(requireTld: true)]
    public ?string $bookingUrl = null;

    public function __construct(?string $id = null)
    {
        $this->id = $id ?? bin2hex(random_bytes(10));
    }

    public function duplicate(): self
    {
        $copy = clone $this;
        $copy->id = bin2hex(random_bytes(10));

        return $copy;
    }
}
