<?php

namespace App\Controller\Admin;

use App\Agenda;
use App\Form\FormatType;
use App\Model\Format;
use App\Repository\FormatRepositoryInterface;
use App\Storage\LogoStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/formats')]
final class FormatController extends AbstractController
{
    use RedirectsBack;

    public function __construct(
        private FormatRepositoryInterface $formats,
        private LogoStorage $logos,
    ) {
    }

    #[Route('', name: 'admin_formats', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('admin/format/list.html.twig', [
            'formats' => $this->formats->all(),
        ]);
    }

    #[Route('/nouveau', name: 'admin_format_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->edit($request, new Format(), 'Nouveau format', false);
    }

    #[Route('/{id}', name: 'admin_format_edit', methods: ['GET', 'POST'])]
    public function update(Request $request, string $id): Response
    {
        return $this->edit($request, $this->find($id), 'Modifier le format', true);
    }

    #[Route('/{id}/supprimer', name: 'admin_format_delete', methods: ['POST'])]
    public function delete(Request $request, string $id, Agenda $agenda): Response
    {
        $format = $this->find($id);
        if (!$this->isCsrfTokenValid('delete-format-'.$id, $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('admin_formats');
        }

        if ([] !== $agenda->upcoming($format)) {
            $this->addFlash('error', \sprintf('« %s » a encore des dates à venir : supprimez-les ou changez leur format d\'abord.', $format->title));

            return $this->redirectToRoute('admin_formats');
        }

        $this->formats->delete($format->id);
        $this->logos->remove($format->logoPath);
        $this->addFlash('success', \sprintf('« %s » supprimé.', $format->title));

        return $this->redirectToRoute('admin_formats');
    }

    private function edit(Request $request, Format $format, string $heading, bool $isEdit): Response
    {
        $form = $this->createForm(FormatType::class, $format);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $sameSlug = $this->formats->findBySlug((string) $format->slug);
            if (null !== $sameSlug && $sameSlug->id !== $format->id) {
                $form->get('slug')->addError(new FormError('Un autre format utilise déjà cette adresse.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $logo */
            $logo = $form->get('logoFile')->getData();
            if (null !== $logo) {
                $previous = $format->logoPath;
                $format->logoPath = $this->logos->store($logo, $format->slug);
                $this->logos->remove($previous);
            }

            $this->formats->save($format);
            $this->addFlash('success', \sprintf('« %s » enregistré.', $format->title));

            // The slug may have changed, so a return to the old public page could 404.
            return $isEdit && str_starts_with($request->query->getString('retour'), '/formats/')
                ? $this->redirectToRoute('format_show', ['slug' => $format->slug])
                : $this->redirectBackOr($request, 'admin_formats');
        }

        return $this->render('admin/format/edit.html.twig', [
            'form' => $form,
            'format' => $format,
            'heading' => $heading,
            'is_edit' => $isEdit,
            'cancel_url' => $this->backPath($request) ?? $this->generateUrl('admin_formats'),
        ]);
    }

    private function find(string $id): Format
    {
        return $this->formats->find($id) ?? throw $this->createNotFoundException();
    }
}
