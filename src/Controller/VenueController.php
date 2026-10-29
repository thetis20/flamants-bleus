<?php

namespace App\Controller;

use App\Agenda;
use App\Repository\VenueRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VenueController extends AbstractController
{
    #[Route('/lieux/{slug}', name: 'venue_show', methods: ['GET'])]
    public function show(string $slug, VenueRepositoryInterface $venues, Agenda $agenda): Response
    {
        $venue = $venues->findBySlug($slug) ?? throw $this->createNotFoundException();

        return $this->render('venue/show.html.twig', [
            'venue' => $venue,
            'rows' => $agenda->upcoming(null, $venue),
        ]);
    }
}
