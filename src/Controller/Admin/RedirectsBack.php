<?php

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sends a member back to the public page they edited from (?retour=/formats/x), never to another site.
 */
trait RedirectsBack
{
    private function backPath(Request $request): ?string
    {
        $back = $request->query->getString('retour');

        return preg_match('#^/(?![/\\\\])#', $back) ? $back : null;
    }

    private function redirectBackOr(Request $request, string $route): RedirectResponse
    {
        $back = $this->backPath($request);

        return null !== $back ? $this->redirect($back) : $this->redirectToRoute($route);
    }
}
