<?php
// src/Entity/MedicalRecord.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: "App\Repository\MedicalRecordRepository")]
#[ORM\Table(name: "medical_records")]
#[ORM\HasLifecycleCallbacks]
class MedicalRecord
{
    public const TYPE_CONSULTATION = 'consultation';
    public const TYPE_DIAGNOSIS = 'diagnosis';
    public const TYPE_LAB_RESULT = 'lab_result';
    public const TYPE_IMAGING = 'imaging';
    public const TYPE_PROCEDURE = 'procedure';
    public const TYPE_VACCINATION = 'vaccination';
    public const TYPE_OTHER = 'other';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: "medicalRecords")]
    #[ORM\JoinColumn(nullable: false)]
    private Patient $patient;

    #[ORM\ManyToOne(targetEntity: Doctor::class, inversedBy: "medicalRecords")]
    #[ORM\JoinColumn(nullable: false)]
    private Doctor $doctor;

    #[ORM\Column(type: "string", length: 50)]
    #[Assert\Choice(choices: [
        self::TYPE_CONSULTATION,
        self::TYPE_DIAGNOSIS,
        self::TYPE_LAB_RESULT,
        self::TYPE_IMAGING,
        self::TYPE_PROCEDURE,
        self::TYPE_VACCINATION,
        self::TYPE_OTHER
    ])]
    private string $type;

    #[ORM\Column(type: "json")]
    private array $data = [];

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $recordDate;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $updatedAt;

    #[ORM\ManyToOne(targetEntity: Appointment::class, inversedBy: "medicalRecords")]
    private ?Appointment $appointment = null;

    #[ORM\Column(type: "boolean")]
    private bool $isConfidential = false;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $attachments = null;

    public function __construct()
    {
        $this->recordDate = new \DateTime();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->data = [
            'vital_signs' => [],
            'symptoms' => [],
            'diagnosis' => [],
            'treatment' => [],
            'recommendations' => []
        ];
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
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getData(): array { return $this->data; }
    public function setData(array $data): self { $this->data = $data; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
    public function getRecordDate(): \DateTimeInterface { return $this->recordDate; }
    public function setRecordDate(\DateTimeInterface $date): self { $this->recordDate = $date; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function getAppointment(): ?Appointment { return $this->appointment; }
    public function setAppointment(?Appointment $appointment): self { $this->appointment = $appointment; return $this; }
    public function isConfidential(): bool { return $this->isConfidential; }
    public function setIsConfidential(bool $confidential): self { $this->isConfidential = $confidential; return $this; }
    public function getAttachments(): ?array { return $this->attachments; }
    public function setAttachments(?array $attachments): self { $this->attachments = $attachments; return $this; }
    public function addAttachment(string $filename, string $type): self { 
        $this->attachments[] = ['filename' => $filename, 'type' => $type, 'uploaded_at' => (new \DateTime())->format('Y-m-d H:i:s')]; 
        return $this; 
    }

    public function getVitalSigns(): array { return $this->data['vital_signs'] ?? []; }
    public function setVitalSigns(array $vitalSigns): self { $this->data['vital_signs'] = $vitalSigns; return $this; }
    public function addVitalSign(string $name, $value): self { 
        $this->data['vital_signs'][$name] = $value; 
        return $this; 
    }

    public function getSymptoms(): array { return $this->data['symptoms'] ?? []; }
    public function setSymptoms(array $symptoms): self { $this->data['symptoms'] = $symptoms; return $this; }
    public function addSymptom(string $symptom): self { 
        $this->data['symptoms'][] = $symptom; 
        return $this; 
    }

    public function getDiagnosis(): array { return $this->data['diagnosis'] ?? []; }
    public function setDiagnosis(array $diagnosis): self { $this->data['diagnosis'] = $diagnosis; return $this; }
    public function addDiagnosis(string $diagnosis): self { 
        $this->data['diagnosis'][] = $diagnosis; 
        return $this; 
    }

    public function getTreatment(): array { return $this->data['treatment'] ?? []; }
    public function setTreatment(array $treatment): self { $this->data['treatment'] = $treatment; return $this; }
    public function addTreatment(string $treatment): self { 
        $this->data['treatment'][] = $treatment; 
        return $this; 
    }

    public function getRecommendations(): array { return $this->data['recommendations'] ?? []; }
    public function setRecommendations(array $recommendations): self { $this->data['recommendations'] = $recommendations; return $this; }
    public function addRecommendation(string $recommendation): self { 
        $this->data['recommendations'][] = $recommendation; 
        return $this; 
    }

    public function getFormattedData(): string
    {
        $output = [];
        
        if (!empty($this->data['vital_signs'])) {
            $output[] = "Vital Signs:";
            foreach ($this->data['vital_signs'] as $name => $value) {
                $output[] = "  - {$name}: {$value}";
            }
        }
        
        if (!empty($this->data['symptoms'])) {
            $output[] = "Symptoms: " . implode(', ', $this->data['symptoms']);
        }
        
        if (!empty($this->data['diagnosis'])) {
            $output[] = "Diagnosis: " . implode(', ', $this->data['diagnosis']);
        }
        
        if (!empty($this->data['treatment'])) {
            $output[] = "Treatment: " . implode(', ', $this->data['treatment']);
        }
        
        if (!empty($this->data['recommendations'])) {
            $output[] = "Recommendations: " . implode(', ', $this->data['recommendations']);
        }
        
        if ($this->notes) {
            $output[] = "Notes: " . $this->notes;
        }
        
        return implode("\n", $output);
    }
}