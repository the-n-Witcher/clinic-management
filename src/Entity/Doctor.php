<?php
// src/Entity/Doctor.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: "App\Repository\DoctorRepository")]
#[ORM\Table(name: "doctors")]
#[ORM\Cache(usage: "NONSTRICT_READ_WRITE", region: "doctors")]
class Doctor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100)]
    #[Assert\NotBlank]
    private string $firstName;

    #[ORM\Column(type: "string", length: 100)]
    #[Assert\NotBlank]
    private string $lastName;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $middleName = null;

    #[ORM\Column(type: "string", length: 100)]
    #[Assert\NotBlank]
    private string $specialization;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $licenseNumber = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $qualification = null;

    #[ORM\Column(type: "integer")]
    #[Assert\Range(min: 1, max: 120)]
    private int $consultationDuration = 30;

    #[ORM\Column(type: "json")]
    #[Assert\NotBlank]
    private array $schedule = [
        'monday' => [['start' => '09:00', 'end' => '17:00']],
        'tuesday' => [['start' => '09:00', 'end' => '17:00']],
        'wednesday' => [['start' => '09:00', 'end' => '17:00']],
        'thursday' => [['start' => '09:00', 'end' => '17:00']],
        'friday' => [['start' => '09:00', 'end' => '17:00']],
        'saturday' => [],
        'sunday' => []
    ];

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, nullable: true)]
    private ?string $consultationFee = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $languages = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: "doctor", targetEntity: Appointment::class)]
    private Collection $appointments;

    #[ORM\OneToMany(mappedBy: "doctor", targetEntity: MedicalRecord::class)]
    private Collection $medicalRecords;

    #[ORM\OneToMany(mappedBy: "doctor", targetEntity: Prescription::class)]
    private Collection $prescriptions;

    #[ORM\ManyToOne(targetEntity: Room::class, inversedBy: "doctors")]
    private ?Room $room = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: "doctor")]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false)]
    private User $user;

    #[ORM\ManyToMany(targetEntity: Equipment::class, inversedBy: "doctors")]
    #[ORM\JoinTable(name: "doctor_equipment")]
    private Collection $equipment;

    public function __construct()
    {
        $this->appointments = new ArrayCollection();
        $this->medicalRecords = new ArrayCollection();
        $this->prescriptions = new ArrayCollection();
        $this->equipment = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
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
    public function getSpecialization(): string { return $this->specialization; }
    public function setSpecialization(string $specialization): self { $this->specialization = $specialization; return $this; }
    public function getLicenseNumber(): ?string { return $this->licenseNumber; }
    public function setLicenseNumber(?string $licenseNumber): self { $this->licenseNumber = $licenseNumber; return $this; }
    public function getQualification(): ?string { return $this->qualification; }
    public function setQualification(?string $qualification): self { $this->qualification = $qualification; return $this; }
    public function getConsultationDuration(): int { return $this->consultationDuration; }
    public function setConsultationDuration(int $minutes): self { $this->consultationDuration = $minutes; return $this; }
    public function getSchedule(): array { return $this->schedule; }
    public function setSchedule(array $schedule): self { $this->schedule = $schedule; return $this; }
    public function getConsultationFee(): ?string { return $this->consultationFee; }
    public function setConsultationFee(?string $fee): self { $this->consultationFee = $fee; return $this; }
    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): self { $this->bio = $bio; return $this; }
    public function getLanguages(): ?array { return $this->languages; }
    public function setLanguages(?array $languages): self { $this->languages = $languages; return $this; }
    public function addLanguage(string $language): self { 
        $this->languages = array_unique(array_merge($this->languages ?? [], [$language])); 
        return $this; 
    }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function getRoom(): ?Room { return $this->room; }
    public function setRoom(?Room $room): self { $this->room = $room; return $this; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    /**
     * @return Collection|Appointment[]
     */
    public function getAppointments(): Collection { return $this->appointments; }

    /**
     * @return Collection|MedicalRecord[]
     */
    public function getMedicalRecords(): Collection { return $this->medicalRecords; }

    /**
     * @return Collection|Prescription[]
     */
    public function getPrescriptions(): Collection { return $this->prescriptions; }

    /**
     * @return Collection|Equipment[]
     */
    public function getEquipment(): Collection { return $this->equipment; }
    public function addEquipment(Equipment $equipment): self { 
        if (!$this->equipment->contains($equipment)) {
            $this->equipment[] = $equipment;
            $equipment->addDoctor($this);
        }
        return $this;
    }
    public function removeEquipment(Equipment $equipment): self { 
        if ($this->equipment->removeElement($equipment)) {
            $equipment->removeDoctor($this);
        }
        return $this;
    }

    public function isAvailable(\DateTimeInterface $dateTime): bool
    {
        $dayOfWeek = strtolower($dateTime->format('l'));
        $time = $dateTime->format('H:i');
        
        if (!isset($this->schedule[$dayOfWeek])) {
            return false;
        }

        foreach ($this->schedule[$dayOfWeek] as $period) {
            if ($time >= $period['start'] && $time <= $period['end']) {
                return true;
            }
        }

        return false;
    }

    public function getWorkingHours(string $day): array
    {
        return $this->schedule[strtolower($day)] ?? [];
    }

    public function getTodayAppointments(): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = clone $today;
        $tomorrow->modify('+1 day');

        return $this->appointments->filter(
            fn(Appointment $appointment) => 
                $appointment->getStartTime() >= $today && 
                $appointment->getStartTime() < $tomorrow
        )->toArray();
    }

    public function getWeeklyStatistics(): array
    {
        $startOfWeek = new \DateTime('monday this week');
        $endOfWeek = new \DateTime('sunday this week 23:59:59');

        $completed = $this->appointments->filter(
            fn(Appointment $appointment) => 
                $appointment->getStatus() === Appointment::STATUS_COMPLETED &&
                $appointment->getStartTime() >= $startOfWeek &&
                $appointment->getStartTime() <= $endOfWeek
        )->count();

        $cancelled = $this->appointments->filter(
            fn(Appointment $appointment) => 
                $appointment->getStatus() === Appointment::STATUS_CANCELLED &&
                $appointment->getStartTime() >= $startOfWeek &&
                $appointment->getStartTime() <= $endOfWeek
        )->count();

        return [
            'completed' => $completed,
            'cancelled' => $cancelled,
            'total' => $completed + $cancelled
        ];
    }
}