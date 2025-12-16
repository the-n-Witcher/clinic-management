<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: "App\Repository\UserRepository")]
#[ORM\Table(name: "users")]
class User implements UserInterface, PasswordAuthenticatedUserInterface, \Serializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(type: "json")]
    private array $roles = ['ROLE_PATIENT'];

    #[ORM\Column(type: "string")]
    private string $password;

    #[ORM\Column(type: "string", length: 100)]
    #[Assert\NotBlank]
    private string $firstName;

    #[ORM\Column(type: "string", length: 100)]
    #[Assert\NotBlank]
    private string $lastName;

    #[ORM\Column(type: "boolean")]
    private bool $isActive = true;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    #[ORM\OneToOne(mappedBy: "user", targetEntity: Doctor::class, cascade: ["persist", "remove"])]
    private ?Doctor $doctor = null;

    #[ORM\OneToOne(mappedBy: "user", targetEntity: Patient::class, cascade: ["persist", "remove"])]
    private ?Patient $patient = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $resetTokenExpiresAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     * 
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @deprecated since Symfony 5.3, use getUserIdentifier instead
     */
    public function getUsername(): string
    {
        return $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function addRole(string $role): self
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
        return $this;
    }

    public function removeRole(string $role): self
    {
        $key = array_search($role, $this->roles, true);
        if ($key !== false) {
            unset($this->roles[$key]);
        }
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): self
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

     public function getDoctor(): ?Doctor
    {
        return $this->doctor;
    }

    public function setDoctor(?Doctor $doctor): self
    {
        if ($this->doctor !== null && $this->doctor !== $doctor) {
            $this->doctor->setUser($this);
        }
        
        $this->doctor = $doctor;

        if ($doctor !== null && $doctor->getUser() !== $this) {
            $doctor->setUser($this);
        }
        
        return $this;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): self
    {
        if ($patient === null && $this->patient !== null) {
            $oldPatient = $this->patient;
            $this->patient = null;
            
            if ($oldPatient->getUser() === $this) {
                $oldPatient->setUser(null);
            }
            return $this;
        }
        
        if ($this->patient === $patient) {
            return $this;
        }

        $oldPatient = $this->patient;
        $this->patient = $patient;
        
        if ($oldPatient !== null && $oldPatient->getUser() === $this) {
            $oldPatient->setUser(null);
        }
        
        if ($patient !== null && $patient->getUser() !== $this) {
            $patient->setUser($this);
        }
        
        return $this;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): self
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->resetTokenExpiresAt;
    }

    public function setResetTokenExpiresAt(?\DateTimeInterface $resetTokenExpiresAt): self
    {
        $this->resetTokenExpiresAt = $resetTokenExpiresAt;
        return $this;
    }

    public function generateResetToken(): void
    {
        $this->resetToken = bin2hex(random_bytes(32));
        $this->resetTokenExpiresAt = (new \DateTime())->modify('+24 hours');
    }

    public function isResetTokenValid(): bool
    {
        if (!$this->resetToken || !$this->resetTokenExpiresAt) {
            return false;
        }

        return $this->resetTokenExpiresAt > new \DateTime();
    }

    public function clearResetToken(): void
    {
        $this->resetToken = null;
        $this->resetTokenExpiresAt = null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
    }

    /**
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return null;
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    public function isDoctor(): bool
    {
        return in_array('ROLE_DOCTOR', $this->getRoles(), true);
    }

    public function isReceptionist(): bool
    {
        return in_array('ROLE_RECEPTIONIST', $this->getRoles(), true);
    }

    public function isNurse(): bool
    {
        return in_array('ROLE_NURSE', $this->getRoles(), true);
    }

    public function isPatient(): bool
    {
        return in_array('ROLE_PATIENT', $this->getRoles(), true);
    }

    public function getPrimaryRole(): string
    {
        $roles = $this->getRoles();
        
        $rolePriority = [
            'ROLE_ADMIN',
            'ROLE_DOCTOR', 
            'ROLE_RECEPTIONIST',
            'ROLE_NURSE',
            'ROLE_PATIENT',
            'ROLE_USER'
        ];

        foreach ($rolePriority as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return 'ROLE_USER';
    }

    public function getPrimaryRoleName(): string
    {
        $role = $this->getPrimaryRole();
        
        $roleNames = [
            'ROLE_ADMIN' => 'Администратор',
            'ROLE_DOCTOR' => 'Врач',
            'ROLE_RECEPTIONIST' => 'Регистратор',
            'ROLE_NURSE' => 'Медсестра',
            'ROLE_PATIENT' => 'Пациент',
            'ROLE_USER' => 'Пользователь'
        ];

        return $roleNames[$role] ?? $role;
    }

    public function serialize(): string
    {
        return serialize([
            $this->id,
            $this->email,
            $this->password,
            $this->isActive
        ]);
    }

    public function unserialize($serialized): void
    {
        [
            $this->id,
            $this->email,
            $this->password,
            $this->isActive
        ] = unserialize($serialized, ['allowed_classes' => false]);
    }

    public function __toString(): string
    {
        return $this->getFullName() . ' (' . $this->email . ')';
    }

    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        if ($this->password !== $user->getPassword()) {
            return false;
        }

        if ($this->email !== $user->getUserIdentifier()) {
            return false;
        }

        if ($this->isActive !== $user->isActive()) {
            return false;
        }

        return true;
    }

    public function getProfileCompletion(): int
    {
        $completed = 0;
        $total = 6;

        if (!empty($this->email)) $completed++;
        if (!empty($this->firstName)) $completed++;
        if (!empty($this->lastName)) $completed++;
        if (!empty($this->password)) $completed++;
        if ($this->doctor !== null || $this->patient !== null) $completed++;
        if (!empty($this->roles)) $completed++;

        return (int) round(($completed / $total) * 100);
    }

    public function hasProfilePicture(): bool
    {
        return false;
    }

    public function getInitials(): string
    {
        $firstNameInitial = $this->firstName ? mb_substr($this->firstName, 0, 1) : '';
        $lastNameInitial = $this->lastName ? mb_substr($this->lastName, 0, 1) : '';
        
        return mb_strtoupper($firstNameInitial . $lastNameInitial);
    }
}