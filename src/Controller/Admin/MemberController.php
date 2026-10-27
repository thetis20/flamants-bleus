<?php

namespace App\Controller\Admin;

use App\Form\MemberType;
use App\Model\Member;
use App\Repository\MemberRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/membres')]
final class MemberController extends AbstractController
{
    public function __construct(private MemberRepositoryInterface $members)
    {
    }

    #[Route('', name: 'admin_members', methods: ['GET', 'POST'])]
    public function list(Request $request, ClockInterface $clock): Response
    {
        $form = $this->createForm(MemberType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member = new Member($form->get('email')->getData(), $this->getUser()->getUserIdentifier(), $clock->now());
            $this->members->add($member);
            $this->addFlash('success', \sprintf('%s peut maintenant se connecter.', $member->email));

            return $this->redirectToRoute('admin_members');
        }

        return $this->render('admin/member/list.html.twig', [
            'members' => $this->members->all(),
            'form' => $form,
        ]);
    }

    #[Route('/supprimer', name: 'admin_member_delete', methods: ['POST'])]
    public function delete(Request $request): Response
    {
        $email = $request->getPayload()->getString('email');

        if (!$this->isCsrfTokenValid('delete-member-'.$email, $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('admin_members');
        }

        if ($email === $this->getUser()->getUserIdentifier()) {
            $this->addFlash('error', 'Vous ne pouvez pas vous retirer vous-même : demandez à un autre membre.');

            return $this->redirectToRoute('admin_members');
        }

        $this->members->remove($email);
        $this->addFlash('success', \sprintf('%s retiré·e des membres.', $email));

        return $this->redirectToRoute('admin_members');
    }
}
