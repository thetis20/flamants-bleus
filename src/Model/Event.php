<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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

    /** No booking at all for this date, as opposed to a booking link not created yet. */
    public bool $noBooking = false;

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

    #[Assert\Callback]
    public function validateBooking(ExecutionContextInterface $context): void
    {
        if ($this->noBooking && $this->bookingUrl) {
            $context->buildViolation('Retirez le lien ou décochez « Pas de réservation ».')
                ->atPath('bookingUrl')
                ->addViolation();
        }
    }
}
