<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ParcoursRepository;
use App\State\ParcoursCollectionProvider;
use App\State\ParcoursWriteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ParcoursRepository::class)]
#[ORM\Table(name: 'parcours')]
#[ORM\UniqueConstraint(name: 'parcours_organization_slug_unique', columns: ['organization_id', 'slug'])]
#[Vich\Uploadable]
#[ApiResource(
    operations: [
        new GetCollection(provider: ParcoursCollectionProvider::class),
        new Get(security: "is_granted('PARCOURS_VIEW', object)"),
        new Post(processor: ParcoursWriteProcessor::class),
        new Patch(security: "is_granted('PARCOURS_EDIT', object)", processor: ParcoursWriteProcessor::class),
        new Delete(security: "is_granted('PARCOURS_EDIT', object)"),
        // Achat à l'unité. Sans processor : celui du Patch courant reconstruit
        // les points à partir du corps soumis et les effacerait tous ici.
        new Patch(
            uriTemplate: '/parcours/{id}/licence',
            security: "is_granted('ROLE_ADMIN')",
            denormalizationContext: ['groups' => ['parcours:admin']],
            normalizationContext: ['groups' => ['parcours:admin:read']],
        ),
    ],
    security: 'is_granted(\'ROLE_USER\')',
    normalizationContext: ['groups' => ['parcours:read']],
    denormalizationContext: ['groups' => ['parcours:write']],
)]
class Parcours
{
    public const array TRANSPORT_MODES = ['cyclable', 'pedestre', 'motorise'];

    public const string ROUTE_TYPE_ORDERED = 'ordered';
    public const string ROUTE_TYPE_FREE = 'free';
    public const array ROUTE_TYPES = [self::ROUTE_TYPE_ORDERED, self::ROUTE_TYPE_FREE];

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['parcours:read', 'parcours:admin:read'])]
    private string $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['parcours:read', 'parcours:write', 'parcours:admin:read'])]
    private string $name;

    /** Généré à partir du nom par ParcoursWriteProcessor, unique au sein de l'organisation. */
    #[ORM\Column(length: 255)]
    #[Groups(['parcours:read', 'parcours:admin:read'])]
    private string $slug = '';

    /** @var array<string, string> description / remarques, multilingue, facultative */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['parcours:read', 'parcours:write'])]
    private array $description = [];

    /**
     * Informations pratiques d'accessibilité, ex. {"label": "Pavés",
     * "variant": "warn"}. `variant` : 'ok' | 'warn' | 'neutral'.
     *
     * @var list<array{label: string, variant: string}>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['parcours:read', 'parcours:write'])]
    private array $accessibility = [];

    #[Vich\UploadableField(mapping: 'parcours_photo', fileNameProperty: 'photoName', size: 'photoSize')]
    private ?File $photoFile = null;

    #[ORM\Column(nullable: true)]
    private ?string $photoName = null;

    #[ORM\Column(nullable: true)]
    private ?int $photoSize = null;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['parcours:read', 'parcours:write'])]
    private float $distanceKm;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['parcours:read', 'parcours:write'])]
    private int $durationMinutes;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Count(min: 1, minMessage: 'Sélectionnez au moins un mode de déplacement.')]
    #[Assert\Choice(choices: self::TRANSPORT_MODES, multiple: true)]
    #[Groups(['parcours:read', 'parcours:write'])]
    private array $transportModes = [];

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: self::ROUTE_TYPES)]
    #[Groups(['parcours:read', 'parcours:write'])]
    private string $routeType = self::ROUTE_TYPE_ORDERED;

    /**
     * Information de départ (parking, lieu de rendez-vous...), distincte des
     * points du tracé : facultative, un seul bloc par parcours.
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['parcours:read', 'parcours:write'])]
    private array $departDescription = [];

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    #[Groups(['parcours:read', 'parcours:write'])]
    private ?float $departLatitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    #[Groups(['parcours:read', 'parcours:write'])]
    private ?float $departLongitude = null;

    #[ORM\ManyToOne(inversedBy: 'parcours')]
    #[ORM\JoinColumn(nullable: false)]
    private Organization $organization;

    /**
     * Parcours acheté à l'unité : il est publié sans bandeau de démonstration
     * même si l'organisation n'a pas d'abonnement illimité. État commercial,
     * donc réservé à l'administration.
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['parcours:read', 'parcours:admin:read', 'parcours:admin'])]
    private bool $licensed = false;

    /**
     * Versions audio de la description, par langue : { "fr": "/audio/..." }.
     * Générées à la demande depuis MANAGER, jamais à l'enregistrement : Polly
     * est facturé au caractère, une regénération à chaque correction de faute
     * coûterait sans rien apporter.
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['parcours:read'])]
    private array $descriptionAudio = [];

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'parcours')]
    #[ORM\JoinTable(name: 'parcours_tag')]
    private Collection $tags;

    /** @var list<string>|null raw tag names submitted by the client, consumed by ParcoursWriteProcessor */
    private ?array $submittedTagNames = null;

    /**
     * Nommée `$pointEntities` (et non `$points`) volontairement : le nom d'une
     * propriété Doctrine mappée est utilisé par l'extracteur de type de
     * PropertyInfo indépendamment des accesseurs, ce qui ferait sinon
     * interférer cette association avec la propriété sérialisée "points"
     * (tableau brut) définie plus bas via getPoints()/setPoints().
     *
     * @var Collection<int, ParcoursPoint>
     */
    #[ORM\OneToMany(mappedBy: 'parcours', targetEntity: ParcoursPoint::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $pointEntities;

    #[ORM\Column]
    #[Groups(['parcours:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['parcours:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->tags = new ArrayCollection();
        $this->pointEntities = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /** @return array<string, string> */
    public function getDescription(): array
    {
        return $this->description;
    }

    /** @param array<string, string> $description */
    public function setDescription(array $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** @return list<array{label: string, variant: string}> */
    public function getAccessibility(): array
    {
        return $this->accessibility;
    }

    /** @param list<array{label: string, variant: string}> $accessibility */
    public function setAccessibility(array $accessibility): static
    {
        $this->accessibility = $accessibility;

        return $this;
    }

    public function setPhotoFile(?File $photoFile): static
    {
        $this->photoFile = $photoFile;

        if (null !== $photoFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getPhotoFile(): ?File
    {
        return $this->photoFile;
    }

    public function getPhotoName(): ?string
    {
        return $this->photoName;
    }

    public function setPhotoName(?string $photoName): static
    {
        $this->photoName = $photoName;

        return $this;
    }

    public function getPhotoSize(): ?int
    {
        return $this->photoSize;
    }

    public function setPhotoSize(?int $photoSize): static
    {
        $this->photoSize = $photoSize;

        return $this;
    }

    /**
     * URL publique de la photo. Doit rester alignée avec le `uri_prefix` de la
     * mapping "parcours_photo" dans config/packages/vich_uploader.yaml.
     */
    #[Groups(['parcours:read'])]
    public function getPhotoUrl(): ?string
    {
        return null !== $this->photoName ? '/media/parcours/'.$this->photoName : null;
    }

    public function getDistanceKm(): float
    {
        return $this->distanceKm;
    }

    public function setDistanceKm(float $distanceKm): static
    {
        $this->distanceKm = $distanceKm;

        return $this;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    /** @return list<string> */
    public function getTransportModes(): array
    {
        return $this->transportModes;
    }

    /** @param list<string> $transportModes */
    public function setTransportModes(array $transportModes): static
    {
        $this->transportModes = array_values($transportModes);

        return $this;
    }

    public function getRouteType(): string
    {
        return $this->routeType;
    }

    public function setRouteType(string $routeType): static
    {
        $this->routeType = $routeType;

        return $this;
    }

    /** @return array<string, string> */
    public function getDepartDescription(): array
    {
        return $this->departDescription;
    }

    /** @param array<string, string> $departDescription */
    public function setDepartDescription(array $departDescription): static
    {
        $this->departDescription = $departDescription;

        return $this;
    }

    public function getDepartLatitude(): ?float
    {
        return $this->departLatitude;
    }

    public function setDepartLatitude(?float $departLatitude): static
    {
        $this->departLatitude = $departLatitude;

        return $this;
    }

    public function getDepartLongitude(): ?float
    {
        return $this->departLongitude;
    }

    public function setDepartLongitude(?float $departLongitude): static
    {
        $this->departLongitude = $departLongitude;

        return $this;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /** @return list<string> */
    #[Groups(['parcours:read'])]
    public function getTagNames(): array
    {
        return array_values(array_map(static fn (Tag $tag): string => $tag->getName(), $this->tags->toArray()));
    }

    /** @param list<string> $tagNames */
    #[Groups(['parcours:write'])]
    public function setTagNames(array $tagNames): static
    {
        $this->submittedTagNames = $tagNames;

        return $this;
    }

    /** @return list<string>|null raw tag names submitted by the client, null if the field was absent from the payload */
    public function getSubmittedTagNames(): ?array
    {
        return $this->submittedTagNames;
    }

    /** @return Collection<int, ParcoursPoint> */
    public function getPointEntities(): Collection
    {
        return $this->pointEntities;
    }

    public function attachPoint(ParcoursPoint $point): static
    {
        if (!$this->pointEntities->contains($point)) {
            $this->pointEntities->add($point);
            $point->setParcours($this);
        }

        return $this;
    }

    public function detachPoint(ParcoursPoint $point): static
    {
        $this->pointEntities->removeElement($point);

        return $this;
    }

    /**
     * Lecture seule : le tableau JSON exposé au client. L'écriture ne passe
     * volontairement pas par un setter équivalent — voir
     * `ParcoursWriteProcessor::readSubmittedPoints()`, qui lit "points"
     * directement dans le corps brut de la requête. Un couple
     * getPoints()/setPoints() (même typé `array`) amenait le Serializer à
     * tenter d'instancier des entités ParcoursPoint à la désérialisation.
     *
     * @return list<array<string, mixed>>
     */
    #[Groups(['parcours:read'])]
    public function getPoints(): array
    {
        return array_values(array_map(
            $this->pointToArray(...),
            array_filter(
                $this->pointEntities->toArray(),
                static fn (ParcoursPoint $point): bool => ParcoursPoint::KIND_ROUTE === $point->getKind(),
            ),
        ));
    }

    /**
     * "Points connexes" : services à proximité du tracé (restaurant, hôtel,
     * WC, office de tourisme...), non ordonnés, distincts des points du tracé.
     *
     * @return list<array<string, mixed>>
     */
    #[Groups(['parcours:read'])]
    public function getRelatedPoints(): array
    {
        return array_values(array_map(
            $this->pointToArray(...),
            array_filter(
                $this->pointEntities->toArray(),
                static fn (ParcoursPoint $point): bool => ParcoursPoint::KIND_ROUTE !== $point->getKind(),
            ),
        ));
    }

    /** @return array<string, mixed> */
    private function pointToArray(ParcoursPoint $point): array
    {
        return [
            'id' => $point->getId(),
            'kind' => $point->getKind(),
            'position' => $point->getPosition(),
            'latitude' => $point->getLatitude(),
            'longitude' => $point->getLongitude(),
            'name' => $point->getName(),
            'description' => $point->getDescription(),
            'datatourismeId' => $point->getDatatourismeId(),
            'imageUrl' => $point->getImageUrl(),
            'media' => $point->getMedia(),
            'links' => $point->getLinks(),
            // Versions audio par langue. Absentes tant qu'aucune génération n'a
            // été demandée : l'application publique se rabat alors sur la
            // synthèse du navigateur.
            'audio' => $point->getAudio(),
            // Fournisseur et identifiant deja extraits : l'application publique
            // n'a pas a analyser une URL, et un lien mal forme ne s'y retrouve pas.
            'video' => $point->getVideo(),
        ];
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
    public function isLicensed(): bool
    {
        return $this->licensed;
    }

    public function setLicensed(bool $licensed): static
    {
        $this->licensed = $licensed;

        return $this;
    }

    /**
     * Le bandeau de démonstration s'affiche par défaut. Il ne disparaît que si
     * l'organisation a un abonnement illimité, ou si ce parcours précis a été
     * acheté à l'unité. La règle est calculée ici plutôt que dans les
     * applications clientes : elle est commerciale, elle n'a pas à être
     * dupliquée ni contournable côté navigateur.
     */
    #[Groups(['parcours:read', 'parcours:admin:read'])]
    public function getDemoBanner(): bool
    {
        return !$this->organization->isUnlimitedPlan() && !$this->licensed;
    }
    /** @return array<string, string> */
    public function getDescriptionAudio(): array
    {
        return $this->descriptionAudio;
    }

    /** @param array<string, string> $descriptionAudio */
    public function setDescriptionAudio(array $descriptionAudio): static
    {
        $this->descriptionAudio = $descriptionAudio;

        return $this;
    }
}


