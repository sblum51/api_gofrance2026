<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Parcours;
use App\Entity\ParcoursPoint;
use App\Entity\User;
use App\Repository\ParcoursRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ParcoursWriteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
        private readonly TagRepository $tagRepository,
        private readonly ParcoursRepository $parcoursRepository,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Parcours) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $isCreation = null === ($context['previous_data'] ?? null);

        if ($isCreation) {
            $user = $this->security->getUser();
            $organization = $user instanceof User ? $user->getOrganization() : null;

            if (null === $organization) {
                throw new BadRequestHttpException("Vous devez d'abord créer votre organisation.");
            }

            $data->setOrganization($organization);
        }

        $previousData = $context['previous_data'] ?? null;
        $nameChanged = !$previousData instanceof Parcours || $previousData->getName() !== $data->getName();

        if ($isCreation || $nameChanged) {
            $data->setSlug($this->generateUniqueSlug($data));
        }

        if (null !== $data->getSubmittedTagNames()) {
            foreach ($data->getTags()->toArray() as $tag) {
                $data->removeTag($tag);
            }

            foreach ($data->getSubmittedTagNames() as $tagName) {
                $data->addTag($this->tagRepository->findOrCreateByName($tagName));
            }
        }

        $body = $this->readRequestBody($context);

        if (null !== $body && (array_key_exists('points', $body) || array_key_exists('relatedPoints', $body))) {
            foreach ($data->getPointEntities()->toArray() as $point) {
                $data->detachPoint($point);
            }

            $ordered = Parcours::ROUTE_TYPE_ORDERED === $data->getRouteType();
            $submittedPoints = is_array($body['points'] ?? null) ? $body['points'] : [];
            foreach (array_values($submittedPoints) as $index => $pointData) {
                $data->attachPoint($this->buildPoint($pointData, ParcoursPoint::KIND_ROUTE, $ordered ? $index + 1 : null));
            }

            $submittedRelatedPoints = is_array($body['relatedPoints'] ?? null) ? $body['relatedPoints'] : [];
            foreach ($submittedRelatedPoints as $pointData) {
                $kind = $pointData['kind'] ?? null;
                if (!in_array($kind, ParcoursPoint::KINDS, true) || ParcoursPoint::KIND_ROUTE === $kind) {
                    continue;
                }

                $data->attachPoint($this->buildPoint($pointData, $kind, null));
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    /** Slug unique au sein de l'organisation : en cas de collision, suffixe -2, -3... */
    private function generateUniqueSlug(Parcours $parcours): string
    {
        $base = strtolower((string) $this->slugger->slug($parcours->getName()));
        if ('' === $base) {
            $base = 'parcours';
        }

        $slug = $base;
        $suffix = 2;
        while ($this->parcoursRepository->slugExistsForOrganization($parcours->getOrganization(), $slug, $parcours->getId())) {
            $slug = sprintf('%s-%d', $base, $suffix);
            ++$suffix;
        }

        return $slug;
    }

    /** @param array<string, mixed> $pointData */
    private function buildPoint(array $pointData, string $kind, ?int $position): ParcoursPoint
    {
        $point = (new ParcoursPoint())
            ->setKind($kind)
            ->setLatitude((float) ($pointData['latitude'] ?? 0))
            ->setLongitude((float) ($pointData['longitude'] ?? 0))
            ->setName(is_array($pointData['name'] ?? null) ? $pointData['name'] : [])
            ->setDescription(is_array($pointData['description'] ?? null) ? $pointData['description'] : [])
            ->setDatatourismeId($pointData['datatourismeId'] ?? null)
            ->setImageUrl($pointData['imageUrl'] ?? null)
            ->setMedia(is_array($pointData['media'] ?? null) ? $pointData['media'] : [])
            ->setLinks(is_array($pointData['links'] ?? null) ? $pointData['links'] : [])
            ->setVideoUrl(is_string($pointData['videoUrl'] ?? null) ? trim($pointData['videoUrl']) : null)
            ->setPosition($position);

        // Un lien que getVideo() ne sait pas analyser ne produirait aucune
        // integration : l'editeur croirait sa video en place alors que rien ne
        // s'afficherait jamais. On refuse plutot que d'accepter en silence.
        // Le controle passe par l'analyseur lui-meme, pour que ce qui est
        // accepte ici et ce qui est affichable ne puissent pas diverger.
        if (null !== $point->getVideoUrl() && null === $point->getVideo()) {
            throw new BadRequestHttpException(
                'Lien video non reconnu : seuls YouTube (youtube.com, youtu.be) et Dailymotion (dailymotion.com, dai.ly) sont acceptes.'
            );
        }

        return $point;
    }

    /**
     * Lit le corps brut de la requête plutôt que de compter sur la
     * désérialisation automatique du Serializer : pour une raison liée à la
     * résolution de type d'API Platform (à creuser), une propriété "points"
     * contenant des objets imbriqués finit par être désérialisée en entités
     * ParcoursPoint avant même d'atteindre un setter, quel que soit le nom
     * choisi pour l'accesseur ou la propriété Doctrine sous-jacente. Lire le
     * JSON brut ici est fiable et évite ce comportement. Concerne "points" et
     * "relatedPoints" ; "departDescription"/"departLatitude"/"departLongitude"
     * sont des champs plats classiques, gérés normalement par le Serializer.
     *
     * @return array<string, mixed>|null
     */
    private function readRequestBody(array $context): ?array
    {
        $request = $context['request'] ?? null;
        if (!$request instanceof Request) {
            return null;
        }

        $body = json_decode($request->getContent(), true);

        return is_array($body) ? $body : null;
    }
}
