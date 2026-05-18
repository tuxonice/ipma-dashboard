<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Glossary page listing IPMA terminology, scales, and categorical codes.
 */
final class GlossaryController
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function index(Request $request): Response
    {
        $locale = $request->getLocale();
        $html = $this->twig->render("glossary/{$locale}.html.twig", [
            'active' => 'glossary',
        ]);

        return new Response($html);
    }
}
