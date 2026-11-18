<?php

namespace App\Tests;

use App\Twig\SchemaOrgExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchemaOrgExtensionTest extends TestCase
{
    #[DataProvider('prices')]
    public function testOfferPrice(?string $typed, ?string $expected): void
    {
        self::assertSame($expected, (new SchemaOrgExtension())->offerPrice($typed));
    }

    public static function prices(): iterable
    {
        yield 'euros' => ['8 €', '8'];
        yield 'cents without space' => ['8,50€', '8.5'];
        yield 'written out' => ['10 euros', '10'];
        yield 'reduced and full price' => ['8 € plein / 5 € réduit', '5'];
        yield 'free' => ['Gratuit', '0'];
        yield 'pay what you want' => ['Prix libre', null];
        yield 'nothing typed' => [null, null];
    }

    public function testPostalAddressSplitsAFrenchAddress(): void
    {
        self::assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => '94 Rue Rabelais',
            'postalCode' => '49000',
            'addressLocality' => 'Angers',
            'addressCountry' => 'FR',
        ], (new SchemaOrgExtension())->postalAddress('94 Rue Rabelais, 49000 Angers'));
    }

    public function testPostalAddressHandlesLineBreaksAndACountry(): void
    {
        $address = (new SchemaOrgExtension())->postalAddress("6 Av. Montaigne\n49100 Angers, France");

        self::assertSame('6 Av. Montaigne', $address['streetAddress']);
        self::assertSame('Angers', $address['addressLocality']);
    }

    public function testPostalAddressNeedsAPostcode(): void
    {
        self::assertNull((new SchemaOrgExtension())->postalAddress('Place du Ralliement'));
        self::assertNull((new SchemaOrgExtension())->postalAddress(null));
    }
}
