<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\ChildRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ChildRepository::class)
 */
#[ApiResource(
    collectionOperations: [
         'get' => ['security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')"],
         'post' => ['security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')"],
    ],
    itemOperations: [
        'get' => ['security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER') or is_granted('ROLE_TEACHER')"],
        'patch' => ['security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER') or is_granted('ROLE_TEACHER')"],
    ]
)]
class Child
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $first_name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $last_name;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $is_present;

    /**
     * @ORM\ManyToOne(targetEntity=KindGroup::class, inversedBy="children")
     */
    private $kind_group;

    /**
     * @ORM\Column(type="enum_gender")
     */
    private $gender;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function setFirstName(string $first_name): self
    {
        $this->first_name = $first_name;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setLastName(string $last_name): self
    {
        $this->last_name = $last_name;

        return $this;
    }

    public function getIsPresent(): ?bool
    {
        return $this->is_present;
    }

    public function setIsPresent(?bool $is_present): self
    {
        $this->is_present = $is_present;

        return $this;
    }

    public function getKindGroup(): ?KindGroup
    {
        return $this->kind_group;
    }

    public function setKindGroup(?KindGroup $kind_group): self
    {
        $this->kind_group = $kind_group;

        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(string $gender): self
    {
        $this->gender = $gender;

        return $this;
    }
}
