<?php

namespace App\Controller;

use App\Agenda;
use App\Repository\FormatRepositoryInterface;
use App\Repository\VenueRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function __invoke(Agenda $agenda, FormatRepositoryInterface $formats, VenueRepositoryInterface $venues): Response
    {
        return $this->render('home.html.twig', [
            'rows' => $agenda->upcoming(),
            'formats' => $formats->all(),
            'venues' => $venues->onHome(),
        ]);
    }
}
