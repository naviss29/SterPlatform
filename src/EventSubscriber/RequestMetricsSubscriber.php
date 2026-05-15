<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestMetricsSubscriber implements EventSubscriberInterface
{
    private float $startTime;

    public function __construct(private readonly LoggerInterface $logger) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onRequest', 0],
            KernelEvents::RESPONSE => ['onResponse', 0],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $this->startTime = microtime(true);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request  = $event->getRequest();
        $response = $event->getResponse();
        $status   = $response->getStatusCode();
        $duration = isset($this->startTime) ? round((microtime(true) - $this->startTime) * 1000) : null;

        $context = [
            'method'      => $request->getMethod(),
            'path'        => $request->getPathInfo(),
            'status_code' => $status,
            'duration_ms' => $duration,
        ];

        if ($status >= 500) {
            $this->logger->error('http.5xx', $context);
        } elseif ($status >= 400) {
            $this->logger->warning('http.4xx', $context);
        } else {
            $this->logger->info('http.request', $context);
        }
    }
}
