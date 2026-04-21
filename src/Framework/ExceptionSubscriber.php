<?php

declare(strict_types=1);

namespace App\Framework;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;
use Tlab\IpmaApi\Exception\IpmaApiException;
use Twig\Environment;

/**
 * Converts uncaught exceptions into branded HTML error responses.
 *
 * - `HttpExceptionInterface` (e.g. 404, 405) → `error/{status}.html.twig`
 *   with a fallback to `error/error.html.twig`.
 * - `IpmaApiException` → 502 Bad Gateway with a friendly upstream-failure page.
 * - Anything else → 500 Internal Server Error. In debug mode the subscriber
 *   steps aside so Symfony's built-in exception renderer can show the trace.
 */
final class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly bool $debug = false,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority below Symfony's default exception handler so routing
        // exceptions (NotFound, MethodNotAllowed) are already wrapped into
        // HttpExceptions by the time we run.
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -8],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // In debug mode, let Symfony render a detailed trace for non-HTTP errors.
        if ($this->debug && !$exception instanceof HttpExceptionInterface) {
            return;
        }

        [$status, $template, $context] = $this->resolve($exception);

        try {
            $html = $this->twig->render($template, $context + [
                'status' => $status,
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            $fallback = dirname(__DIR__, 2) . '/templates/error/500.html';
            $html = is_readable($fallback)
                ? (string) file_get_contents($fallback)
                : '<h1>500 Internal Server Error</h1>';
        }

        $event->setResponse(new Response($html, $status));
    }

    /**
     * @return array{0:int,1:string,2:array<string,mixed>}
     */
    private function resolve(Throwable $exception): array
    {
        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $template = sprintf('error/%d.html.twig', $status);
            if (!$this->twig->getLoader()->exists($template)) {
                $template = 'error/error.html.twig';
            }

            return [$status, $template, []];
        }

        if ($exception instanceof IpmaApiException) {
            return [
                Response::HTTP_BAD_GATEWAY,
                'error/upstream.html.twig',
                ['upstream' => 'IPMA'],
            ];
        }

        return [Response::HTTP_INTERNAL_SERVER_ERROR, 'error/500.html.twig', []];
    }
}
