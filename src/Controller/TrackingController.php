<?php

namespace App\Controller;

use App\Repository\TrackingLinkRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class TrackingController
{
    // Link previews (WhatsApp, Facebook…) and crawlers would inflate the counts.
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|python|headless/i';

    #[Route('/r/{slug}', name: 'tracking_link', methods: ['GET'])]
    public function __invoke(string $slug, Request $request, TrackingLinkRepositoryInterface $links, ClockInterface $clock): RedirectResponse
    {
        $link = $links->find($slug);
        if (null === $link) {
            return new RedirectResponse('/');
        }

        if (!preg_match(self::BOT_PATTERN, (string) $request->headers->get('User-Agent'))) {
            $links->hit($slug, $clock->now());
        }

        $response = new RedirectResponse($link->target);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
