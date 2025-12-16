<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: "App\Repository\PatientRepository")]
#[ORM\Table(name: "patients")]
#[ORM\HasLifecycleCallbacks]
#[ORM\Cache(usage: "NONSTRICT_READ_WRITE", region: "patients")]
class Patient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100)]
    #[Assert\NotBlank(message: "patient.first_name.not_blank")]
    #[Assert\Length(min: 2, max: 100)]
    private string $firstName;

    #[ORM\Column(type: "string", length: 100)]
    #[Assert\NotBlank(message: "patient.last_name.not_blank")]
    #[Assert\Length(min: 2, max: 100)]
    private string $lastName;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $middleName = null;

    #[ORM\Column(type: "date")]
    #[Assert\NotNull(message: "patient.date_of_birth.not_null")]
    #[Assert\LessThan("today")]
    private \DateTimeInterface $dateOfBirth;

    #[ORM\Column(type: "string", length: 10)]
    #[Assert\Choice(choices: ["male", "female", "other"])]
    private string $gender;

    #[ORM\Column(type: "string", length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: "/^MED-\d{6}$/", message: "patient.medical_number.format")]
    private string $medicalNumber;

    #[ORM\Column(type: "string", length: 100)]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(type: "string", length: 20)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: "/^\+?[1-9]\d{1,14}$/")]
    private string $phone;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $insuranceCompany = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $insurancePolicy = null;

    #[ORM\Column(type: "string", length: 20, nullable: true)]
    private ?string $bloodType = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $allergies = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $chronicDiseases = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: "patient", targetEntity: Appointment::class)]
    private Collection $appointments;

    #[ORM\OneToMany(mappedBy: "patient", targetEntity: MedicalRecord::class)]
    private Collection $medicalRecords;

    #[ORM\OneToMany(mappedBy: "patient", targetEntity: Prescription::class)]
    private Collection $prescriptions;

    #[ORM\OneToMany(mappedBy: "patient", targetEntity: Invoice::class)]
    private Collection $invoices;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: "patient", cascade: ["persist"])]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: "boolean")]
    private bool $consentToDataProcessing = false;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $emergencyContactName = null;

    #[ORM\Column(type: "string", length: 20, nullable: true)]
    private ?string $emergencyContactPhone = null;

    public function __construct()
    {
        $this->appointments = new ArrayCollection();
        $this->medicalRecords = new ArrayCollection();
        $this->prescriptions = new ArrayCollection();
        $this->invoices = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->generateMedicalNumber();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }

    private function generateMedicalNumber(): void
    {
        if (empty($this->medicalNumber)) {
            $this->medicalNumber = 'MED-' . str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $firstName): self { $this->firstName = $firstName; return $this; }
    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $lastName): self { $this->lastName = $lastName; return $this; }
    public function getMiddleName(): ?string { return $this->middleName; }
    public function setMiddleName(?string $middleName): self { $this->middleName = $middleName; return $this; }
    public function getFullName(): string { 
        return $this->lastName . ' ' . $this->firstName . 
               ($this->middleName ? ' ' . $this->middleName : ''); 
    }
    public function getDateOfBirth(): \DateTimeInterface { return $this->dateOfBirth; }
    public function setDateOfBirth(\DateTimeInterface $dateOfBirth): self { $this->dateOfBirth = $dateOfBirth; return $this; }
    public function getAge(): int { return $this->dateOfBirth->diff(new \DateTime())->y; }
    public function getGender(): string { return $this->gender; }
    public function setGender(string $gender): self { $this->gender = $gender; return $this; }
    public function getMedicalNumber(): string { return $this->medicalNumber; }
    public function setMedicalNumber(string $medicalNumber): self { $this->medicalNumber = $medicalNumber; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
    public function getPhone(): string { return $this->phone; }
    public function setPhone(string $phone): self { $this->phone = $phone; return $this; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }
    public function getInsuranceCompany(): ?string { return $this->insuranceCompany; }
    public function setInsuranceCompany(?string $insuranceCompany): self { $this->insuranceCompany = $insuranceCompany; return $this; }
    public function getInsurancePolicy(): ?string { return $this->insurancePolicy; }
    public function setInsurancePolicy(?string $insurancePolicy): self { $this->insurancePolicy = $insurancePolicy; return $this; }
    public function getBloodType(): ?string { return $this->bloodType; }
    public function setBloodType(?string $bloodType): self { $this->bloodType = $bloodType; return $this; }
    public function getAllergies(): ?array { return $this->allergies; }
    public function setAllergies(?array $allergies): self { $this->allergies = $allergies; return $this; }
    public function addAllergy(string $allergy): self { 
        $this->allergies = array_unique(array_merge($this->allergies ?? [], [$allergy])); 
        return $this; 
    }
    public function getChronicDiseases(): ?array { return $this->chronicDiseases; }
    public function setChronicDiseases(?array $chronicDiseases): self { $this->chronicDiseases = $chronicDiseases; return $this; }
    public function addChronicDisease(string $disease): self { 
        $this->chronicDiseases = array_unique(array_merge($this->chronicDiseases ?? [], [$disease])); 
        return $this; 
    }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        if ($this->user === $user) {
            return $this;
        }

        $oldUser = $this->user;
        $this->user = $user;
        
        if ($user !== null && $user->getPatient() !== $this) {
            $user->setPatient($this);
        }
        
        if ($oldUser !== null && $oldUser->getPatient() === $this) {
            $oldUser->setPatient(null);
        }
        
        return $this;
    }

    public function hasConsentToDataProcessing(): bool { return $this->consentToDataProcessing; }
    public function setConsentToDataProcessing(bool $consent): self { $this->consentToDataProcessing = $consent; return $this; }
    public function getEmergencyContactName(): ?string { return $this->emergencyContactName; }
    public function setEmergencyContactName(?string $name): self { $this->emergencyContactName = $name; return $this; }
    public function getEmergencyContactPhone(): ?string { return $this->emergencyContactPhone; }
    public function setEmergencyContactPhone(?string $phone): self { $this->emergencyContactPhone = $phone; return $this; }

    /**
     * @return Collection|Appointment[]
     */
    public function getAppointments(): Collection { return $this->appointments; }
    public function addAppointment(Appointment $appointment): self { 
        if (!$this->appointments->contains($appointment)) {
            $this->appointments[] = $appointment;
            $appointment->setPatient($this);
        }
        return $this;
    }
    public function removeAppointment(Appointment $appointment): self { 
        if ($this->appointments->removeElement($appointment)) {
            if ($appointment->getPatient() === $this) {
                $appointment->setPatient($this);
            }
        }
        return $this;
    }

    /**
     * @return Collection|MedicalRecord[]
     */
    public function getMedicalRecords(): Collection { return $this->medicalRecords; }

    /**
     * @return Collection|Prescription[]
     */
    public function getPrescriptions(): Collection { return $this->prescriptions; }

    /**
     * @return Collection|Invoice[]
     */
    public function getInvoices(): Collection { return $this->invoices; }

    public function getActivePrescriptions(): array
    {
        $now = new \DateTime();
        return $this->prescriptions->filter(
            fn(Prescription $prescription) => 
                $prescription->getValidUntil() >= $now && 
                !$prescription->isCompleted()
        )->toArray();
    }

    public function getUpcomingAppointments(): array
    {
        $now = new \DateTime();
        return $this->appointments->filter(
            fn(Appointment $appointment) => 
                $appointment->getStartTime() >= $now && 
                in_array($appointment->getStatus(), [
                    Appointment::STATUS_SCHEDULED, 
                    Appointment::STATUS_CONFIRMED
                ])
        )->toArray();
    }
}