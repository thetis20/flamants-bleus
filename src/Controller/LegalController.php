<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractController
{
    #[Route('/mentions-legales', name: 'legal', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('legal.html.twig')->setPublic()->setMaxAge(86400);
    }
}
