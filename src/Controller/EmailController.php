<?php

namespace App\Controller;

use App\Repository\EmailTemplateRepository;
use App\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/email')]
class EmailController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'APP_TOKEN')]
        private readonly string $appToken,
    ) {}

    #[Route('/send', name: 'api_email_send', methods: ['POST'])]
    public function send(
        Request $request,
        EmailTemplateRepository $templateRepository,
        MailerService $mailerService,
    ): JsonResponse {
        if ($request->headers->get('X-App-Token') !== $this->appToken) {
            return $this->json(['error' => 'Token invalide.'], 401);
        }

        $data = json_decode($request->getContent(), true);

        $slug      = trim((string) ($data['template'] ?? ''));
        $to        = trim((string) ($data['to'] ?? ''));
        $variables = $data['variables'] ?? [];

        if (!$slug) {
            return $this->json(['error' => 'Le champ "template" est requis.'], 400);
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Le champ "to" doit être une adresse email valide.'], 400);
        }

        if (!is_array($variables)) {
            return $this->json(['error' => 'Le champ "variables" doit être un objet JSON.'], 400);
        }

        $template = $templateRepository->findBySlug($slug);
        if (!$template) {
            return $this->json(['error' => sprintf('Template "%s" introuvable.', $slug)], 404);
        }

        $mailerService->sendFromTemplate($template, $to, $variables);

        return $this->json(['sent' => true]);
    }
}
