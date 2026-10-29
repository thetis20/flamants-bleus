<?php

namespace App\Controller\Admin;

use App\Agenda;
use App\Form\VenueType;
use App\Model\Venue;
use App\Repository\VenueRepositoryInterface;
use App\Storage\LogoStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/lieux')]
final class VenueController extends AbstractController
{
    use RedirectsBack;

    public function __construct(
        private VenueRepositoryInterface $venues,
        private LogoStorage $logos,
    ) {
    }

    #[Route('', name: 'admin_venues', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('admin/venue/list.html.twig', [
            'venues' => $this->venues->all(),
        ]);
    }

    #[Route('/nouveau', name: 'admin_venue_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->edit($request, new Venue(), 'Nouveau lieu', false);
    }

    #[Route('/{id}', name: 'admin_venue_edit', methods: ['GET', 'POST'])]
    public function update(Request $request, string $id): Response
    {
        return $this->edit($request, $this->find($id), 'Modifier le lieu', true);
    }

    #[Route('/{id}/supprimer', name: 'admin_venue_delete', methods: ['POST'])]
    public function delete(Request $request, string $id, Agenda $agenda): Response
    {
        $venue = $this->find($id);
        if (!$this->isCsrfTokenValid('delete-venue-'.$id, $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('admin_venues');
        }

        if ([] !== $agenda->upcoming(null, $venue)) {
            $this->addFlash('error', \sprintf('« %s » a encore des dates à venir : supprimez-les ou changez leur lieu d\'abord.', $venue->name));

            return $this->redirectToRoute('admin_venues');
        }

        $this->venues->delete($venue->id);
        $this->logos->remove($venue->logoPath);
        $this->addFlash('success', \sprintf('« %s » supprimé.', $venue->name));

        return $this->redirectToRoute('admin_venues');
    }

    private function edit(Request $request, Venue $venue, string $heading, bool $isEdit): Response
    {
        $form = $this->createForm(VenueType::class, $venue);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $sameSlug = $this->venues->findBySlug((string) $venue->slug);
            if (null !== $sameSlug && $sameSlug->id !== $venue->id) {
                $form->get('slug')->addError(new FormError('Un autre lieu utilise déjà cette adresse.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $logo */
            $logo = $form->get('logoFile')->getData();
            if (null !== $logo) {
                $previous = $venue->logoPath;
                $venue->logoPath = $this->logos->store($logo, 'lieu-'.$venue->slug);
                $this->logos->remove($previous);
            }

            $this->venues->save($venue);
            $this->addFlash('success', \sprintf('« %s » enregistré.', $venue->name));

            // The slug may have changed, so a return to the old public page could 404.
            return $isEdit && str_starts_with($request->query->getString('retour'), '/lieux/')
                ? $this->redirectToRoute('venue_show', ['slug' => $venue->slug])
                : $this->redirectBackOr($request, 'admin_venues');
        }

        return $this->render('admin/venue/edit.html.twig', [
            'form' => $form,
            'venue' => $venue,
            'heading' => $heading,
            'is_edit' => $isEdit,
            'cancel_url' => $this->backPath($request) ?? $this->generateUrl('admin_venues'),
        ]);
    }

    private function find(string $id): Venue
    {
        return $this->venues->find($id) ?? throw $this->createNotFoundException();
    }
}
