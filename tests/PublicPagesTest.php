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

    public function testEditLinkIsShownToMembersOnly(): void
    {
        $this->givenEvent($this->givenFormat(), '2026-11-20 20:00');

        $this->client->request('GET', '/');
        self::assertSelectorNotExists('.member-edit');

        $this->loginAsMember();
        $this->client->request('GET', '/');
        self::assertSelectorExists('.member-edit');
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

    public function testEventPageShowsTheDateAndRecallsItsFormat(): void
    {
        $cabaret = $this->givenFormat();
        $cabaret->description = 'Des scènes improvisées sur vos thèmes.';
        $this->formats()->save($cabaret);
        $event = $this->givenEvent($cabaret, '2026-11-06 20:30', 'Cabaret de novembre');

        $this->client->request('GET', '/dates/'.$event->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Cabaret de novembre');
        self::assertSelectorTextContains('.event-meta', 'Blue Monkey');
        self::assertSelectorTextContains('.event-meta', '20h30');
        self::assertSelectorTextContains('.format-detail h2', 'Cabaret d\'impro');
        self::assertSelectorTextContains('.format-detail', 'Des scènes improvisées sur vos thèmes.');
        self::assertSelectorExists('.format-detail a[href="/formats/cabaret-impro"]');
    }

    public function testPastEventPageSaysSoAndOffersNoBooking(): void
    {
        $event = $this->givenEvent($this->givenFormat(), '2026-10-23 20:00');
        $event->bookingUrl = 'https://billetterie.example/cabaret';
        $this->events()->save($event);

        $this->client->request('GET', '/dates/'.$event->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.booking-soon', 'Cette date est passée');
        self::assertSelectorNotExists('.event-card a.button');
    }

    public function testUnknownEventIsNotFound(): void
    {
        $this->client->request('GET', '/dates/inconnu');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAgendaLinksEachEventToItsPage(): void
    {
        $event = $this->givenEvent($this->givenFormat(), '2026-11-20 20:00');

        $this->client->request('GET', '/');

        self::assertSelectorExists('.event-title a[href="/dates/'.$event->id.'"]');
    }

    public function testHomeListsTheFormats(): void
    {
        $this->givenFormat();
        $this->givenFormat('Doublage Improvisé', 'doublage-improvise');

        $crawler = $this->client->request('GET', '/');

        self::assertSame(
            ['/formats/cabaret-impro', '/formats/doublage-improvise'],
            $crawler->filter('#formats .format-tile')->each(fn ($node) => $node->attr('href')),
        );
    }

    public function testEventCardLinksToItsVenue(): void
    {
        $this->givenEvent($this->givenFormat(), '2026-11-20 20:00', venue: $this->givenVenue());

        $this->client->request('GET', '/');

        self::assertSelectorTextContains('.event-meta a[href="/lieux/blue-monkeys"]', 'Blue Monkeys');
    }

    public function testHomeShowsOnlyVenuesTickedForIt(): void
    {
        $this->givenVenue('Blue Monkeys', 'blue-monkeys', onHome: true);
        $this->givenVenue('Salle des fêtes', 'salle-des-fetes');

        $crawler = $this->client->request('GET', '/');

        self::assertSame(['/lieux/blue-monkeys'], $crawler->filter('.partners a')->each(fn ($node) => $node->attr('href')));
    }

    public function testHomeHidesVenuesBlockWhenNoneIsTicked(): void
    {
        $this->givenVenue();

        $this->client->request('GET', '/');

        self::assertSelectorNotExists('.partners');
    }

    public function testVenuePageShowsItsAddressWebsiteAndOnlyItsEvents(): void
    {
        $venue = $this->givenVenue();
        $venue->address = "12 rue de la Roë\n49100 Angers";
        $venue->websiteUrl = 'https://bluemonkeys.example';
        $this->venues()->save($venue);
        $format = $this->givenFormat();
        $this->givenEvent($format, '2026-11-20 20:00', 'Cabaret au Blue', $venue);
        $this->givenEvent($format, '2026-11-06 20:00', 'Cabaret ailleurs', $this->givenVenue('Digital Village', 'digital-village'));

        $crawler = $this->client->request('GET', '/lieux/blue-monkeys');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Blue Monkeys');
        self::assertSelectorTextContains('.venue-address', '49100 Angers');
        self::assertSelectorExists('.venue-address a[href^="https://www.google.com/maps/search/"]');
        self::assertSelectorExists('a.venue-website[href="https://bluemonkeys.example"]');
        self::assertSame(['Cabaret au Blue'], $crawler->filter('.event-title')->each(fn ($node) => $node->text()));
    }

    public function testEventPageRecallsItsVenueAddress(): void
    {
        $venue = $this->givenVenue();
        $venue->address = '12 rue de la Roë, 49100 Angers';
        $this->venues()->save($venue);
        $event = $this->givenEvent($this->givenFormat(), '2026-11-20 20:00', venue: $venue);

        $this->client->request('GET', '/dates/'.$event->id);

        self::assertSelectorTextContains('.venue-detail .venue-address', '12 rue de la Roë');
    }

    public function testUnknownVenueIsNotFound(): void
    {
        $this->client->request('GET', '/lieux/inconnu');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownFormatIsNotFound(): void
    {
        $this->client->request('GET', '/formats/inconnu');

        self::assertResponseStatusCodeSame(404);
    }
}
