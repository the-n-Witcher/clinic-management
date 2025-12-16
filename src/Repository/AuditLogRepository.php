<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function save(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByFilters(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if (!empty($filters['entity_type'])) {
            $qb->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $filters['entity_type']);
        }

        if (!empty($filters['action'])) {
            $qb->andWhere('a.action LIKE :action')
                ->setParameter('action', '%' . $filters['action'] . '%');
        }

        if (!empty($filters['username'])) {
            $qb->andWhere('a.username LIKE :username')
                ->setParameter('username', '%' . $filters['username'] . '%');
        }

        if (!empty($filters['start_date'])) {
            $startDate = new \DateTime($filters['start_date']);
            $qb->andWhere('a.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if (!empty($filters['end_date'])) {
            $endDate = new \DateTime($filters['end_date']);
            $endDate->setTime(23, 59, 59);
            $qb->andWhere('a.createdAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        if (!empty($filters['ip_address'])) {
            $qb->andWhere('a.ipAddress LIKE :ipAddress')
                ->setParameter('ipAddress', '%' . $filters['ip_address'] . '%');
        }

        return $qb->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        if (!empty($filters['entity_type'])) {
            $qb->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $filters['entity_type']);
        }

        if (!empty($filters['action'])) {
            $qb->andWhere('a.action LIKE :action')
                ->setParameter('action', '%' . $filters['action'] . '%');
        }

        if (!empty($filters['username'])) {
            $qb->andWhere('a.username LIKE :username')
                ->setParameter('username', '%' . $filters['username'] . '%');
        }

        if (!empty($filters['start_date'])) {
            $startDate = new \DateTime($filters['start_date']);
            $qb->andWhere('a.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if (!empty($filters['end_date'])) {
            $endDate = new \DateTime($filters['end_date']);
            $endDate->setTime(23, 59, 59);
            $qb->andWhere('a.createdAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getRecentActivity(int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getActivityByEntityType(string $entityType, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.entityType = :entityType')
            ->setParameter('entityType', $entityType)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getUserActivity(string $username, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.username = :username')
            ->setParameter('username', $username)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select([
                'COUNT(a.id) as total_logs',
                'COUNT(DISTINCT a.username) as unique_users',
                'COUNT(DISTINCT a.entityType) as unique_entities',
                'a.entityType',
                'COUNT(CASE WHEN a.entityType = :patient THEN 1 END) as patient_logs',
                'COUNT(CASE WHEN a.entityType = :appointment THEN 1 END) as appointment_logs',
                'COUNT(CASE WHEN a.entityType = :doctor THEN 1 END) as doctor_logs',
                'COUNT(CASE WHEN a.entityType = :medical_record THEN 1 END) as medical_record_logs',
                'COUNT(CASE WHEN a.entityType = :invoice THEN 1 END) as invoice_logs'
            ])
            ->where('a.createdAt BETWEEN :startDate AND :endDate')
            ->groupBy('a.entityType')
            ->orderBy('total_logs', 'DESC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('patient', 'patient')
            ->setParameter('appointment', 'appointment')
            ->setParameter('doctor', 'doctor')
            ->setParameter('medical_record', 'medical_record')
            ->setParameter('invoice', 'invoice');

        $result = $qb->getQuery()->getResult();

        $summary = [
            'total_logs' => 0,
            'unique_users' => 0,
            'unique_entities' => 0,
            'by_entity_type' => [],
            'by_action' => [],
            'top_users' => [],
            'activity_by_hour' => []
        ];

        foreach ($result as $row) {
            $summary['total_logs'] += (int) $row['total_logs'];
            $summary['by_entity_type'][$row['entityType']] = (int) $row['total_logs'];
        }

        $summary['unique_users'] = $this->getUniqueUsersCount($startDate, $endDate);
        $summary['unique_entities'] = count($summary['by_entity_type']);
        $summary['by_action'] = $this->getActivityByAction($startDate, $endDate);
        $summary['top_users'] = $this->getTopUsers($startDate, $endDate, 10);
        $summary['activity_by_hour'] = $this->getActivityByHour($startDate, $endDate);

        return $summary;
    }

    private function getUniqueUsersCount(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.username)')
            ->where('a.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getActivityByAction(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('a.action, COUNT(a.id) as count')
            ->where('a.createdAt BETWEEN :startDate AND :endDate')
            ->groupBy('a.action')
            ->orderBy('count', 'DESC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        $actions = [];
        foreach ($result as $row) {
            $actions[$row['action']] = (int) $row['count'];
        }

        return $actions;
    }

    private function getTopUsers(\DateTimeInterface $startDate, \DateTimeInterface $endDate, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.username, COUNT(a.id) as activity_count')
            ->where('a.createdAt BETWEEN :startDate AND :endDate')
            ->groupBy('a.username')
            ->orderBy('activity_count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();
    }

    private function getActivityByHour(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('hour', 'hour');
        $rsm->addScalarResult('count', 'count');

        $sql = "
            SELECT HOUR(created_at) as hour, COUNT(*) as count
            FROM audit_logs
            WHERE created_at BETWEEN :startDate AND :endDate
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('startDate', $startDate);
        $query->setParameter('endDate', $endDate);
        
        return $query->getResult();
    }

    public function getDailyActivity(\DateTimeInterface $date): array
    {
        $startOfDay = \DateTime::createFromInterface($date);
        $startOfDay->setTime(0, 0, 0);
        $endOfDay = \DateTime::createFromInterface($date);
        $endOfDay->setTime(23, 59, 59);

        return $this->createQueryBuilder('a')
            ->where('a.createdAt BETWEEN :start AND :end')
            ->orderBy('a.createdAt', 'DESC')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->getQuery()
            ->getResult();
    }

    public function findSensitiveDataAccess(string $username = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.entityType IN (:sensitiveTypes)')
            ->setParameter('sensitiveTypes', [
                'patient', 
                'medical_record', 
                'prescription',
                'appointment'
            ])
            ->orderBy('a.createdAt', 'DESC');

        if ($username) {
            $qb->andWhere('a.username = :username')
                ->setParameter('username', $username);
        }

        return $qb->getQuery()->getResult();
    }

    public function getFailedLoginAttempts(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.createdAt BETWEEN :startDate AND :endDate')
            ->andWhere('a.action LIKE :failedLogin')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('failedLogin', '%FAILED_LOGIN%')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function cleanupOldLogs(int $daysToKeep = 90): int
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify('-' . $daysToKeep . ' days');

        $qb = $this->createQueryBuilder('a')
            ->delete()
            ->where('a.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate);

        return $qb->getQuery()->execute();
    }

    public function searchInData(string $searchTerm, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.data LIKE :searchTerm')
            ->orWhere('a.username LIKE :searchTerm')
            ->orWhere('a.action LIKE :searchTerm')
            ->orWhere('a.entityType LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getEntityAuditLog(string $entityType, int $entityId, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.entityType = :entityType')
            ->andWhere('a.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function exportLogs(\DateTimeInterface $startDate, \DateTimeInterface $endDate, string $format = 'json'): array
    {
        $logs = $this->createQueryBuilder('a')
            ->where('a.createdAt BETWEEN :startDate AND :endDate')
            ->orderBy('a.createdAt', 'DESC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        $exportData = [];
        foreach ($logs as $log) {
            $exportData[] = [
                'id' => $log->getId(),
                'timestamp' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
                'action' => $log->getAction(),
                'entity_type' => $log->getEntityType(),
                'entity_id' => $log->getEntityId(),
                'username' => $log->getUsername(),
                'ip_address' => $log->getIpAddress(),
                'user_agent' => $log->getUserAgent(),
                'data' => $log->getData()
            ];
        }

        return $exportData;
    }
}