<?php

namespace App\Entity;

use App\Repository\ParcoursPointRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ParcoursPointRepository::class)]
class ParcoursPoint
{
    public const string KIND_ROUTE = 'route';
    public const string KIND_RESTAURANT = 'restaurant';
    public const string KIND_HOTEL = 'hotel';
    public const string KIND_WC = 'wc';
    public const string KIND_OFFICE_TOURISME = 'office_tourisme';
    public const array KINDS = [
        self::KIND_ROUTE,
        self::KIND_RESTAURANT,
        self::KIND_HOTEL,
        self::KIND_WC,
        self::KIND_OFFICE_TOURISME,
    ];

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['parcours:read'])]
    private string $id;

    #[ORM\ManyToOne(inversedBy: 'points')]
    #[ORM\JoinColumn(nullable: false)]
    private Parcours $parcours;

    /** 'route' = point du tracé (ordonné ou libre) ; les autres = point connexe (service à proximité). */
    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: self::KINDS)]
    #[Groups(['parcours:read'])]
    private string $kind = self::KIND_ROUTE;

    #[ORM\Column(nullable: true)]
    #[Groups(['parcours:read'])]
    private ?int $position = null;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\NotNull]
    #[Assert\Range(min: -90, max: 90)]
    #[Groups(['parcours:read'])]
    private float $latitude;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\NotNull]
    #[Assert\Range(min: -180, max: 180)]
    #[Groups(['parcours:read'])]
    private float $longitude;

    /** @var array<string, string> map locale => nom, ex. {"fr": "Cathédrale", "en": "Cathedral"} */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Count(min: 1, minMessage: 'Le point doit avoir un nom dans au moins une langue.')]
    #[Groups(['parcours:read'])]
    private array $name = [];

    /** @var array<string, string> map locale => description */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['parcours:read'])]
    private array $description = [];

    #[ORM\Column(nullable: true)]
    #[Groups(['parcours:read'])]
    private ?string $datatourismeId = null;

    /**
     * Versions audio du texte du point, par langue. Même logique que sur le
     * parcours : générées explicitement, jamais en silence.
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['parcours:read'])]
    private array $audio = [];

    /**
     * Lien YouTube ou Dailymotion. La vidéo n'est pas hébergée chez nous : une
     * vidéo de deux minutes pèse une cinquantaine de mégaoctets, ce qui la rend
     * impossible à précharger pour le hors-ligne et coûterait, en diffusion,
     * plus que l'abonnement annuel d'une organisation.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: "Ce lien n'est pas une adresse valide.")]
    #[Assert\Regex(
        pattern: '#^https?://(www\\.)?(youtube\\.com/|youtu\\.be/|dailymotion\\.com/|dai\\.ly/)#i',
        message: 'Seuls les liens YouTube et Dailymotion sont acceptés.',
    )]
    #[Groups(['parcours:read'])]
    private ?string $videoUrl = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['parcours:read'])]
    private ?string $imageUrl = null;

    /** @var list<array{title: string, path: string}> médias déposés depuis MANAGER (titre + chemin servi par MediaController) */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['parcours:read'])]
    private array $media = [];

    /** @var list<array{label: string, url: string}> */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['parcours:read'])]
    private array $links = [];

    #[ORM\Column]
    #[Groups(['parcours:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getParcours(): Parcours
    {
        return $this->parcours;
    }

    public function setParcours(Parcours $parcours): static
    {
        $this->parcours = $parcours;

        return $this;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    /** @return array<string, string> */
    public function getName(): array
    {
        return $this->name;
    }

    /** @param array<string, string> $name */
    public function setName(array $name): static
    {
        $this->name = $name;

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

    public function getDatatourismeId(): ?string
    {
        return $this->datatourismeId;
    }

    public function setDatatourismeId(?string $datatourismeId): static
    {
        $this->datatourismeId = $datatourismeId;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    /** @return list<array{title: string, path: string}> */
    public function getMedia(): array
    {
        return $this->media;
    }

    /** @param list<array{title: string, path: string}> $media */
    public function setMedia(array $media): static
    {
        $this->media = $media;

        return $this;
    }

    /** @return list<array{label: string, url: string}> */
    public function getLinks(): array
    {
        return $this->links;
    }

    /** @param list<array{label: string, url: string}> $links */
    public function setLinks(array $links): static
    {
        $this->links = $links;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    /** @return array<string, string> */
    public function getAudio(): array
    {
        return $this->audio;
    }

    /** @param array<string, string> $audio */
    public function setAudio(array $audio): static
    {
        $this->audio = $audio;

        return $this;
    }
    public function getVideoUrl(): ?string
    {
        return $this->videoUrl;
    }

    public function setVideoUrl(?string $videoUrl): static
    {
        $this->videoUrl = $videoUrl ?: null;

        return $this;
    }

    /**
     * Fournisseur et identifiant extraits du lien, pour que l'application
     * publique construise l'intégration sans avoir à analyser l'URL — et
     * surtout sans qu'un lien mal formé s'y retrouve tel quel.
     *
     * @return array{provider: string, id: string}|null
     */
    #[Groups(['parcours:read'])]
    public function getVideo(): ?array
    {
        if (null === $this->videoUrl) {
            return null;
        }

        $motifs = [
            'youtube' => [
                '#youtube\\.com/watch\\?(?:.*&)?v=([\\w-]{6,})#i',
                '#youtu\\.be/([\\w-]{6,})#i',
                '#youtube\\.com/embed/([\\w-]{6,})#i',
                '#youtube\\.com/shorts/([\\w-]{6,})#i',
            ],
            'dailymotion' => [
                '#dailymotion\\.com/video/([\\w]+)#i',
                '#dai\\.ly/([\\w]+)#i',
            ],
        ];

        foreach ($motifs as $fournisseur => $regles) {
            foreach ($regles as $regle) {
                if (preg_match($regle, $this->videoUrl, $trouve)) {
                    return ['provider' => $fournisseur, 'id' => $trouve[1]];
                }
            }
        }

        return null;
    }
}


