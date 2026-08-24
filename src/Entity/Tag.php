<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_tag_name', fields: ['name'])]
class Tag
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $name;

    #[ORM\ManyToMany(targetEntity: Parcours::class, mappedBy: 'tags')]
    private Collection $parcours;

    public function __construct(string $name)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->name = self::normalize($name);
        $this->parcours = new ArrayCollection();
    }

    public static function normalize(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection<int, Parcours> */
    public function getParcours(): Collection
    {
        return $this->parcours;
    }
}
