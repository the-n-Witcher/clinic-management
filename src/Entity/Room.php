<?php
// src/Entity/Room.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: "App\Repository\RoomRepository")]
#[ORM\Table(name: "rooms")]
class Room
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 50)]
    private string $number;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $floor = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $features = null;

    #[ORM\Column(type: "boolean")]
    private bool $isAvailable = true;

    #[ORM\OneToMany(mappedBy: "room", targetEntity: Doctor::class)]
    private Collection $doctors;

    #[ORM\OneToMany(mappedBy: "room", targetEntity: Equipment::class)]
    private Collection $equipment;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->doctors = new ArrayCollection();
        $this->equipment = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getNumber(): string { return $this->number; }
    public function setNumber(string $number): self { $this->number = $number; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): self { $this->type = $type; return $this; }
    public function getFloor(): ?int { return $this->floor; }
    public function setFloor(?int $floor): self { $this->floor = $floor; return $this; }
    public function getFeatures(): ?array { return $this->features; }
    public function setFeatures(?array $features): self { $this->features = $features; return $this; }
    public function addFeature(string $feature): self { 
        $this->features = array_unique(array_merge($this->features ?? [], [$feature])); 
        return $this; 
    }
    public function isAvailable(): bool { return $this->isAvailable; }
    public function setIsAvailable(bool $available): self { $this->isAvailable = $available; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    /**
     * @return Collection|Doctor[]
     */
    public function getDoctors(): Collection { return $this->doctors; }
    public function addDoctor(Doctor $doctor): self { 
        if (!$this->doctors->contains($doctor)) {
            $this->doctors[] = $doctor;
            $doctor->setRoom($this);
        }
        return $this;
    }
    public function removeDoctor(Doctor $doctor): self { 
        if ($this->doctors->removeElement($doctor)) {
            if ($doctor->getRoom() === $this) {
                $doctor->setRoom(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection|Equipment[]
     */
    public function getEquipment(): Collection { return $this->equipment; }
    public function addEquipment(Equipment $equipment): self { 
        if (!$this->equipment->contains($equipment)) {
            $this->equipment[] = $equipment;
            $equipment->setRoom($this);
        }
        return $this;
    }
    public function removeEquipment(Equipment $equipment): self { 
        if ($this->equipment->removeElement($equipment)) {
            if ($equipment->getRoom() === $this) {
                $equipment->setRoom(null);
            }
        }
        return $this;
    }

    public function getFullName(): string
    {
        return "Room {$this->number} - {$this->name}";
    }

    public function getAvailableEquipment(): array
    {
        return $this->equipment->filter(
            fn(Equipment $equipment) => $equipment->isAvailable()
        )->toArray();
    }

    public function getAssignedDoctors(): array
    {
        return $this->doctors->toArray();
    }
}