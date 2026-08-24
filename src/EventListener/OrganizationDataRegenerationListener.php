<?php

namespace App\EventListener;

use App\Entity\Organization;
use App\Entity\Parcours;
use App\Entity\ParcoursPoint;
use App\Message\GenerateOrganizationDataMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEntityListener(event: Events::postPersist, entity: Organization::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Organization::class)]
#[AsEntityListener(event: Events::postPersist, entity: Parcours::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Parcours::class)]
#[AsEntityListener(event: Events::postRemove, entity: Parcours::class)]
#[AsEntityListener(event: Events::postPersist, entity: ParcoursPoint::class)]
#[AsEntityListener(event: Events::postUpdate, entity: ParcoursPoint::class)]
#[AsEntityListener(event: Events::postRemove, entity: ParcoursPoint::class)]
final class OrganizationDataRegenerationListener
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function postPersist(Organization|Parcours|ParcoursPoint $entity): void
    {
        $this->dispatch($entity);
    }

    public function postUpdate(Organization|Parcours|ParcoursPoint $entity): void
    {
        $this->dispatch($entity);
    }

    public function postRemove(Organization|Parcours|ParcoursPoint $entity): void
    {
        $this->dispatch($entity);
    }

    private function dispatch(Organization|Parcours|ParcoursPoint $entity): void
    {
        $organizationId = match (true) {
            $entity instanceof Organization => $entity->getId(),
            $entity instanceof Parcours => $entity->getOrganization()->getId(),
            $entity instanceof ParcoursPoint => $entity->getParcours()->getOrganization()->getId(),
        };

        $this->messageBus->dispatch(new GenerateOrganizationDataMessage($organizationId));
    }
}
