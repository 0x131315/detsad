<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=GroupRepository::class)
 * @ORM\Table(name="`kind_group`")
 */
#[ApiResource(
    collectionOperations: [
    'get' => ['security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')"],
    'post' => ['security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')"],
],
    itemOperations: [
        'get' => ['security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER') or is_granted('ROLE_TEACHER')"],
        'patch' => ['security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')"],
    ]
)]
class KindGroup
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
    private $name;

    /**
     * @ORM\OneToMany(targetEntity=Child::class, mappedBy="kind_group")
     */
    private $children;

    /**
     * @ORM\OneToMany(targetEntity=Teacher::class, mappedBy="kind_group")
     */
    private $teachers;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->teachers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection|Child[]
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Child $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setKindGroup($this);
        }

        return $this;
    }

    public function removeChild(Child $child): self
    {
        if ($this->children->removeElement($child)) {
            // set the owning side to null (unless already changed)
            if ($child->getKindGroup() === $this) {
                $child->setKindGroup(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Teacher[]
     */
    public function getTeachers(): Collection
    {
        return $this->teachers;
    }

    public function addTeacher(Teacher $teacher): self
    {
        if (!$this->teachers->contains($teacher)) {
            $this->teachers[] = $teacher;
            $teacher->setKindGroup($this);
        }

        return $this;
    }

    public function removeTeacher(Teacher $teacher): self
    {
        if ($this->teachers->removeElement($teacher)) {
            // set the owning side to null (unless already changed)
            if ($teacher->getKindGroup() === $this) {
                $teacher->setKindGroup(null);
            }
        }

        return $this;
    }
}
