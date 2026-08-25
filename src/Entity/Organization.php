<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\OrganizationRepository;
use App\State\OrganizationCreateProcessor;
use App\State\OrganizationMineProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_organization_identifier', fields: ['identifier'])]
#[Vich\Uploadable]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/organizations/mine', name: 'mine', provider: OrganizationMineProvider::class),
        new Get(requirements: ['id' => '[0-9a-fA-F-]{36}'], security: "is_granted('ORGANIZATION_VIEW', object)"),
        new Patch(requirements: ['id' => '[0-9a-fA-F-]{36}'], security: "is_granted('ORGANIZATION_EDIT', object)"),
        new Post(processor: OrganizationCreateProcessor::class),
        // Administration : liste des organisations avec leurs parcours, et
        // bascule de l'abonnement. Séparées des opérations courantes pour que
        // le groupe d'écriture « admin » reste hors de portée du Patch normal —
        // une organisation ne doit pas pouvoir s'attribuer son abonnement.
        new GetCollection(
            uriTemplate: '/organizations',
            security: "is_granted('ROLE_ADMIN')",
            normalizationContext: ['groups' => ['organization:admin:read', 'parcours:admin:read']],
        ),
        new Patch(
            uriTemplate: '/organizations/{id}/plan',
            requirements: ['id' => '[0-9a-fA-F-]{36}'],
            security: "is_granted('ROLE_ADMIN')",
            denormalizationContext: ['groups' => ['organization:admin']],
            normalizationContext: ['groups' => ['organization:admin:read']],
        ),
    ],
    security: 'is_granted(\'ROLE_USER\')',
    normalizationContext: ['groups' => ['organization:read']],
    denormalizationContext: ['groups' => ['organization:write']],
)]
class Organization
{
    public const array RESERVED_IDENTIFIERS = ['www', 'manager', 'api', 'admin'];

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['organization:read', 'organization:admin:read'])]
    private string $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['organization:read', 'organization:write', 'organization:admin:read'])]
    private string $name;

    #[ORM\Column(length: 63)]
    #[Assert\NotBlank]
    // 4 caractères minimum : « demo » est l'adresse annoncée sur le site
    // vitrine et encodée dans son QR code. Les identifiants sensibles (www,
    // manager, api, admin) restent bloqués par la contrainte Choice ci-dessous.
    #[Assert\Length(min: 4, max: 63)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Uniquement des lettres minuscules, des chiffres et des tirets.')]
    #[Assert\Choice(choices: self::RESERVED_IDENTIFIERS, match: false, message: 'Cet identifiant est réservé.')]
    #[Groups(['organization:read', 'organization:write', 'organization:admin:read'])]
    private string $identifier;

    #[Vich\UploadableField(mapping: 'organization_logo', fileNameProperty: 'logoName', size: 'logoSize')]
    private ?File $logoFile = null;

    #[ORM\Column(nullable: true)]
    private ?string $logoName = null;

    #[ORM\Column(nullable: true)]
    private ?int $logoSize = null;

    #[Vich\UploadableField(mapping: 'organization_cover', fileNameProperty: 'coverImageName', size: 'coverImageSize')]
    private ?File $coverImageFile = null;

    #[ORM\Column(nullable: true)]
    private ?string $coverImageName = null;

    #[ORM\Column(nullable: true)]
    private ?int $coverImageSize = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['organization:read', 'organization:write'])]
    private ?string $mainCommune = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups(['organization:read', 'organization:write'])]
    private ?float $mainCommuneLat = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups(['organization:read', 'organization:write'])]
    private ?float $mainCommuneLng = null;

    /**
     * Gestionnaires de l'organisation. Remplace le propriétaire unique : un
     * office de tourisme à deux salariés n'a plus à partager un mot de passe,
     * et le départ d'une personne ne fait plus disparaître l'accès.
     *
     * @var Collection<int, Membership>
     */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: Membership::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $memberships;

    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: Parcours::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['organization:admin:read'])]
    private Collection $parcours;

    /**
     * Abonnement illimité : aucun parcours de l'organisation ne porte le
     * bandeau de démonstration. C'est un état commercial, il n'est donc
     * modifiable que par l'administration — le groupe organization:admin n'est
     * accessible qu'aux opérations réservées à ROLE_ADMIN.
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['organization:read', 'organization:admin:read', 'organization:admin'])]
    private bool $unlimitedPlan = false;

    #[ORM\Column]
    #[Groups(['organization:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['organization:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->parcours = new ArrayCollection();
        $this->memberships = new ArrayCollection();
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

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function setLogoFile(?File $logoFile): static
    {
        $this->logoFile = $logoFile;

        if (null !== $logoFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getLogoFile(): ?File
    {
        return $this->logoFile;
    }

    public function getLogoName(): ?string
    {
        return $this->logoName;
    }

    public function setLogoName(?string $logoName): static
    {
        $this->logoName = $logoName;

        return $this;
    }

    public function getLogoSize(): ?int
    {
        return $this->logoSize;
    }

    public function setLogoSize(?int $logoSize): static
    {
        $this->logoSize = $logoSize;

        return $this;
    }

    /**
     * URL publique du logo. Doit rester alignée avec le `uri_prefix` de la
     * mapping "organization_logo" dans config/packages/vich_uploader.yaml.
     */
    #[Groups(['organization:read'])]
    public function getLogoUrl(): ?string
    {
        return null !== $this->logoName ? '/media/logos/'.$this->logoName : null;
    }

    public function setCoverImageFile(?File $coverImageFile): static
    {
        $this->coverImageFile = $coverImageFile;

        if (null !== $coverImageFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getCoverImageFile(): ?File
    {
        return $this->coverImageFile;
    }

    public function getCoverImageName(): ?string
    {
        return $this->coverImageName;
    }

    public function setCoverImageName(?string $coverImageName): static
    {
        $this->coverImageName = $coverImageName;

        return $this;
    }

    public function getCoverImageSize(): ?int
    {
        return $this->coverImageSize;
    }

    public function setCoverImageSize(?int $coverImageSize): static
    {
        $this->coverImageSize = $coverImageSize;

        return $this;
    }

    /**
     * URL publique de la couverture. Doit rester alignée avec le `uri_prefix` de
     * la mapping "organization_cover" dans config/packages/vich_uploader.yaml.
     */
    #[Groups(['organization:read'])]
    public function getCoverImageUrl(): ?string
    {
        return null !== $this->coverImageName ? '/media/covers/'.$this->coverImageName : null;
    }

    public function getMainCommune(): ?string
    {
        return $this->mainCommune;
    }

    public function setMainCommune(?string $mainCommune): static
    {
        $this->mainCommune = $mainCommune;

        return $this;
    }

    public function getMainCommuneLat(): ?float
    {
        return $this->mainCommuneLat;
    }

    public function setMainCommuneLat(?float $mainCommuneLat): static
    {
        $this->mainCommuneLat = $mainCommuneLat;

        return $this;
    }

    public function getMainCommuneLng(): ?float
    {
        return $this->mainCommuneLng;
    }

    public function setMainCommuneLng(?float $mainCommuneLng): static
    {
        $this->mainCommuneLng = $mainCommuneLng;

        return $this;
    }

    /** @return Collection<int, Membership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }


    /** @return Collection<int, Parcours> */
    public function getParcours(): Collection
    {
        return $this->parcours;
    }

    public function addParcour(Parcours $parcours): static
    {
        if (!$this->parcours->contains($parcours)) {
            $this->parcours->add($parcours);
            $parcours->setOrganization($this);
        }

        return $this;
    }

    public function removeParcour(Parcours $parcours): static
    {
        $this->parcours->removeElement($parcours);

        return $this;
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
    public function isUnlimitedPlan(): bool
    {
        return $this->unlimitedPlan;
    }

    public function setUnlimitedPlan(bool $unlimitedPlan): static
    {
        $this->unlimitedPlan = $unlimitedPlan;

        return $this;
    }
}

