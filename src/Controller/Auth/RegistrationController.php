<?php

namespace App\Controller\Auth;

use App\Dto\RegisterUserRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class RegistrationController
{
    use ValidatesRequestBody;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MailerInterface $mailer,
        private readonly string $mailerFromAddress,
    ) {
    }

    #[Route('/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request, SerializerInterface $serializer, ValidatorInterface $validator): JsonResponse
    {
        $dto = $this->parseAndValidate($request, RegisterUserRequest::class, $serializer, $validator);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        if (null !== $this->userRepository->findOneByEmail($dto->email)) {
            return new JsonResponse(['message' => 'Un compte existe déjà avec cet email.'], 409);
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));

        $this->em->persist($user);
        $this->em->flush();

        $signature = $this->verifyEmailHelper->generateSignature(
            'auth_verify_email',
            $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()],
        );

        $email = (new Email())
            ->from($this->mailerFromAddress)
            ->to($user->getEmail())
            ->subject('Confirmez votre adresse email')
            ->text("Cliquez sur ce lien pour confirmer votre compte : {$signature->getSignedUrl()}")
            ->html(sprintf('<p>Cliquez sur ce lien pour confirmer votre compte : <a href="%1$s">%1$s</a></p>', $signature->getSignedUrl()));

        $this->mailer->send($email);

        return new JsonResponse(['id' => $user->getId(), 'email' => $user->getEmail()], 201);
    }
}
