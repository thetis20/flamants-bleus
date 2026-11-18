<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Turns the free-text address and price typed in the admin into the structured
 * values search engines expect for event listings.
 */
final class SchemaOrgExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('postal_address', $this->postalAddress(...)),
            new TwigFilter('offer_price', $this->offerPrice(...)),
        ];
    }

    /**
     * "94 Rue Rabelais, 49000 Angers" → schema.org PostalAddress, or null without a French postcode.
     *
     * @return array<string, string>|null
     */
    public function postalAddress(?string $address): ?array
    {
        $address = trim(preg_replace('/\s*[\r\n]+\s*/', ', ', (string) $address));
        if (!preg_match('/^(?<street>.*?)[\s,]*\b(?<postcode>\d{5})\s+(?<city>[^,]+)/u', $address, $match)) {
            return null;
        }

        return array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => trim($match['street'], " ,"),
            'postalCode' => $match['postcode'],
            'addressLocality' => trim($match['city']),
            'addressCountry' => 'FR',
        ], static fn (string $value) => '' !== $value);
    }

    /** Lowest amount in euros ("8 €", "5 € réduit / 8 € plein", "gratuit"), or null when there is none ("Prix libre"). */
    public function offerPrice(?string $price): ?string
    {
        $price = (string) $price;
        if (preg_match_all('/(\d+(?:[.,]\d{1,2})?)\s*(?:€|euros?\b|eur\b)/iu', $price, $matches)) {
            $amounts = array_map(static fn (string $amount) => (float) str_replace(',', '.', $amount), $matches[1]);

            return rtrim(rtrim(number_format(min($amounts), 2, '.', ''), '0'), '.');
        }

        return preg_match('/\bgratuit/iu', $price) ? '0' : null;
    }
}
