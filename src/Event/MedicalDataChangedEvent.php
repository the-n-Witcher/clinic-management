<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class MedicalDataChangedEvent extends Event
{
    public const NAME = 'medical.data.changed';

    private string $action;
    private array $oldData;
    private array $newData;
    private string $entityType;
    private int $entityId;

    public function __construct(
        string $action,
        array $oldData,
        array $newData,
        string $entityType,
        int $entityId
    ) {
        $this->action = $action;
        $this->oldData = $oldData;
        $this->newData = $newData;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getOldData(): array
    {
        return $this->oldData;
    }

    public function getNewData(): array
    {
        return $this->newData;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }
}