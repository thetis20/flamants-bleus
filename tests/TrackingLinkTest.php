<?php

namespace App\Tests;

use App\Model\TrackingLink;

final class TrackingLinkTest extends WebTestCase
{
    private function givenLink(string $slug = 'flyer', string $target = '/'): void
    {
        $link = new TrackingLink();
        $link->label = 'QR code flyer';
        $link->slug = $slug;
        $link->target = $target;
        $this->links()->save($link);
    }

    public function testVisitIsCountedAndRedirectsToTarget(): void
    {
        $this->givenLink(target: '/formats');

        $this->client->request('GET', '/r/flyer', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone)']);
        $this->client->request('GET', '/r/flyer', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone)']);

        self::assertResponseRedirects('/formats', 302);
        self::assertSame(2, $this->links()->find('flyer')->total);
        self::assertSame(['2026-10-27' => 2], $this->links()->dailyCounts('flyer', new \DateTimeImmutable('2026-10-01')));
    }

    public function testUnknownSlugRedirectsHomeWithoutCounting(): void
    {
        $this->client->request('GET', '/r/inconnu');

        self::assertResponseRedirects('/', 302);
        self::assertSame([], $this->links()->all());
    }

    public function testLinkPreviewBotsAreNotCounted(): void
    {
        $this->givenLink();

        $this->client->request('GET', '/r/flyer', server: ['HTTP_USER_AGENT' => 'facebookexternalhit/1.1']);

        self::assertResponseRedirects('/');
        self::assertSame(0, $this->links()->find('flyer')->total);
    }

    public function testMemberCreatesLinkAndSeesItsStats(): void
    {
        $this->loginAsMember();

        $this->client->request('GET', '/admin/liens/nouveau');
        $this->client->submitForm('Enregistrer', [
            'tracking_link[label]' => 'Bio Instagram',
            'tracking_link[slug]' => 'insta',
            'tracking_link[target]' => '/',
        ]);
        self::assertResponseRedirects('/admin/liens/insta');

        $this->client->request('GET', '/r/insta');
        $crawler = $this->client->request('GET', '/admin/liens/insta');

        self::assertResponseIsSuccessful();
        self::assertSame('http://localhost/r/insta', $crawler->filter('.copy-field')->attr('value'));
        self::assertSelectorTextContains('.big-number', '1');
    }

    public function testTargetMustBeAnInternalPath(): void
    {
        $this->loginAsMember();

        $this->client->request('GET', '/admin/liens/nouveau');
        $this->client->submitForm('Enregistrer', [
            'tracking_link[label]' => 'Piège',
            'tracking_link[slug]' => 'piege',
            'tracking_link[target]' => '//evil.example',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->links()->find('piege'));
    }

    public function testQrCodeIsDownloadable(): void
    {
        $this->givenLink();
        $this->loginAsMember();

        $this->client->request('GET', '/admin/liens/flyer/qr.svg');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/svg+xml');
    }
}
