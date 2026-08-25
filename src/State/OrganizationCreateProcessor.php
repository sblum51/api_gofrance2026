<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OrganizationCreateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Organization) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Un utilisateur authentifié est requis.');
        }

        // Un même compte peut désormais gérer plusieurs territoires : plus de
        // refus si une organisation existe déjà. Seul l'identifiant reste
        // unique, la contrainte d'entité s'en charge.
        $membership = (new Membership())
            ->setUser($user)
            ->setOrganization($data);

        $data->getMemberships()->add($membership);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
