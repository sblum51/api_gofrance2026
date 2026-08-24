<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
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
        new Get(uriTemplate: '/organizations/mine', name: 'mine', provider: OrganizationMineProvider::class),
        new Get(requirements: ['id' => '[0-9a-fA-F-]{36}'], security: "is_granted('ORGANIZATION_VIEW', object)"),
        new Patch(requirements: ['id' => '[0-9a-fA-F-]{36}'], security: "is_granted('ORGANIZATION_EDIT', object)"),
        new Post(processor: OrganizationCreateProcessor::class),
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
    #[Groups(['organization:read'])]
    private string $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['organization:read', 'organization:write'])]
    private string $name;

    #[ORM\Column(length: 63)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 63)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Uniquement des lettres minuscules, des chiffres et des tirets.')]
    #[Assert\Choice(choices: self::RESERVED_IDENTIFIERS, match: false, message: 'Cet identifiant est réservé.')]
    #[Groups(['organization:read', 'organization:write'])]
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

    #[ORM\OneToOne(inversedBy: 'organization', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private User $owner;

    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: Parcours::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $parcours;

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

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;

        return $this;
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
}
