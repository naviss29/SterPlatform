<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    public function __construct(private readonly Connection $connection) {}

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $checks = [];
        $status = 'ok';

        // Database check
        try {
            $this->connection->executeQuery('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'error';
            $status = 'error';
        }

        // Mercure check
        $mercureUrl = $_ENV['MERCURE_HUB_INTERNAL_URL'] ?? null;
        if ($mercureUrl) {
            try {
                $hubBase = preg_replace('#/\.well-known/mercure$#', '', $mercureUrl);
                $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
                $result = @file_get_contents($hubBase, false, $ctx);
                $checks['mercure'] = ($result !== false) ? 'ok' : 'error';
            } catch (\Throwable) {
                $checks['mercure'] = 'error';
            }
            if ($checks['mercure'] === 'error') {
                $status = 'error';
            }
        } else {
            $checks['mercure'] = 'unconfigured';
        }

        $httpStatus = $status === 'ok' ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return $this->json(['status' => $status, 'checks' => $checks], $httpStatus);
    }
}
