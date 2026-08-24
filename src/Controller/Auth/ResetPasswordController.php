<?php

namespace App\Controller\Auth;

use App\Dto\ResetPasswordPayload;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class ResetPasswordController
{
    use ValidatesRequestBody;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/auth/reset-password/{token}', name: 'auth_reset_password', methods: ['POST'])]
    public function resetPassword(string $token, Request $request, SerializerInterface $serializer, ValidatorInterface $validator): JsonResponse
    {
        $dto = $this->parseAndValidate($request, ResetPasswordPayload::class, $serializer, $validator);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            return new JsonResponse(['message' => $e->getReason()], 400);
        }

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Jeton de réinitialisation invalide.'], 400);
        }

        $this->resetPasswordHelper->removeResetRequest($token);

        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
        $this->em->flush();

        return new JsonResponse(['message' => 'Mot de passe réinitialisé avec succès.']);
    }
}
