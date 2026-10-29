<?php

namespace App\Tests;

use App\Security\MemberUser;

final class AdminTest extends WebTestCase
{
    public function testAnonymousVisitorIsSentToLogin(): void
    {
        $this->client->request('GET', '/admin/dates');

        self::assertResponseRedirects('/connexion');
    }

    public function testRemovedMemberLosesAccess(): void
    {
        $this->client->loginUser(new MemberUser('ancien@gmail.com'));

        $this->client->request('GET', '/admin/dates');

        self::assertResponseRedirects('/connexion');
    }

    public function testMemberCreatesAnEvent(): void
    {
        $format = $this->givenFormat();
        $venue = $this->givenVenue();
        $this->loginAsMember();

        $this->client->request('GET', '/admin/dates/nouvelle');
        $this->client->submitForm('Enregistrer', [
            'event[title]' => 'Cabaret de Noël',
            'event[startsAt]' => '2026-12-18T20:00',
            'event[formatId]' => $format->id,
            'event[venueId]' => $venue->id,
            'event[price]' => '8 €',
            'event[bookingUrl]' => 'https://billetterie.example/noel',
        ]);

        self::assertResponseRedirects('/admin/dates');
        $events = $this->events()->upcoming(new \DateTimeImmutable(self::NOW));
        self::assertCount(1, $events);
        self::assertSame('Cabaret de Noël', $events[0]->title);
        self::assertSame($venue->id, $events[0]->venueId);
        self::assertSame('2026-12-18 20:00', $events[0]->startsAt->format('Y-m-d H:i'));
        self::assertSame('https://billetterie.example/noel', $events[0]->bookingUrl);
    }

    public function testMemberEditsAnEventFromTheList(): void
    {
        $event = $this->givenEvent($this->givenFormat(), '2026-11-20 20:00', venue: $this->givenVenue());
        $digitalVillage = $this->givenVenue('Digital Village', 'digital-village');
        $this->loginAsMember();

        $crawler = $this->client->request('GET', '/admin/dates');
        $this->client->click($crawler->selectLink('Modifier')->link());
        $this->client->submitForm('Enregistrer', [
            'event[venueId]' => $digitalVillage->id,
            'event[bookingUrl]' => 'https://www.helloasso.com/associations/compagnie-les-flamants-bleus/evenements/cabaret',
        ]);

        self::assertResponseRedirects('/admin/dates');
        $saved = $this->events()->find($event->id);
        self::assertSame($digitalVillage->id, $saved->venueId);
        self::assertSame('https://www.helloasso.com/associations/compagnie-les-flamants-bleus/evenements/cabaret', $saved->bookingUrl);
        self::assertCount(1, $this->events()->upcoming(new \DateTimeImmutable(self::NOW)));
    }

    public function testEditFromThePublicSiteReturnsThere(): void
    {
        $this->givenEvent($this->givenFormat(), '2026-11-20 20:00', venue: $this->givenVenue());
        $this->loginAsMember();

        $crawler = $this->client->request('GET', '/');
        $this->client->click($crawler->selectLink('✎ Modifier')->link());
        $this->client->submitForm('Enregistrer');

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'enregistrée');
    }

    public function testExternalReturnIsIgnored(): void
    {
        $event = $this->givenEvent($this->givenFormat(), '2026-11-20 20:00', venue: $this->givenVenue());
        $this->loginAsMember();

        $this->client->request('GET', '/admin/dates/'.$event->id.'?retour=//evil.example');
        $this->client->submitForm('Enregistrer');

        self::assertResponseRedirects('/admin/dates');
    }

    public function testMemberEditsAFormatAndKeepsItsLogo(): void
    {
        $format = $this->givenFormat();
        $format->logoPath = 'logos/cabaret.png';
        $this->formats()->save($format);
        $this->loginAsMember();

        $crawler = $this->client->request('GET', '/admin/formats');
        $this->client->click($crawler->selectLink('Modifier')->link());
        $this->client->submitForm('Enregistrer', [
            'format[title]' => 'Cabaret d\'improvisation',
            'format[description]' => 'Un maître de cérémonie et vos idées.',
        ]);

        self::assertResponseRedirects('/admin/formats');
        $saved = $this->formats()->find($format->id);
        self::assertSame('Cabaret d\'improvisation', $saved->title);
        self::assertSame('Un maître de cérémonie et vos idées.', $saved->description);
        self::assertSame('logos/cabaret.png', $saved->logoPath);
    }

    public function testFormatEditedFromItsPageReturnsToItsNewAddress(): void
    {
        $format = $this->givenFormat();
        $this->loginAsMember();

        $crawler = $this->client->request('GET', '/formats/cabaret-impro');
        $this->client->click($crawler->selectLink('✎ Modifier ce format')->link());
        $this->client->submitForm('Enregistrer', ['format[slug]' => 'cabaret']);

        self::assertResponseRedirects('/formats/cabaret');
        self::assertSame('cabaret', $this->formats()->find($format->id)->slug);
    }

    public function testDuplicateKeepsTheOriginal(): void
    {
        $event = $this->givenEvent($this->givenFormat(), '2026-11-20 20:00', venue: $this->givenVenue());
        $this->loginAsMember();

        $this->client->request('GET', '/admin/dates/'.$event->id.'/dupliquer');
        $this->client->submitForm('Enregistrer', ['event[startsAt]' => '2026-12-04T20:00']);

        self::assertCount(2, $this->events()->upcoming(new \DateTimeImmutable(self::NOW)));
        self::assertSame('2026-11-20', $this->events()->find($event->id)->startsAt->format('Y-m-d'));
    }

    public function testFormatWithUpcomingEventsCannotBeDeleted(): void
    {
        $format = $this->givenFormat();
        $this->givenEvent($format, '2026-11-20 20:00');
        $this->loginAsMember();

        $crawler = $this->client->request('GET', '/admin/formats');
        $this->client->submit($crawler->selectButton('Supprimer')->form());

        self::assertResponseRedirects('/admin/formats');
        self::assertNotNull($this->formats()->find($format->id));
    }

    public function testFormatSlugMustBeUnique(): void
    {
        $this->givenFormat();
        $this->loginAsMember();

        $this->client->request('GET', '/admin/formats/nouveau');
        $this->client->submitForm('Enregistrer', [
            'format[title]' => 'Autre cabaret',
            'format[slug]' => 'cabaret-impro',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.field-errors', 'déjà cette adresse');
    }

    public function testEventSavedBeforeVenuesMustChooseOne(): void
    {
        $event = $this->givenEvent($this->givenFormat(), '2026-11-20 20:00');
        $venue = $this->givenVenue();
        $this->loginAsMember();

        $this->client->request('GET', '/admin/dates/'.$event->id);
        $this->client->submitForm('Enregistrer');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.field-errors', 'Choisissez un lieu.');

        $this->client->submitForm('Enregistrer', ['event[venueId]' => $venue->id]);
        self::assertResponseRedirects('/admin/dates');
        self::assertSame($venue->id, $this->events()->find($event->id)->venueId);
    }

    public function testMemberCreatesAVenueShownOnTheHomePage(): void
    {
        $this->loginAsMember();

        $this->client->request('GET', '/admin/lieux/nouveau');
        $this->client->submitForm('Enregistrer', [
            'venue[name]' => 'Blue Monkeys',
            'venue[slug]' => 'blue-monkeys',
            'venue[address]' => "12 rue de la Roë\n49100 Angers",
            'venue[websiteUrl]' => 'https://bluemonkeys.example',
            'venue[onHome]' => true,
        ]);

        self::assertResponseRedirects('/admin/lieux');
        $saved = $this->venues()->findBySlug('blue-monkeys');
        self::assertSame("12 rue de la Roë\n49100 Angers", $saved->address);
        self::assertSame('https://bluemonkeys.example', $saved->websiteUrl);
        self::assertTrue($saved->onHome);
    }

    public function testMemberEditsAVenueAndKeepsItsLogo(): void
    {
        $venue = $this->givenVenue(onHome: true);
        $venue->logoPath = 'logos/lieu-blue-monkeys.png';
        $this->venues()->save($venue);
        $this->loginAsMember();

        $crawler = $this->client->request('GET', '/admin/lieux');
        $this->client->click($crawler->selectLink('Modifier')->link());
        $form = $this->client->getCrawler()->selectButton('Enregistrer')->form();
        $form['venue[onHome]']->untick();
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/lieux');
        $saved = $this->venues()->find($venue->id);
        self::assertFalse($saved->onHome);
        self::assertSame('logos/lieu-blue-monkeys.png', $saved->logoPath);
    }

    public function testVenueSlugMustBeUnique(): void
    {
        $this->givenVenue();
        $this->loginAsMember();

        $this->client->request('GET', '/admin/lieux/nouveau');
        $this->client->submitForm('Enregistrer', [
            'venue[name]' => 'Autre bar',
            'venue[slug]' => 'blue-monkeys',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.field-errors', 'déjà cette adresse');
    }

    public function testVenueWithUpcomingEventsCannotBeDeleted(): void
    {
        $venue = $this->givenVenue();
        $this->givenEvent($this->givenFormat(), '2026-11-20 20:00', venue: $venue);
        $this->loginAsMember();

        $crawler = $this->client->request('GET', '/admin/lieux');
        $this->client->submit($crawler->selectButton('Supprimer')->form());

        self::assertResponseRedirects('/admin/lieux');
        self::assertNotNull($this->venues()->find($venue->id));
    }

    public function testMemberAddsAndRemovesAnotherMember(): void
    {
        $this->loginAsMember('alice@gmail.com');

        $this->client->request('GET', '/admin/membres');
        $this->client->submitForm('Ajouter', ['member[email]' => 'Bob@Gmail.com']);
        self::assertTrue($this->members()->has('bob@gmail.com'));

        $crawler = $this->client->request('GET', '/admin/membres');
        $this->client->submit($crawler->selectButton('Supprimer')->form());

        self::assertFalse($this->members()->has('bob@gmail.com'));
        self::assertTrue($this->members()->has('alice@gmail.com'));
    }

    public function testMemberCannotRemoveThemself(): void
    {
        $this->loginAsMember('alice@gmail.com');
        $this->client->request('GET', '/admin/membres');
        $token = $this->csrfToken('delete-member-alice@gmail.com');

        $this->client->request('POST', '/admin/membres/supprimer', ['email' => 'alice@gmail.com', '_token' => $token]);

        self::assertResponseRedirects('/admin/membres');
        self::assertTrue($this->members()->has('alice@gmail.com'));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'vous retirer vous-même');
    }
}
