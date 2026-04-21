<?php

declare(strict_types=1);

namespace App\Controller;

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
}
