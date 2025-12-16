<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: "App\Repository\PrescriptionRepository")]
#[ORM\Table(name: "prescriptions")]
#[ORM\HasLifecycleCallbacks]
class Prescription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: "prescriptions")]
    #[ORM\JoinColumn(nullable: false)]
    private Patient $patient;

    #[ORM\ManyToOne(targetEntity: Doctor::class, inversedBy: "prescriptions")]
    #[ORM\JoinColumn(nullable: false)]
    private Doctor $doctor;

    #[ORM\Column(type: "json")]
    #[Assert\NotBlank]
    private array $medications = [];

    #[ORM\Column(type: "date")]
    #[Assert\NotNull]
    private \DateTimeInterface $prescribedDate;

    #[ORM\Column(type: "date")]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(propertyPath: "prescribedDate")]
    private \DateTimeInterface $validUntil;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $instructions = null;

    #[ORM\Column(type: "boolean")]
    private bool $isCompleted = false;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $updatedAt;

    #[ORM\ManyToOne(targetEntity: MedicalRecord::class, inversedBy: "prescriptions")]
    private ?MedicalRecord $medicalRecord = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $pharmacyNotes = null;

    public function __construct()
    {
        $this->prescribedDate = new \DateTime();
        $this->validUntil = (new \DateTime())->modify('+30 days');
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getPatient(): Patient { return $this->patient; }
    public function setPatient(Patient $patient): self { $this->patient = $patient; return $this; }
    public function getDoctor(): Doctor { return $this->doctor; }
    public function setDoctor(Doctor $doctor): self { $this->doctor = $doctor; return $this; }
    public function getMedications(): array { return $this->medications; }
    public function setMedications(array $medications): self { $this->medications = $medications; return $this; }
    public function addMedication(string $name, string $dosage, string $frequency, int $duration): self { 
        $this->medications[] = [
            'name' => $name,
            'dosage' => $dosage,
            'frequency' => $frequency,
            'duration' => $duration,
            'started' => false,
            'completed' => false
        ]; 
        return $this; 
    }
    public function getPrescribedDate(): \DateTimeInterface { return $this->prescribedDate; }
    public function setPrescribedDate(\DateTimeInterface $date): self { $this->prescribedDate = $date; return $this; }
    public function getValidUntil(): \DateTimeInterface { return $this->validUntil; }
    public function setValidUntil(\DateTimeInterface $date): self { $this->validUntil = $date; return $this; }
    public function getInstructions(): ?string { return $this->instructions; }
    public function setInstructions(?string $instructions): self { $this->instructions = $instructions; return $this; }
    public function isCompleted(): bool { return $this->isCompleted; }
    public function setIsCompleted(bool $completed): self { $this->isCompleted = $completed; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function getMedicalRecord(): ?MedicalRecord { return $this->medicalRecord; }
    public function setMedicalRecord(?MedicalRecord $record): self { $this->medicalRecord = $record; return $this; }
    public function getPharmacyNotes(): ?string { return $this->pharmacyNotes; }
    public function setPharmacyNotes(?string $notes): self { $this->pharmacyNotes = $notes; return $this; }

    public function isActive(): bool
    {
        $now = new \DateTime();
        return !$this->isCompleted && $this->validUntil >= $now;
    }

    public function getFormattedMedications(): string
    {
        $output = [];
        foreach ($this->medications as $med) {
            $output[] = sprintf(
                "%s: %s, %s, %d days",
                $med['name'],
                $med['dosage'],
                $med['frequency'],
                $med['duration']
            );
        }
        return implode("\n", $output);
    }

    public function markMedicationAsStarted(int $index): void
    {
        if (isset($this->medications[$index])) {
            $this->medications[$index]['started'] = true;
        }
    }

    public function markMedicationAsCompleted(int $index): void
    {
        if (isset($this->medications[$index])) {
            $this->medications[$index]['completed'] = true;
            
            $allCompleted = true;
            foreach ($this->medications as $med) {
                if (!$med['completed']) {
                    $allCompleted = false;
                    break;
                }
            }
            
            if ($allCompleted) {
                $this->isCompleted = true;
            }
        }
    }
}