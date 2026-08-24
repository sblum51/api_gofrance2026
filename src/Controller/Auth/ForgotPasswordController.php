<?php

namespace App\Controller\Auth;

use App\Dto\ForgotPasswordRequest;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class ForgotPasswordController
{
    use ValidatesRequestBody;

    private const string GENERIC_MESSAGE = "Si un compte existe avec cet email, un lien de réinitialisation vient d'être envoyé.";

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly MailerInterface $mailer,
        private readonly string $managerAppUrl,
        private readonly string $mailerFromAddress,
    ) {
    }

    #[Route('/auth/forgot-password', name: 'auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request, SerializerInterface $serializer, ValidatorInterface $validator): JsonResponse
    {
        $dto = $this->parseAndValidate($request, ForgotPasswordRequest::class, $serializer, $validator);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $user = $this->userRepository->findOneByEmail($dto->email);

        // Toujours la même réponse : on n'indique jamais si l'email existe ou non.
        if (null === $user) {
            return new JsonResponse(['message' => self::GENERIC_MESSAGE]);
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            return new JsonResponse(['message' => self::GENERIC_MESSAGE]);
        }

        $resetUrl = sprintf('%s/reset-password/%s', rtrim($this->managerAppUrl, '/'), $resetToken->getToken());

        $email = (new Email())
            ->from($this->mailerFromAddress)
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->text("Cliquez sur ce lien pour choisir un nouveau mot de passe : {$resetUrl}")
            ->html(sprintf('<p>Cliquez sur ce lien pour choisir un nouveau mot de passe : <a href="%1$s">%1$s</a></p>', $resetUrl));

        $this->mailer->send($email);

        return new JsonResponse(['message' => self::GENERIC_MESSAGE]);
    }
}
