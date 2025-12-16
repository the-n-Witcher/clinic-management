<?php
// src/Entity/Equipment.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: "App\Repository\EquipmentRepository")]
#[ORM\Table(name: "equipment")]
class Equipment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $serialNumber = null;

    #[ORM\Column(type: "string", length: 100)]
    private string $type;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $manufacturer = null;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $purchaseDate = null;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $warrantyUntil = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, nullable: true)]
    private ?string $purchasePrice = null;

    #[ORM\Column(type: "string", length: 20)]
    private string $status = 'active';

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $lastMaintenance = null;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $nextMaintenance = null;

    #[ORM\ManyToOne(targetEntity: Room::class, inversedBy: "equipment")]
    private ?Room $room = null;

    #[ORM\ManyToMany(targetEntity: Doctor::class, mappedBy: "equipment")]
    private Collection $doctors;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $specifications = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->doctors = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getSerialNumber(): ?string { return $this->serialNumber; }
    public function setSerialNumber(?string $number): self { $this->serialNumber = $number; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getModel(): ?string { return $this->model; }
    public function setModel(?string $model): self { $this->model = $model; return $this; }
    public function getManufacturer(): ?string { return $this->manufacturer; }
    public function setManufacturer(?string $manufacturer): self { $this->manufacturer = $manufacturer; return $this; }
    public function getPurchaseDate(): ?\DateTimeInterface { return $this->purchaseDate; }
    public function setPurchaseDate(?\DateTimeInterface $date): self { $this->purchaseDate = $date; return $this; }
    public function getWarrantyUntil(): ?\DateTimeInterface { return $this->warrantyUntil; }
    public function setWarrantyUntil(?\DateTimeInterface $date): self { $this->warrantyUntil = $date; return $this; }
    public function getPurchasePrice(): ?string { return $this->purchasePrice; }
    public function setPurchasePrice(?string $price): self { $this->purchasePrice = $price; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getLastMaintenance(): ?\DateTimeInterface { return $this->lastMaintenance; }
    public function setLastMaintenance(?\DateTimeInterface $date): self { $this->lastMaintenance = $date; return $this; }
    public function getNextMaintenance(): ?\DateTimeInterface { return $this->nextMaintenance; }
    public function setNextMaintenance(?\DateTimeInterface $date): self { $this->nextMaintenance = $date; return $this; }
    public function getRoom(): ?Room { return $this->room; }
    public function setRoom(?Room $room): self { $this->room = $room; return $this; }
    public function getSpecifications(): ?string { return $this->specifications; }
    public function setSpecifications(?string $specifications): self { $this->specifications = $specifications; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    /**
     * @return Collection|Doctor[]
     */
    public function getDoctors(): Collection { return $this->doctors; }
    public function addDoctor(Doctor $doctor): self { 
        if (!$this->doctors->contains($doctor)) {
            $this->doctors[] = $doctor;
            $doctor->addEquipment($this);
        }
        return $this;
    }
    public function removeDoctor(Doctor $doctor): self { 
        if ($this->doctors->removeElement($doctor)) {
            $doctor->removeEquipment($this);
        }
        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->status === 'active' || $this->status === 'available';
    }

    public function isUnderMaintenance(): bool
    {
        return $this->status === 'maintenance';
    }

    public function needsMaintenance(): bool
    {
        if (!$this->nextMaintenance) {
            return false;
        }
        
        $today = new \DateTime();
        $interval = $today->diff($this->nextMaintenance);
        return $interval->days <= 7; // Needs maintenance within 7 days
    }

    public function scheduleMaintenance(\DateTimeInterface $date): void
    {
        $this->nextMaintenance = $date;
        $this->status = 'scheduled_maintenance';
    }

    public function completeMaintenance(): void
    {
        $this->lastMaintenance = new \DateTime();
        $this->nextMaintenance = (new \DateTime())->modify('+6 months'); // Next maintenance in 6 months
        $this->status = 'active';
    }

    public function getAgeInYears(): int
    {
        if (!$this->purchaseDate) {
            return 0;
        }
        
        return $this->purchaseDate->diff(new \DateTime())->y;
    }

    public function isUnderWarranty(): bool
    {
        if (!$this->warrantyUntil) {
            return false;
        }
        
        return new \DateTime() <= $this->warrantyUntil;
    }
}