<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AuditLogger
{
    private EntityManagerInterface $entityManager;
    private TokenStorageInterface $tokenStorage;

    public function __construct(
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage
    ) {
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
    }
    public function log(string $action, array $data, string $entityType, ?int $entityId = null): void
    {
        $user = null;
        $username = 'system';
        
        if ($this->tokenStorage->getToken()) {
            $user = $this->tokenStorage->getToken()->getUser();
            
            if ($user && is_object($user)) {
                if (method_exists($user, 'getUserIdentifier')) {
                    $username = $user->getUserIdentifier();
                } elseif (method_exists($user, 'getUsername')) {
                    $username = $user->getUsername();
                } else {
                    $username = (string) $user;
                }
            }
        }

        $auditLog = new AuditLog();
        $auditLog->setAction($action);
        $auditLog->setEntityType($entityType);
        $auditLog->setEntityId($entityId);
        $auditLog->setData($data);
        $auditLog->setUsername($username);
        $auditLog->setIpAddress($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $auditLog->setUserAgent($_SERVER['HTTP_USER_AGENT'] ?? '');
        $auditLog->setCreatedAt(new \DateTime());

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();
    }

    public function logMedicalDataChange(
        string $action, 
        array $oldData, 
        array $newData, 
        string $entityType, 
        int $entityId
    ): void {
        $diff = $this->calculateDiff($oldData, $newData);
        
        $this->log($action, [
            'entity_id' => $entityId,
            'old_data' => $oldData,
            'new_data' => $newData,
            'diff' => $diff
        ], $entityType, $entityId);
    }

    private function calculateDiff(array $oldData, array $newData): array
    {
        $diff = [];
        
        $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
        
        foreach ($allKeys as $key) {
            $oldValue = $oldData[$key] ?? null;
            $newValue = $newData[$key] ?? null;
            
            if ($oldValue !== $newValue) {
                $diff[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }
        
        return $diff;
    }
}