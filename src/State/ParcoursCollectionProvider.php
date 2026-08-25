<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class ParcoursCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        // Un compte peut gérer plusieurs organisations : MANAGER précise
        // laquelle. Le filtre porte sur les adhésions de l'utilisateur, donc un
        // identifiant d'organisation qu'il ne gère pas ne renvoie rien.
        $demandee = $this->requestStack->getCurrentRequest()?->query->get('organization');

        $parcours = [];
        foreach ($user->getMemberships() as $membership) {
            $organization = $membership->getOrganization();
            if (null !== $demandee && $organization->getId() !== $demandee) {
                continue;
            }
            foreach ($organization->getParcours() as $item) {
                $parcours[] = $item;
            }
        }

        return $parcours;
    }
}
