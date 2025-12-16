<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\AuditLog;
use App\Entity\Patient;
use App\Entity\Doctor;
use App\Entity\MedicalRecord;
use App\Entity\Prescription;
use App\Entity\Appointment;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DoctrineSubscriber implements EventSubscriber
{
    private TokenStorageInterface $tokenStorage;
    private array $entitiesToAudit = [];
    private array $medicalEntities = [
        Patient::class,
        MedicalRecord::class,
        Prescription::class,
        Appointment::class
    ];

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
            Events::postFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($this->isMedicalEntity($entity)) {
                $this->entitiesToAudit[] = [
                    'entity' => $entity,
                    'action' => 'CREATE',
                    'changes' => $this->getEntityChanges($entity, $em)
                ];
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($this->isMedicalEntity($entity)) {
                $changes = $uow->getEntityChangeSet($entity);
                if (!empty($changes)) {
                    $this->entitiesToAudit[] = [
                        'entity' => $entity,
                        'action' => 'UPDATE',
                        'changes' => $changes
                    ];
                }
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->isMedicalEntity($entity)) {
                $this->entitiesToAudit[] = [
                    'entity' => $entity,
                    'action' => 'DELETE',
                    'changes' => $this->getEntityData($entity)
                ];
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (empty($this->entitiesToAudit)) {
            return;
        }

        $em = $args->getObjectManager();
        $token = $this->tokenStorage->getToken();
        $username = 'system';
        
        if ($token && $token->getUser()) {
            $user = $token->getUser();
            
            if ($user instanceof User) {
                $username = $user->getUserIdentifier();
            } elseif (is_object($user)) {
                if (method_exists($user, 'getUserIdentifier')) {
                    $username = $user->getUserIdentifier();
                } elseif (method_exists($user, 'getUsername')) {
                    $username = $user->getUsername();
                } else {
                    $username = 'unknown_user';
                }
            } else {
                $username = 'unknown';
            }
        }

        foreach ($this->entitiesToAudit as $auditData) {
            $entity = $auditData['entity'];
            $action = $auditData['action'];
            $changes = $auditData['changes'];

            $auditLog = new AuditLog();
            $auditLog->setAction($action);
            $auditLog->setEntityType(get_class($entity));
            
            if (method_exists($entity, 'getId')) {
                $auditLog->setEntityId($entity->getId());
            }
            
            $auditLog->setData($changes);
            $auditLog->setUsername($username);
            $auditLog->setIpAddress($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            
            $em->persist($auditLog);
        }

        $this->entitiesToAudit = [];
        $em->flush();
    }

    private function isMedicalEntity($entity): bool
    {
        foreach ($this->medicalEntities as $medicalEntity) {
            if ($entity instanceof $medicalEntity) {
                return true;
            }
        }
        return false;
    }

    private function getEntityChanges($entity, $em): array
    {
        $changes = [];
        $classMetadata = $em->getClassMetadata(get_class($entity));
        
        foreach ($classMetadata->fieldNames as $fieldName) {
            $value = $classMetadata->getFieldValue($entity, $fieldName);
            if ($value instanceof \DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            }
            $changes[$fieldName] = $value;
        }

        return $changes;
    }

    private function getEntityData($entity): array
    {
        $data = [];
        
        if (method_exists($entity, 'getId')) {
            $data['id'] = $entity->getId();
        }
        
        if ($entity instanceof Patient) {
            $data['medical_number'] = $entity->getMedicalNumber();
            $data['name'] = $entity->getFullName();
        } elseif ($entity instanceof Doctor) {
            $data['name'] = $entity->getFullName();
            $data['specialization'] = $entity->getSpecialization();
        } elseif ($entity instanceof MedicalRecord) {
            $data['patient_id'] = $entity->getPatient()->getId();
            $data['type'] = $entity->getType();
        } elseif ($entity instanceof Prescription) {
            $data['patient_id'] = $entity->getPatient()->getId();
            $data['valid_until'] = $entity->getValidUntil()->format('Y-m-d');
        } elseif ($entity instanceof Appointment) {
            $data['patient_id'] = $entity->getPatient()->getId();
            $data['doctor_id'] = $entity->getDoctor()->getId();
            $data['start_time'] = $entity->getStartTime()->format('Y-m-d H:i:s');
        }

        return $data;
    }
}