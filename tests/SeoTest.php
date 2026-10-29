<?php

namespace App\Tests;

final class SeoTest extends WebTestCase
{
    public function testRobotsKeepsCrawlersOutOfTheMembersAreaAndPointsToTheSitemap(): void
    {
        $this->client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        $robots = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Disallow: /admin', $robots);
        self::assertStringContainsString('Disallow: /r/', $robots);
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $robots);
    }

    public function testSitemapListsPublicPagesAndOnlyUpcomingDates(): void
    {
        $cabaret = $this->givenFormat();
        $venue = $this->givenVenue();
        $past = $this->givenEvent($cabaret, '2026-10-23 20:00');
        $upcoming = $this->givenEvent($cabaret, '2026-11-20 20:00', venue: $venue);

        $this->client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        $sitemap = new \SimpleXMLElement((string) $this->client->getResponse()->getContent());
        $urls = array_map(fn ($url) => (string) $url->loc, iterator_to_array($sitemap->url, false));
        self::assertSame([
            'http://localhost/',
            'http://localhost/formats',
            'http://localhost/mentions-legales',
            'http://localhost/formats/cabaret-impro',
            'http://localhost/lieux/blue-monkeys',
            'http://localhost/dates/'.$upcoming->id,
        ], $urls);
        self::assertNotContains('http://localhost/dates/'.$past->id, $urls);
    }

    public function testPagesDeclareTheirCanonicalUrl(): void
    {
        $this->givenFormat();

        $this->client->request('GET', '/formats/cabaret-impro?utm_source=flyer');

        self::assertSelectorExists('link[rel="canonical"][href="http://localhost/formats/cabaret-impro"]');
    }

    public function testUpcomingDatesAreDescribedForSearchEngines(): void
    {
        $venue = $this->givenVenue();
        $venue->address = "12 rue de la Roë\n49100 Angers";
        $this->venues()->save($venue);
        $event = $this->givenEvent($this->givenFormat(), '2026-11-20 20:00', venue: $venue);
        $event->bookingUrl = 'https://billetterie.example/cabaret';
        $this->events()->save($event);

        $crawler = $this->client->request('GET', '/dates/'.$event->id);

        $data = json_decode($crawler->filter('.event-card script[type="application/ld+json"]')->text(), true);
        self::assertSame('TheaterEvent', $data['@type']);
        self::assertSame('2026-11-20T20:00:00+01:00', $data['startDate']);
        self::assertSame('12 rue de la Roë, 49100 Angers', $data['location']['address']);
        self::assertSame('https://billetterie.example/cabaret', $data['offers']['url']);
    }

    public function testPastDatesAreNeitherIndexedNorDescribedAsEvents(): void
    {
        $event = $this->givenEvent($this->givenFormat(), '2026-10-23 20:00');

        $this->client->request('GET', '/dates/'.$event->id);

        self::assertSelectorExists('meta[name="robots"][content="noindex"]');
        self::assertSelectorNotExists('script[type="application/ld+json"]');
    }

    public function testMembersAreaIsNotIndexed(): void
    {
        $this->client->request('GET', '/connexion');

        self::assertSelectorExists('meta[name="robots"][content="noindex"]');
    }
}
