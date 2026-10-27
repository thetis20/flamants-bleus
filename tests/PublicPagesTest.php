<?php

namespace App\Tests;

final class PublicPagesTest extends WebTestCase
{
    public function testHomeListsUpcomingEventsSoonestFirstAndHidesPastOnes(): void
    {
        $cabaret = $this->givenFormat();
        $this->givenEvent($cabaret, '2026-10-23 20:00', 'Cabaret passé');
        $this->givenEvent($cabaret, '2026-11-20 20:00', 'Cabaret de novembre');
        $this->givenEvent($cabaret, '2026-11-06 20:00', 'Doublage Improvisé');

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['Doublage Improvisé', 'Cabaret de novembre'],
            $crawler->filter('.event-title')->each(fn ($node) => $node->text()),
        );
    }

    public function testEventOfTheDayStaysListedAfterItsStartTime(): void
    {
        $this->givenEvent($this->givenFormat(), '2026-10-27 11:00', 'Matinée');

        $this->client->request('GET', '/');

        self::assertSelectorTextContains('.event-title', 'Matinée');
    }

    public function testEventCardShowsDateVenueAndBookingLink(): void
    {
        $event = $this->givenEvent($this->givenFormat(), '2026-11-06 20:30');
        $event->price = '8 €';
        $event->bookingUrl = 'https://billetterie.example/cabaret';
        $this->events()->save($event);

        $this->client->request('GET', '/');

        self::assertSelectorTextContains('.date-tile .month', 'NOVEMBRE');
        self::assertSelectorTextContains('.date-tile .day', '06');
        self::assertSelectorTextContains('.date-tile .weekday', 'Ven');
        self::assertSelectorTextContains('.event-meta', 'Blue Monkey');
        self::assertSelectorTextContains('.event-meta', '20h30');
        self::assertSelectorTextContains('.event-meta', '8 €');
        self::assertSelectorExists('a.button[href="https://billetterie.example/cabaret"]');
    }

    public function testEventWithoutBookingLinkAnnouncesItIsComing(): void
    {
        $this->givenEvent($this->givenFormat(), '2026-11-20 20:00');

        $this->client->request('GET', '/');

        self::assertSelectorTextContains('.booking-soon', 'Réservations bientôt ouvertes');
        self::assertSelectorNotExists('.event-card a.button');
    }

    public function testEmptyAgendaShowsAMessage(): void
    {
        $this->client->request('GET', '/');

        self::assertSelectorTextContains('.empty', 'Pas de spectacle annoncé');
    }

    public function testFormatPageListsOnlyItsUpcomingEvents(): void
    {
        $cabaret = $this->givenFormat();
        $doublage = $this->givenFormat('Doublage Improvisé', 'doublage-improvise');
        $this->givenEvent($cabaret, '2026-11-20 20:00', 'Cabaret de novembre');
        $this->givenEvent($doublage, '2026-11-06 20:00', 'Doublage de novembre');

        $crawler = $this->client->request('GET', '/formats/doublage-improvise');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Doublage Improvisé');
        self::assertSame(['Doublage de novembre'], $crawler->filter('.event-title')->each(fn ($node) => $node->text()));
    }

    public function testUnknownFormatIsNotFound(): void
    {
        $this->client->request('GET', '/formats/inconnu');

        self::assertResponseStatusCodeSame(404);
    }
}
