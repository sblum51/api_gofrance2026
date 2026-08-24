<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Organization;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OrganizationMineProvider implements ProviderInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Organization
    {
        $user = $this->security->getUser();
        $organization = $user instanceof User ? $user->getOrganization() : null;

        if (null === $organization) {
            throw new NotFoundHttpException("Vous n'avez pas encore créé d'organisation.");
        }

        return $organization;
    }
}
