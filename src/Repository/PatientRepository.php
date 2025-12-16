<?php
// src/Repository/PatientRepository.php

namespace App\Repository;

use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Patient>
 */
class PatientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Patient::class);
    }

    public function save(Patient $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Patient $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function search(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.firstName LIKE :query')
            ->orWhere('p.lastName LIKE :query')
            ->orWhere('p.middleName LIKE :query')
            ->orWhere('p.medicalNumber LIKE :query')
            ->orWhere('p.email LIKE :query')
            ->orWhere('p.phone LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.lastName', 'ASC')
            ->addOrderBy('p.firstName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('p');

        if (!empty($filters['name'])) {
            $qb->andWhere('CONCAT(p.lastName, \' \', p.firstName, \' \', COALESCE(p.middleName, \'\')) LIKE :name')
                ->setParameter('name', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['medicalNumber'])) {
            $qb->andWhere('p.medicalNumber LIKE :medicalNumber')
                ->setParameter('medicalNumber', '%' . $filters['medicalNumber'] . '%');
        }

        if (!empty($filters['gender'])) {
            $qb->andWhere('p.gender = :gender')
                ->setParameter('gender', $filters['gender']);
        }

        if (!empty($filters['minAge']) && !empty($filters['maxAge'])) {
            $minDate = new \DateTime();
            $minDate->modify('-' . $filters['maxAge'] . ' years');
            $maxDate = new \DateTime();
            $maxDate->modify('-' . $filters['minAge'] . ' years');
            
            $qb->andWhere('p.dateOfBirth BETWEEN :minDate AND :maxDate')
                ->setParameter('minDate', $minDate)
                ->setParameter('maxDate', $maxDate);
        }

        if (!empty($filters['hasAllergy'])) {
            $qb->andWhere('JSON_CONTAINS(p.allergies, :allergy) = 1')
                ->setParameter('allergy', json_encode($filters['hasAllergy']));
        }

        if (!empty($filters['hasChronicDisease'])) {
            $qb->andWhere('JSON_CONTAINS(p.chronicDiseases, :disease) = 1')
                ->setParameter('disease', json_encode($filters['hasChronicDisease']));
        }

        return $qb->orderBy('p.lastName', 'ASC')
            ->addOrderBy('p.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getStatistics(): array
    {
        return [
            'total' => $this->count([]),
            'byGender' => $this->getCountByGender(),
            'byAgeGroup' => $this->getCountByAgeGroup(),
            'newThisMonth' => $this->getNewThisMonth(),
        ];
    }

    private function getCountByGender(): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.gender, COUNT(p.id) as count')
            ->groupBy('p.gender');

        $result = $qb->getQuery()->getResult();
        $formatted = [];
        
        foreach ($result as $row) {
            $formatted[$row['gender']] = $row['count'];
        }

        return $formatted;
    }

    private function getCountByAgeGroup(): array
    {
        $ageGroups = [
            '0-18' => [0, 18],
            '19-30' => [19, 30],
            '31-45' => [31, 45],
            '46-60' => [46, 60],
            '61+' => [61, 150]
        ];

        $result = [];
        foreach ($ageGroups as $group => $range) {
            $minDate = new \DateTime();
            $minDate->modify('-' . $range[1] . ' years');
            $maxDate = new \DateTime();
            $maxDate->modify('-' . $range[0] . ' years');

            $count = $this->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->where('p.dateOfBirth BETWEEN :minDate AND :maxDate')
                ->setParameter('minDate', $minDate)
                ->setParameter('maxDate', $maxDate)
                ->getQuery()
                ->getSingleScalarResult();

            $result[$group] = $count;
        }

        return $result;
    }

    private function getNewThisMonth(): int
    {
        $startOfMonth = new \DateTime('first day of this month');
        $startOfMonth->setTime(0, 0, 0);
        $endOfMonth = new \DateTime('last day of this month');
        $endOfMonth->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getPatientsWithUpcomingAppointments(): array
    {
        $tomorrow = new \DateTime('tomorrow');
        $tomorrow->setTime(0, 0, 0);
        $dayAfterTomorrow = clone $tomorrow;
        $dayAfterTomorrow->modify('+1 day');

        return $this->createQueryBuilder('p')
            ->select('p', 'a')
            ->innerJoin('p.appointments', 'a')
            ->where('a.startTime BETWEEN :start AND :end')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('start', $tomorrow)
            ->setParameter('end', $dayAfterTomorrow)
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPatientsWithOverdueInvoices(): array
    {
        $today = new \DateTime();

        return $this->createQueryBuilder('p')
            ->select('p', 'i')
            ->innerJoin('p.invoices', 'i')
            ->where('i.dueDate < :today')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('today', $today)
            ->setParameter('statuses', ['pending', 'partially_paid'])
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPatientsByDoctor(int $doctorId, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->select('p', 'COUNT(a.id) as appointment_count')
            ->innerJoin('p.appointments', 'a')
            ->where('a.doctor = :doctorId')
            ->andWhere('a.startTime BETWEEN :startDate AND :endDate')
            ->andWhere('a.status = :completed')
            ->setParameter('doctorId', $doctorId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('completed', 'completed')
            ->groupBy('p.id')
            ->orderBy('appointment_count', 'DESC')
            ->getQuery()
            ->getResult();
    }
}