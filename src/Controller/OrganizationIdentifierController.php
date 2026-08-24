<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Repository\OrganizationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class OrganizationIdentifierController
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/organizations/check-identifier/{identifier}', name: 'organization_check_identifier', methods: ['GET'])]
    public function __invoke(string $identifier): JsonResponse
    {
        $violations = $this->validator->validatePropertyValue(Organization::class, 'identifier', $identifier);

        if (count($violations) > 0) {
            return new JsonResponse(['available' => false, 'message' => $violations[0]->getMessage()]);
        }

        $available = null === $this->organizationRepository->findOneByIdentifier($identifier);

        return new JsonResponse(['available' => $available]);
    }
}
