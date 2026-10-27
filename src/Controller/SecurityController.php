<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'login', methods: ['GET'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_events');
        }

        return $this->render('security/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/admin', name: 'admin', methods: ['GET'])]
    public function admin(): RedirectResponse
    {
        return $this->redirectToRoute('admin_events');
    }

    #[Route('/connexion/google', name: 'connect_google', methods: ['GET'])]
    public function connectGoogle(ClientRegistry $clients): RedirectResponse
    {
        return $clients->getClient('google')->redirect(['openid', 'email'], ['prompt' => 'select_account']);
    }

    #[Route('/connexion/google/retour', name: 'connect_google_check', methods: ['GET'])]
    public function connectGoogleCheck(): never
    {
        throw new \LogicException('Handled by GoogleAuthenticator.');
    }

    #[Route('/deconnexion', name: 'logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Handled by the logout listener.');
    }
}
