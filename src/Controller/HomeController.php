<?php

declare(strict_types=1);

namespace App\Controller;

use App\Framework\LocaleSubscriber;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Landing page controller.
 *
 * Acts as the entry-point smoke test for the scaffolded framework:
 * renders a Bootstrap-powered welcome page through Twig.
 */
final class HomeController
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function index(): Response
    {
        $html = $this->twig->render('Home/index.html.twig', [
            'title' => 'IPMA Dashboard',
        ]);

        return new Response($html);
    }

    public function redirect(Request $request): RedirectResponse
    {
        $locale = LocaleSubscriber::fromCookie($request->cookies->get(LocaleSubscriber::COOKIE_NAME));

        return new RedirectResponse('/' . $locale, 302);
    }
}
