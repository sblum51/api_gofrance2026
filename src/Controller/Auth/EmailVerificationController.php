<?php

namespace App\Controller\Auth;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class EmailVerificationController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
    ) {
    }

    #[Route('/auth/verify-email', name: 'auth_verify_email', methods: ['GET'])]
    public function verify(Request $request): JsonResponse
    {
        $userId = $request->query->get('id');
        $user = null !== $userId ? $this->userRepository->find($userId) : null;

        if (null === $user) {
            return new JsonResponse(['message' => 'Lien de vérification invalide.'], 404);
        }

        if ($user->isVerified()) {
            return new JsonResponse(['message' => 'Ce compte est déjà vérifié.']);
        }

        try {
            $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, $user->getId(), $user->getEmail());
        } catch (VerifyEmailExceptionInterface $e) {
            return new JsonResponse(['message' => $e->getReason()], 400);
        }

        $user->setVerified(true);
        $this->em->flush();

        return new JsonResponse(['message' => 'Compte vérifié avec succès.']);
    }
}
