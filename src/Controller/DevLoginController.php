<?php

namespace App\Controller;

use App\Model\Member;
use App\Repository\MemberRepositoryInterface;
use App\Security\GoogleAuthenticator;
use App\Security\MemberUser;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Signs in as any email without Google, so the members area can be used before an OAuth client exists.
 * Neither the route nor the service exist outside the dev environment.
 */
#[When('dev')]
final class DevLoginController extends AbstractController
{
    #[Route('/connexion/dev', name: 'dev_login', methods: ['GET', 'POST'], env: 'dev')]
    public function __invoke(Request $request, MemberRepositoryInterface $members, Security $security, ClockInterface $clock): Response
    {
        $email = mb_strtolower(trim($request->getPayload()->getString('email')));

        if (!$request->isMethod('POST')) {
            return $this->render('security/dev_login.html.twig', ['members' => $members->all()]);
        }

        if (!$this->isCsrfTokenValid('dev-login', $request->getPayload()->getString('_token')) || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->redirectToRoute('dev_login');
        }

        if (!$members->has($email)) {
            $members->add(new Member($email, 'dev', $clock->now()));
        }

        return $security->login(new MemberUser($email), GoogleAuthenticator::class)
            ?? $this->redirectToRoute('admin_events');
    }
}
