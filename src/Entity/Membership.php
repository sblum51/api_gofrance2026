<?php

namespace App\Entity;

use App\Repository\MembershipRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Appartenance d'un utilisateur à une organisation.
 *
 * Pas de rôle : tout membre peut tout faire — parcours, identité de
 * l'organisation, gestion des accès. Une distinction éditeur/propriétaire
 * aurait ajouté une notion à expliquer sans répondre à un besoin réel dans une
 * structure de quelques personnes.
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
