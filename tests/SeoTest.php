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

    public function testHomeCanBeCrawledAndIndexed(): void
    {
        $this->client->request('GET', '/robots.txt');
        self::assertDoesNotMatchRegularExpression('#^Disallow: /\s*$#m', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('meta[name="robots"]');
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
        $event->price = '6 €';
        $this->events()->save($event);

        $crawler = $this->client->request('GET', '/dates/'.$event->id);

        $data = json_decode($crawler->filter('.event-card script[type="application/ld+json"]')->text(), true);
        self::assertSame('TheaterEvent', $data['@type']);
        self::assertSame('2026-11-20T20:00:00+01:00', $data['startDate']);
        self::assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => '12 rue de la Roë',
            'postalCode' => '49100',
            'addressLocality' => 'Angers',
            'addressCountry' => 'FR',
        ], $data['location']['address']);
        self::assertSame('https://billetterie.example/cabaret', $data['offers']['url']);
        self::assertSame('6', $data['offers']['price']);
        self::assertSame('EUR', $data['offers']['priceCurrency']);
        self::assertSelectorTextContains('title', 'Blue Monkeys, Angers');
    }

    public function testAnAddressWithoutPostcodeIsKeptAsText(): void
    {
        $venue = $this->givenVenue();
        $venue->address = 'Place du Ralliement';
        $this->venues()->save($venue);
        $event = $this->givenEvent($this->givenFormat(), '2026-11-20 20:00', venue: $venue);
        $event->price = 'Prix libre';
        $this->events()->save($event);

        $crawler = $this->client->request('GET', '/dates/'.$event->id);

        $data = json_decode($crawler->filter('.event-card script[type="application/ld+json"]')->text(), true);
        self::assertSame('Place du Ralliement', $data['location']['address']);
        self::assertArrayNotHasKey('offers', $data);
    }

    public function testHomeTellsSearchEnginesWhereTheTroupePlays(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertSelectorTextContains('title', 'Angers');
        $description = $crawler->filter('meta[name="description"]')->attr('content');
        self::assertStringContainsString('Angers', $description);
        self::assertStringNotContainsString('prochains spectacles, formats et réservations', $description);
        $troupe = json_decode($crawler->filter('script[type="application/ld+json"]')->text(), true);
        self::assertSame('PerformingGroup', $troupe['@type']);
        self::assertSame('Angers', $troupe['areaServed']['name']);
    }

    public function testPagesDeclareSquareIconsAndAManifest(): void
    {
        $this->client->request('GET', '/');

        self::assertSelectorExists('link[rel="icon"][href="/favicon.ico"]');
        self::assertSelectorExists('link[rel="icon"][sizes="48x48"][href="/favicon-48.png"]');
        self::assertSelectorExists('link[rel="apple-touch-icon"][href="/apple-touch-icon.png"]');
        self::assertSelectorExists('link[rel="manifest"][href="/site.webmanifest"]');
        self::assertSelectorExists('meta[name="theme-color"]');

        $public = \dirname(__DIR__).'/public/';
        foreach (['favicon-48.png' => 48, 'icon-192.png' => 192, 'icon-512.png' => 512, 'apple-touch-icon.png' => 180] as $file => $size) {
            self::assertSame([$size, $size], \array_slice(getimagesize($public.$file), 0, 2), $file);
        }
        $manifest = json_decode((string) file_get_contents($public.'site.webmanifest'), true, flags: \JSON_THROW_ON_ERROR);
        foreach ($manifest['icons'] as $icon) {
            self::assertFileExists($public.ltrim($icon['src'], '/'));
        }
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
