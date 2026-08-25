<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Organization;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

final class OrganizationMineProvider implements ProviderInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    /**
     * Toutes les organisations de l'utilisateur. C'était un objet unique tant
     * qu'un compte n'en avait qu'une ; c'est une collection depuis que les
     * adhésions permettent d'en gérer plusieurs.
     *
     * @return list<Organization>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return array_values(array_map(
            static fn ($membership) => $membership->getOrganization(),
            $user->getMemberships()->toArray(),
        ));
    }
}
