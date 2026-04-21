<?php

declare(strict_types=1);

namespace App\Framework;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Twig\Environment;

/**
 * Locale resolution + persistence.
 *
 * On every request:
 *  1. If `?lang=xx` is present and supported, that locale wins and is
 *     persisted in a `lang` cookie (1 year).
 *  2. Otherwise the existing `lang` cookie is honoured if supported.
 *  3. Otherwise the default locale (PT) is used.
 *
 * The chosen locale is forwarded to:
 *  - the Translator (so `|trans` resolves the right catalogue),
 *  - the request (so any locale-aware Symfony component agrees),
 *  - the Twig environment as a global so templates can render the active
 *    locale and a switcher.
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    public const COOKIE_NAME = 'lang';
    public const QUERY_PARAM = 'lang';

    /** @var list<string> */
    public const SUPPORTED = ['pt', 'en'];
    public const DEFAULT = 'pt';

    private ?string $persistLocale = null;

    public function __construct(
        private readonly LocaleAwareInterface $translator,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run before RouterListener (priority 32) so any controller
            // pulled via the matcher already sees the right locale.
            KernelEvents::REQUEST => ['onKernelRequest', 64],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $locale = self::resolve($request->query->get(self::QUERY_PARAM), $request->cookies->get(self::COOKIE_NAME));

        $request->setLocale($locale);
        $this->translator->setLocale($locale);
        $this->twig->addGlobal('app_locale', $locale);
        $this->twig->addGlobal('app_supported_locales', self::SUPPORTED);

        // Only persist when the user actively picked one through the URL.
        $queryValue = $request->query->get(self::QUERY_PARAM);
        if (is_string($queryValue) && in_array(strtolower($queryValue), self::SUPPORTED, true)) {
            $this->persistLocale = strtolower($queryValue);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($this->persistLocale === null || !$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            Cookie::create(self::COOKIE_NAME)
                ->withValue($this->persistLocale)
                ->withExpires(strtotime('+1 year') ?: null)
                ->withPath('/')
                ->withSecure($event->getRequest()->isSecure())
                ->withHttpOnly(false)
                ->withSameSite(Cookie::SAMESITE_LAX),
        );

        $this->persistLocale = null;
    }

    /**
     * Pure resolution helper — exposed for unit testing.
     */
    public static function resolve(mixed $queryValue, mixed $cookieValue): string
    {
        foreach ([$queryValue, $cookieValue] as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            $normalized = strtolower(trim($candidate));
            if (in_array($normalized, self::SUPPORTED, true)) {
                return $normalized;
            }
        }

        return self::DEFAULT;
    }
}
