<?php

namespace App\Controller;

use App\Repository\EventRepositoryInterface;
use App\Repository\FormatRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EventController extends AbstractController
{
    #[Route('/dates/{id}', name: 'event_show', methods: ['GET'])]
    public function show(string $id, EventRepositoryInterface $events, FormatRepositoryInterface $formats, ClockInterface $clock): Response
    {
        $event = $events->find($id) ?? throw $this->createNotFoundException();

        return $this->render('event/show.html.twig', [
            'event' => $event,
            'format' => null !== $event->formatId ? $formats->find($event->formatId) : null,
            // Same rule as the agenda: still current on the evening it is played.
            'past' => $event->startsAt < $clock->now()->setTime(0, 0),
        ]);
    }
}
