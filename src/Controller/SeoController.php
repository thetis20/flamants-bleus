<?php

namespace App\Controller;

use App\Agenda;
use App\Repository\FormatRepositoryInterface;
use App\Repository\VenueRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SeoController extends AbstractController
{
    #[Route('/robots.txt', name: 'robots', methods: ['GET'])]
    public function robots(): Response
    {
        $sitemap = $this->generateUrl('sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $response = new Response(<<<TXT
            User-agent: *
            Disallow: /admin
            Disallow: /connexion
            Disallow: /deconnexion
            Disallow: /r/

            Sitemap: {$sitemap}

            TXT, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);

        return $response->setPublic()->setMaxAge(86400);
    }

    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function sitemap(Agenda $agenda, FormatRepositoryInterface $formats, VenueRepositoryInterface $venues): Response
    {
        $response = $this->render('sitemap.xml.twig', [
            'rows' => $agenda->upcoming(),
            'formats' => $formats->all(),
            'venues' => $venues->all(),
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response->setPublic()->setMaxAge(3600);
    }
}
