<?php

namespace App\MessageHandler;

use App\Entity\Organization;
use App\Message\GenerateOrganizationDataMessage;
use App\Repository\OrganizationRepository;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateOrganizationDataHandler
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
        #[Autowire(service: 'organization_data.storage')]
        private readonly FilesystemOperator $dataStorage,
    ) {
    }

    public function __invoke(GenerateOrganizationDataMessage $message): void
    {
        $organization = $this->organizationRepository->find($message->organizationId);

        // L'organisation a pu être supprimée entre le dispatch et le traitement du message.
        if (null === $organization) {
            return;
        }

        $this->dataStorage->write(
            $organization->getIdentifier().'.json',
            json_encode($this->buildPayload($organization), \JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<string, mixed> */
    private function buildPayload(Organization $organization): array
    {
        return [
            'organization' => [
                'name' => $organization->getName(),
                'identifier' => $organization->getIdentifier(),
                'logoUrl' => $organization->getLogoUrl(),
                'coverImageUrl' => $organization->getCoverImageUrl(),
                'mainCommune' => $organization->getMainCommune(),
                'mainCommuneLat' => $organization->getMainCommuneLat(),
                'mainCommuneLng' => $organization->getMainCommuneLng(),
            ],
            'parcours' => array_values(array_map(
                static fn ($parcours) => [
                    'id' => $parcours->getId(),
                    'slug' => $parcours->getSlug(),
                    'name' => $parcours->getName(),
                    'description' => $parcours->getDescription(),
                    'accessibility' => $parcours->getAccessibility(),
                    'photoUrl' => $parcours->getPhotoUrl(),
                    // Calculé côté serveur : l'application publique se contente
                    // d'afficher le bandeau, elle n'a pas à connaître la règle
                    // commerciale ni à pouvoir la contourner.
                    'demoBanner' => $parcours->getDemoBanner(),
                    'distanceKm' => $parcours->getDistanceKm(),
                    'durationMinutes' => $parcours->getDurationMinutes(),
                    'transportModes' => $parcours->getTransportModes(),
                    'tags' => $parcours->getTagNames(),
                    'routeType' => $parcours->getRouteType(),
                    'points' => $parcours->getPoints(),
                    'relatedPoints' => $parcours->getRelatedPoints(),
                    'departDescription' => $parcours->getDepartDescription(),
                    'departLatitude' => $parcours->getDepartLatitude(),
                    'departLongitude' => $parcours->getDepartLongitude(),
                ],
                $organization->getParcours()->toArray(),
            )),
        ];
    }
}
