<?php

namespace App\Entity;

use App\Repository\MembershipRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Appartenance d'un utilisateur à une organisation.
 *
 * Remplace le OneToOne User↔Organization, qui interdisait à la fois les
 * co-gestionnaires et les organisations multiples. Or la formule « Territoire »
 * s'adresse à des offices de tourisme, des intercommunalités et des villes :
 * des structures à plusieurs personnes, parfois à plusieurs communes.
 */
#[ORM\Entity(repositoryClass: MembershipRepository::class)]
#[ORM\Table(name: 'membership')]
#[ORM\UniqueConstraint(name: 'uniq_membership_user_organization', fields: ['user', 'organization'])]
class Membership
{
    /** Peut gérer les membres et supprimer l'organisation. */
    public const string ROLE_OWNER = 'owner';
    /** Peut créer et modifier les parcours, rien de plus. */
    public const string ROLE_EDITOR = 'editor';
    public const array ROLES = [self::ROLE_OWNER, self::ROLE_EDITOR];

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['membership:read'])]
    private string $id;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: self::ROLES)]
    #[Groups(['membership:read'])]
    private string $role = self::ROLE_EDITOR;

    #[ORM\Column]
    #[Groups(['membership:read'])]
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

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

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function isOwner(): bool
    {
        return self::ROLE_OWNER === $this->role;
    }

    /** Adresse du membre, pour l'écran de gestion des accès. */
    #[Groups(['membership:read'])]
    public function getEmail(): string
    {
        return $this->user->getEmail();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
