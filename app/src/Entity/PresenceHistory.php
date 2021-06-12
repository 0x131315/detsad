<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\PresenceHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PresenceHistoryRepository::class)
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"child_id","date"})})
 * @ORM\HasLifecycleCallbacks
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
class PresenceHistory
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="date")
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity=Child::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $child;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $presence;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getChild(): ?Child
    {
        return $this->child;
    }

    public function setChild(?Child $child): self
    {
        $this->child = $child;

        return $this;
    }

    public function getPresence(): ?bool
    {
        return $this->presence;
    }

    public function setPresence(?bool $presence): self
    {
        $this->presence = $presence;

        return $this;
    }

    /**
     * @ORM\PreFlush
     */
    public function setDateNow(): self
    {
        if (!$this->date) {
            $this->date = new \DateTime();
        }
        return $this;
    }
}
