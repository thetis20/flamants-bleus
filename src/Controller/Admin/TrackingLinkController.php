<?php

namespace App\Controller\Admin;

use App\Form\TrackingLinkType;
use App\Model\TrackingLink;
use App\Repository\TrackingLinkRepositoryInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/admin/liens')]
final class TrackingLinkController extends AbstractController
{
    private const STATS_DAYS = 30;

    public function __construct(private TrackingLinkRepositoryInterface $links)
    {
    }

    #[Route('', name: 'admin_links', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('admin/link/list.html.twig', [
            'links' => $this->links->all(),
        ]);
    }

    #[Route('/nouveau', name: 'admin_link_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $link = new TrackingLink();
        $form = $this->createForm(TrackingLinkType::class, $link);
        $form->handleRequest($request);

        if ($form->isSubmitted() && null !== $link->slug && null !== $this->links->find($link->slug)) {
            $form->get('slug')->addError(new FormError('Ce code est déjà utilisé.'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $this->links->save($link);
            $this->addFlash('success', \sprintf('Lien « %s » créé.', $link->label));

            return $this->redirectToRoute('admin_link_show', ['slug' => $link->slug]);
        }

        return $this->render('admin/link/edit.html.twig', ['form' => $form, 'heading' => 'Nouveau lien suivi']);
    }

    #[Route('/{slug}', name: 'admin_link_show', methods: ['GET'])]
    public function show(string $slug, ClockInterface $clock): Response
    {
        $link = $this->find($slug);
        $today = $clock->now()->setTime(0, 0);
        $counts = $this->links->dailyCounts($slug, $today->modify(\sprintf('-%d days', self::STATS_DAYS - 1)));

        $days = [];
        for ($i = self::STATS_DAYS - 1; $i >= 0; --$i) {
            $day = $today->modify(\sprintf('-%d days', $i));
            $days[] = ['day' => $day, 'count' => $counts[$day->format('Y-m-d')] ?? 0];
        }

        return $this->render('admin/link/show.html.twig', [
            'link' => $link,
            'url' => $this->publicUrl($slug),
            'days' => $days,
            'max' => max(1, ...array_column($days, 'count')),
            'period_total' => array_sum($counts),
        ]);
    }

    #[Route('/{slug}/modifier', name: 'admin_link_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $slug): Response
    {
        $link = $this->find($slug);
        $form = $this->createForm(TrackingLinkType::class, $link, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->links->save($link);
            $this->addFlash('success', 'Lien enregistré.');

            return $this->redirectToRoute('admin_link_show', ['slug' => $slug]);
        }

        return $this->render('admin/link/edit.html.twig', ['form' => $form, 'heading' => 'Modifier le lien']);
    }

    #[Route('/{slug}/qr.{format}', name: 'admin_link_qr', requirements: ['format' => 'png|svg'], methods: ['GET'])]
    public function qr(string $slug, string $format): Response
    {
        $this->find($slug);
        $result = new Builder(
            writer: 'svg' === $format ? new SvgWriter() : new PngWriter(),
            data: $this->publicUrl($slug),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 1000,
            margin: 40,
        )->build();

        return new Response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, \sprintf('qr-%s.%s', $slug, $format)),
        ]);
    }

    #[Route('/{slug}/supprimer', name: 'admin_link_delete', methods: ['POST'])]
    public function delete(Request $request, string $slug): Response
    {
        $link = $this->find($slug);
        if ($this->isCsrfTokenValid('delete-link-'.$slug, $request->getPayload()->getString('_token'))) {
            $this->links->delete($slug);
            $this->addFlash('success', \sprintf('Lien « %s » supprimé.', $link->label));
        }

        return $this->redirectToRoute('admin_links');
    }

    private function publicUrl(string $slug): string
    {
        return $this->generateUrl('tracking_link', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function find(string $slug): TrackingLink
    {
        return $this->links->find($slug) ?? throw $this->createNotFoundException();
    }
}
