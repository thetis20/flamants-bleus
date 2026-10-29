<?php

namespace App\Tests;

use App\Model\Event;
use App\Model\Format;
use App\Model\Member;
use App\Model\Venue;
use App\Repository\EventRepositoryInterface;
use App\Repository\FormatRepositoryInterface;
use App\Repository\MemberRepositoryInterface;
use App\Repository\TrackingLinkRepositoryInterface;
use App\Repository\VenueRepositoryInterface;
use App\Security\MemberUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;

abstract class WebTestCase extends BaseWebTestCase
{
    protected const NOW = '2026-10-27 15:00';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        Clock::set(new MockClock(self::NOW, 'Europe/Paris'));
        $this->client = static::createClient();
        // Keep the in-memory repositories alive between requests.
        $this->client->disableReboot();
    }

    protected function formats(): FormatRepositoryInterface
    {
        return static::getContainer()->get(FormatRepositoryInterface::class);
    }

    protected function events(): EventRepositoryInterface
    {
        return static::getContainer()->get(EventRepositoryInterface::class);
    }

    protected function venues(): VenueRepositoryInterface
    {
        return static::getContainer()->get(VenueRepositoryInterface::class);
    }

    protected function members(): MemberRepositoryInterface
    {
        return static::getContainer()->get(MemberRepositoryInterface::class);
    }

    protected function links(): TrackingLinkRepositoryInterface
    {
        return static::getContainer()->get(TrackingLinkRepositoryInterface::class);
    }

    protected function loginAsMember(string $email = 'alice@gmail.com'): void
    {
        $this->members()->add(new Member($email, 'test', new \DateTimeImmutable()));
        $this->client->loginUser(new MemberUser($email));
    }

    /** Token for a form the page does not render, stored in the browser's session. */
    protected function csrfToken(string $id): string
    {
        $session = $this->client->getRequest()->getSession();
        $requestStack = static::getContainer()->get('request_stack');
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);
        try {
            return static::getContainer()->get('security.csrf.token_manager')->getToken($id)->getValue();
        } finally {
            $requestStack->pop();
            $session->save();
        }
    }

    protected function givenFormat(string $title = 'Cabaret d\'impro', string $slug = 'cabaret-impro'): Format
    {
        $format = new Format();
        $format->title = $title;
        $format->slug = $slug;
        $this->formats()->save($format);

        return $format;
    }

    protected function givenVenue(string $name = 'Blue Monkeys', string $slug = 'blue-monkeys', bool $onHome = false): Venue
    {
        $venue = new Venue();
        $venue->name = $name;
        $venue->slug = $slug;
        $venue->onHome = $onHome;
        $this->venues()->save($venue);

        return $venue;
    }

    /** Without a venue, the event carries the free-text venue of dates saved before venues were managed. */
    protected function givenEvent(Format $format, string $startsAt, string $title = 'Cabaret d\'impro', ?Venue $venue = null): Event
    {
        $event = new Event();
        $event->title = $title;
        $event->startsAt = new \DateTimeImmutable($startsAt, new \DateTimeZone('Europe/Paris'));
        $event->formatId = $format->id;
        $event->venueId = $venue?->id;
        $event->venue = null === $venue ? 'Blue Monkey' : null;
        $this->events()->save($event);

        return $event;
    }
}
