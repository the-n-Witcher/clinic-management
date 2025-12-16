<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: "App\Repository\AppointmentRepository")]
#[ORM\Table(name: "appointments")]
#[ORM\HasLifecycleCallbacks]
class Appointment
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: "appointments")]
    #[ORM\JoinColumn(nullable: false)]
    private Patient $patient;

    #[ORM\ManyToOne(targetEntity: Doctor::class, inversedBy: "appointments")]
    #[ORM\JoinColumn(nullable: false)]
    private Doctor $doctor;

    #[ORM\Column(type: "boolean")]
    private bool $reminderSent = false;

    #[ORM\Column(type: "boolean")]
    private bool $noShowMarked = false;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $cancelledAt = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $cancelledBy = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $updatedBy = null;

    #[ORM\Column(type: "datetime")]
    #[Assert\NotNull]
    private \DateTimeInterface $startTime;

    #[ORM\Column(type: "datetime")]
    #[Assert\NotNull]
    private \DateTimeInterface $endTime;

    #[ORM\Column(type: "string", length: 20)]
    #[Assert\Choice(choices: [
        self::STATUS_SCHEDULED,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_NO_SHOW
    ])]
    private string $status = self::STATUS_SCHEDULED;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToOne(mappedBy: "appointment", targetEntity: MedicalRecord::class)]
    private ?MedicalRecord $medicalRecord = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getPatient(): Patient { return $this->patient; }
    public function setPatient(Patient $patient): self { $this->patient = $patient; return $this; }
    public function getDoctor(): Doctor { return $this->doctor; }
    public function setDoctor(Doctor $doctor): self { $this->doctor = $doctor; return $this; }
    public function getStartTime(): \DateTimeInterface { return $this->startTime; }
    public function setStartTime(\DateTimeInterface $startTime): self { $this->startTime = $startTime; return $this; }
    public function getEndTime(): \DateTimeInterface { return $this->endTime; }
    public function setEndTime(\DateTimeInterface $endTime): self { $this->endTime = $endTime; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): self { $this->reason = $reason; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function getMedicalRecord(): ?MedicalRecord { return $this->medicalRecord; }
    public function setMedicalRecord(?MedicalRecord $medicalRecord): self { $this->medicalRecord = $medicalRecord; return $this; }
public function isReminderSent(): bool
    {
        return $this->reminderSent;
    }

    public function setReminderSent(bool $reminderSent): self
    {
        $this->reminderSent = $reminderSent;
        return $this;
    }

    public function isNoShowMarked(): bool
    {
        return $this->noShowMarked;
    }

    public function setNoShowMarked(bool $noShowMarked): self
    {
        $this->noShowMarked = $noShowMarked;
        return $this;
    }

    public function getCancelledAt(): ?\DateTimeInterface
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeInterface $cancelledAt): self
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?string $cancellationReason): self
    {
        $this->cancellationReason = $cancellationReason;
        return $this;
    }

    public function getCancelledBy(): ?string
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?string $cancelledBy): self
    {
        $this->cancelledBy = $cancelledBy;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

}