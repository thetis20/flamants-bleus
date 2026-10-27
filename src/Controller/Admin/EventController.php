<?php

namespace App\Controller\Admin;

use App\Form\EventType;
use App\Model\Event;
use App\Repository\EventRepositoryInterface;
use App\Repository\FormatRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/dates')]
final class EventController extends AbstractController
{
    use RedirectsBack;

    public function __construct(
        private EventRepositoryInterface $events,
        private FormatRepositoryInterface $formats,
    ) {
    }

    #[Route('', name: 'admin_events', methods: ['GET'])]
    public function list(ClockInterface $clock): Response
    {
        $formats = [];
        foreach ($this->formats->all() as $format) {
            $formats[$format->id] = $format;
        }

        $now = $clock->now();

        return $this->render('admin/event/list.html.twig', [
            'upcoming' => $this->events->upcoming($now),
            'past' => $this->events->past($now, 30),
            'formats' => $formats,
        ]);
    }

    #[Route('/nouvelle', name: 'admin_event_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->edit($request, new Event(), 'Nouvelle date', false);
    }

    #[Route('/{id}', name: 'admin_event_edit', methods: ['GET', 'POST'])]
    public function update(Request $request, string $id): Response
    {
        return $this->edit($request, $this->find($id), 'Modifier la date', true);
    }

    #[Route('/{id}/dupliquer', name: 'admin_event_duplicate', methods: ['GET', 'POST'])]
    public function duplicate(Request $request, string $id): Response
    {
        return $this->edit($request, $this->find($id)->duplicate(), 'Dupliquer la date', false);
    }

    #[Route('/{id}/supprimer', name: 'admin_event_delete', methods: ['POST'])]
    public function delete(Request $request, string $id): Response
    {
        $event = $this->find($id);
        if ($this->isCsrfTokenValid('delete-event-'.$id, $request->getPayload()->getString('_token'))) {
            $this->events->delete($event->id);
            $this->addFlash('success', \sprintf('« %s » supprimée.', $event->title));
        }

        return $this->redirectToRoute('admin_events');
    }

    private function edit(Request $request, Event $event, string $heading, bool $isEdit): Response
    {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->events->save($event);
            $this->addFlash('success', \sprintf('« %s » enregistrée.', $event->title));

            return $this->redirectBackOr($request, 'admin_events');
        }

        return $this->render('admin/event/edit.html.twig', [
            'form' => $form,
            'event' => $event,
            'heading' => $heading,
            'is_edit' => $isEdit,
            'cancel_url' => $this->backPath($request) ?? $this->generateUrl('admin_events'),
            'has_formats' => [] !== $this->formats->all(),
        ]);
    }

    private function find(string $id): Event
    {
        return $this->events->find($id) ?? throw $this->createNotFoundException();
    }
}
