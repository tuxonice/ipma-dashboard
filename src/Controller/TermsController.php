<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Terms and conditions page.
 */
final class TermsController
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function index(): Response
    {
        $html = $this->twig->render('Terms/index.html.twig');

        return new Response($html);
    }
}
