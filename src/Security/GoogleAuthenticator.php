<?php

namespace App\Security;

use App\Model\Member;
use App\Repository\MemberRepositoryInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class GoogleAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private ClientRegistry $clients,
        private MemberRepositoryInterface $members,
        private UrlGeneratorInterface $urls,
        #[Autowire(env: 'ADMIN_BOOTSTRAP_EMAIL')]
        private string $bootstrapEmail,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'connect_google_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clients->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(new UserBadge($accessToken->getToken(), function () use ($client, $accessToken) {
            /** @var GoogleUser $googleUser */
            $googleUser = $client->fetchUserFromToken($accessToken);
            $email = mb_strtolower((string) $googleUser->getEmail());

            if ('' === $email || true !== $googleUser->getEmailVerified()) {
                throw new CustomUserMessageAuthenticationException('Adresse Google non vérifiée.');
            }

            // First login of the bootstrap member seeds the list, so the site is never locked.
            if ('' !== $this->bootstrapEmail && $email === mb_strtolower(trim($this->bootstrapEmail)) && !$this->members->has($email)) {
                $this->members->add(new Member($email, 'bootstrap', new \DateTimeImmutable()));
            }

            if (!$this->members->has($email)) {
                throw new CustomUserMessageAuthenticationException(\sprintf('%s n\'est pas dans la liste des membres. Demandez à un membre de vous ajouter.', $email));
            }

            return new MemberUser($email);
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->getPreviousUrl($request, $firewallName) ?? $this->urls->generate('admin_events'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urls->generate('login'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->saveAuthenticationErrorToSession($request, $exception);

        return new RedirectResponse($this->urls->generate('login'));
    }
}
