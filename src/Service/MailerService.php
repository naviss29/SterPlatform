<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class MailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $appUrl,
        private readonly string $fromEmail,
    ) {}

    public function sendVerificationEmail(User $user): void
    {
        $link = sprintf('%s/api/auth/verify?token=%s', $this->appUrl, $user->getVerificationToken());

        $html = $this->twig->render('emails/verification.html.twig', [
            'user' => $user,
            'link' => $link,
        ]);

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('Confirmez votre adresse email — SterPlatform')
            ->html($html);

        $this->mailer->send($email);
    }

    public function sendPasswordResetEmail(User $user): void
    {
        $link = sprintf('%s/reset-password?token=%s', $this->appUrl, $user->getResetToken());

        $html = $this->twig->render('emails/reset_password.html.twig', [
            'user' => $user,
            'link' => $link,
        ]);

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe — SterPlatform')
            ->html($html);

        $this->mailer->send($email);
    }
}
