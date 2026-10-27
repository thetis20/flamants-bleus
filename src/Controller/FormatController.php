<?php

namespace App\Controller;

use App\Agenda;
use App\Repository\FormatRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FormatController extends AbstractController
{
    public function __construct(private FormatRepositoryInterface $formats)
    {
    }

    #[Route('/formats', name: 'formats', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('format/list.html.twig', [
            'formats' => $this->formats->all(),
        ]);
    }

    #[Route('/formats/{slug}', name: 'format_show', methods: ['GET'])]
    public function show(string $slug, Agenda $agenda): Response
    {
        $format = $this->formats->findBySlug($slug) ?? throw $this->createNotFoundException();

        return $this->render('format/show.html.twig', [
            'format' => $format,
            'rows' => $agenda->upcoming($format),
        ]);
    }
}
