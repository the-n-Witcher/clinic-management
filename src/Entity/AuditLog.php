<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: "App\Repository\AuditLogRepository")]
#[ORM\Table(name: "audit_logs")]
#[ORM\Index(name: "idx_entity", columns: ["entity_type", "entity_id"])]
#[ORM\Index(name: "idx_action", columns: ["action"])]
#[ORM\Index(name: "idx_created_at", columns: ["created_at"])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100)]
    private string $action;

    #[ORM\Column(type: "string", length: 50)]
    private string $entityType;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(type: "json")]
    private array $data = [];

    #[ORM\Column(type: "string", length: 100)]
    private string $username;

    #[ORM\Column(type: "string", length: 45)]
    private string $ipAddress;

    #[ORM\Column(type: "string", length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $action): self { $this->action = $action; return $this; }
    public function getEntityType(): string { return $this->entityType; }
    public function setEntityType(string $entityType): self { $this->entityType = $entityType; return $this; }
    public function getEntityId(): ?int { return $this->entityId; }
    public function setEntityId(?int $entityId): self { $this->entityId = $entityId; return $this; }
    public function getData(): array { return $this->data; }
    public function setData(array $data): self { $this->data = $data; return $this; }
    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): self { $this->username = $username; return $this; }
    public function getIpAddress(): string { return $this->ipAddress; }
    public function setIpAddress(string $ipAddress): self { $this->ipAddress = $ipAddress; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $userAgent): self { $this->userAgent = $userAgent; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getFormattedData(): string
    {
        return json_encode($this->data, JSON_PRETTY_PRINT);
    }

    public function getShortDescription(): string
    {
        $entityInfo = $this->entityId ? "{$this->entityType} #{$this->entityId}" : $this->entityType;
        return "{$this->action} - {$entityInfo} by {$this->username}";
    }
}