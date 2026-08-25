<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column]
    private bool $isVerified = false;

    /**
     * Organisations auxquelles l'utilisateur appartient. Remplace l'ancien
     * OneToOne : un même compte peut gérer plusieurs territoires, et une
     * organisation peut avoir plusieurs gestionnaires.
     *
     * @var Collection<int, Membership>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Membership::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $memberships;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->memberships = new ArrayCollection();
        $this->id = Uuid::v7()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    /** @return Collection<int, Membership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    /**
     * Organisation « par défaut » : la première à laquelle l'utilisateur
     * appartient. Conservée pour les usages qui n'ont pas à choisir (courriels
     * transactionnels, affichage d'un contexte). MANAGER, lui, laisse
     * l'utilisateur sélectionner.
     */
    public function getOrganization(): ?Organization
    {
        $premiere = $this->memberships->first();

        return false === $premiere ? null : $premiere->getOrganization();
    }

    public function belongsTo(Organization $organization): bool
    {
        foreach ($this->memberships as $membership) {
            if ($membership->getOrganization()->getId() === $organization->getId()) {
                return true;
            }
        }

        return false;
    }


    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
