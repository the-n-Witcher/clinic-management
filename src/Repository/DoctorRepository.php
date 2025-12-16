<?php

namespace App\Repository;

use App\Entity\Doctor;
use App\Entity\Specialization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Doctor>
 */
class DoctorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Doctor::class);
    }

    public function save(Doctor $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Doctor $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.user', 'u')
            ->addSelect('u')
            ->leftJoin('d.room', 'r')
            ->addSelect('r')
            ->where('u.isActive = true');

        if (!empty($filters['specialization'])) {
            $qb->andWhere('d.specialization = :specialization')
                ->setParameter('specialization', $filters['specialization']);
        }

        if (!empty($filters['name'])) {
            $qb->andWhere('CONCAT(d.lastName, \' \', d.firstName, \' \', COALESCE(d.middleName, \'\')) LIKE :name')
                ->setParameter('name', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['room'])) {
            $qb->andWhere('r.id = :roomId')
                ->setParameter('roomId', $filters['room']);
        }

        if (isset($filters['available_today']) && $filters['available_today']) {
            $today = new \DateTime();
            $dayOfWeek = strtolower($today->format('l'));
            $qb->andWhere('JSON_EXTRACT(d.schedule, :dayOfWeek) IS NOT NULL')
                ->andWhere('JSON_LENGTH(JSON_EXTRACT(d.schedule, :dayOfWeek)) > 0')
                ->setParameter('dayOfWeek', '$.' . $dayOfWeek);
        }

        if (!empty($filters['equipment'])) {
            $qb->leftJoin('d.equipment', 'e')
                ->andWhere('e.id = :equipmentId')
                ->setParameter('equipmentId', $filters['equipment']);
        }

        return $qb->orderBy('d.lastName', 'ASC')
            ->addOrderBy('d.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function search(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.user', 'u')
            ->addSelect('u')
            ->where('d.firstName LIKE :query')
            ->orWhere('d.lastName LIKE :query')
            ->orWhere('d.middleName LIKE :query')
            ->orWhere('d.specialization LIKE :query')
            ->orWhere('d.licenseNumber LIKE :query')
            ->andWhere('u.isActive = true')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('d.lastName', 'ASC')
            ->addOrderBy('d.firstName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAvailableDoctors(\DateTimeInterface $dateTime, string $specialization = null): array
    {
        $dayOfWeek = strtolower($dateTime->format('l'));
        $time = $dateTime->format('H:i');

        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.user', 'u')
            ->addSelect('u')
            ->leftJoin('d.room', 'r')
            ->addSelect('r')
            ->where('u.isActive = true')
            ->andWhere('JSON_EXTRACT(d.schedule, :dayOfWeek) IS NOT NULL')
            ->andWhere('JSON_LENGTH(JSON_EXTRACT(d.schedule, :dayOfWeek)) > 0')
            ->setParameter('dayOfWeek', '$.' . $dayOfWeek);

        if ($specialization) {
            $qb->andWhere('d.specialization = :specialization')
                ->setParameter('specialization', $specialization);
        }

        $doctors = $qb->getQuery()->getResult();

        $availableDoctors = [];
        foreach ($doctors as $doctor) {
            $schedule = $doctor->getSchedule();
            if (isset($schedule[$dayOfWeek])) {
                foreach ($schedule[$dayOfWeek] as $period) {
                    if ($time >= $period['start'] && $time <= $period['end']) {
                        $availableDoctors[] = $doctor;
                        break;
                    }
                }
            }
        }

        return $availableDoctors;
    }

    public function getDoctorsWithAppointmentsCount(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('d')
            ->select('d', 'COUNT(a.id) as appointmentCount')
            ->leftJoin('d.appointments', 'a')
            ->where('a.startTime BETWEEN :startDate AND :endDate')
            ->andWhere('a.status NOT IN (:cancelled)')
            ->groupBy('d.id')
            ->orderBy('appointmentCount', 'DESC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('cancelled', ['cancelled'])
            ->getQuery()
            ->getResult();
    }

    public function getSpecializations(): array
    {
        $result = $this->createQueryBuilder('d')
            ->select('DISTINCT d.specialization')
            ->orderBy('d.specialization', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'specialization');
    }

    public function getDoctorsStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select([
                'd.id',
                'd.firstName',
                'd.lastName',
                'd.specialization',
                'COUNT(a.id) as totalAppointments',
                'SUM(CASE WHEN a.status = :completed THEN 1 ELSE 0 END) as completed',
                'SUM(CASE WHEN a.status = :cancelled THEN 1 ELSE 0 END) as cancelled',
                'SUM(CASE WHEN a.status = :noShow THEN 1 ELSE 0 END) as noShow',
                'AVG(TIMESTAMPDIFF(MINUTE, a.startTime, a.endTime)) as avgDuration'
            ])
            ->leftJoin('d.appointments', 'a')
            ->where('a.startTime BETWEEN :startDate AND :endDate')
            ->groupBy('d.id')
            ->orderBy('totalAppointments', 'DESC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('completed', 'completed')
            ->setParameter('cancelled', 'cancelled')
            ->setParameter('noShow', 'no_show');

        return $qb->getQuery()->getResult();
    }

    public function findDoctorsByRoom(int $roomId): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.user', 'u')
            ->addSelect('u')
            ->where('d.room = :roomId')
            ->andWhere('u.isActive = true')
            ->setParameter('roomId', $roomId)
            ->orderBy('d.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getBusyDoctors(\DateTimeInterface $date): array
    {
        $startOfDay = \DateTime::createFromInterface($date);
        $startOfDay->setTime(0, 0, 0);
        $endOfDay = \DateTime::createFromInterface($date);
        $endOfDay->setTime(23, 59, 59);

        return $this->createQueryBuilder('d')
            ->select('d', 'COUNT(a.id) as appointmentCount')
            ->leftJoin('d.appointments', 'a')
            ->where('a.startTime BETWEEN :start AND :end')
            ->andWhere('a.status NOT IN (:cancelled)')
            ->groupBy('d.id')
            ->having('appointmentCount >= 5')
            ->orderBy('appointmentCount', 'DESC')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->setParameter('cancelled', ['cancelled'])
            ->getQuery()
            ->getResult();
    }

    public function getAvailableDoctorsForTimeSlot(\DateTimeInterface $startTime, \DateTimeInterface $endTime, string $specialization = null): array
    {
        $dayOfWeek = strtolower($startTime->format('l'));
        $startHour = $startTime->format('H:i');
        $endHour = $endTime->format('H:i');

        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.user', 'u')
            ->addSelect('u')
            ->where('u.isActive = true')
            ->andWhere('JSON_EXTRACT(d.schedule, :dayOfWeek) IS NOT NULL')
            ->setParameter('dayOfWeek', '$.' . $dayOfWeek);

        if ($specialization) {
            $qb->andWhere('d.specialization = :specialization')
                ->setParameter('specialization', $specialization);
        }

        $doctors = $qb->getQuery()->getResult();

        $availableDoctors = [];
        foreach ($doctors as $doctor) {
            $schedule = $doctor->getSchedule();
            
            $isAvailableInSchedule = false;
            if (isset($schedule[$dayOfWeek])) {
                foreach ($schedule[$dayOfWeek] as $period) {
                    if ($startHour >= $period['start'] && $endHour <= $period['end']) {
                        $isAvailableInSchedule = true;
                        break;
                    }
                }
            }

            if (!$isAvailableInSchedule) {
                continue;
            }

            $conflictingAppointments = $this->getEntityManager()
                ->getRepository(\App\Entity\Appointment::class)
                ->createQueryBuilder('a')
                ->where('a.doctor = :doctor')
                ->andWhere('a.status NOT IN (:cancelled)')
                ->andWhere('(a.startTime < :endTime AND a.endTime > :startTime)')
                ->setParameter('doctor', $doctor)
                ->setParameter('cancelled', ['cancelled'])
                ->setParameter('startTime', $startTime)
                ->setParameter('endTime', $endTime)
                ->getQuery()
                ->getResult();

            if (empty($conflictingAppointments)) {
                $availableDoctors[] = $doctor;
            }
        }

        return $availableDoctors;
    }

    public function getTopDoctorsByRevenue(\DateTimeInterface $startDate, \DateTimeInterface $endDate, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select([
                'd.id',
                'd.firstName',
                'd.lastName',
                'd.specialization',
                'COUNT(DISTINCT a.id) as appointmentCount',
                'SUM(i.amount) as totalRevenue',
                'SUM(CASE WHEN i.status = :paid THEN i.amount ELSE 0 END) as collectedRevenue'
            ])
            ->leftJoin('d.appointments', 'a')
            ->leftJoin('a.invoices', 'i')
            ->where('a.startTime BETWEEN :startDate AND :endDate')
            ->andWhere('a.status = :completed')
            ->groupBy('d.id')
            ->orderBy('totalRevenue', 'DESC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('completed', 'completed')
            ->setParameter('paid', 'paid')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function getDoctorWorkload(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('doctor_id', 'doctor_id');
        $rsm->addScalarResult('date', 'date');
        $rsm->addScalarResult('appointment_count', 'appointment_count');
        $rsm->addScalarResult('total_hours', 'total_hours');

        $sql = "
            SELECT 
                d.id as doctor_id,
                DATE(a.start_time) as date,
                COUNT(a.id) as appointment_count,
                SUM(TIMESTAMPDIFF(HOUR, a.start_time, a.end_time)) as total_hours
            FROM doctors d
            INNER JOIN appointments a ON d.id = a.doctor_id
            WHERE a.start_time BETWEEN :startDate AND :endDate
                AND a.status NOT IN ('cancelled')
            GROUP BY d.id, DATE(a.start_time)
            ORDER BY date DESC, total_hours DESC
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('startDate', $startDate);
        $query->setParameter('endDate', $endDate);
        
        return $query->getResult();
    }

    public function findDoctorsWithEquipment(int $equipmentId): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.equipment', 'e')
            ->leftJoin('d.user', 'u')
            ->addSelect('u', 'e')
            ->where('e.id = :equipmentId')
            ->andWhere('u.isActive = true')
            ->setParameter('equipmentId', $equipmentId)
            ->orderBy('d.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getDoctorAvailabilityByDay(int $doctorId, string $dayOfWeek): array
    {
        $doctor = $this->find($doctorId);
        if (!$doctor) {
            return [];
        }

        $schedule = $doctor->getSchedule();
        return $schedule[strtolower($dayOfWeek)] ?? [];
    }

    public function updateDoctorSchedule(int $doctorId, array $schedule): void
    {
        $doctor = $this->find($doctorId);
        if ($doctor) {
            $doctor->setSchedule($schedule);
            $this->getEntityManager()->flush();
        }
    }

    public function findInactiveDoctors(): array
    {
        $thirtyDaysAgo = new \DateTime();
        $thirtyDaysAgo->modify('-30 days');

        return $this->createQueryBuilder('d')
            ->leftJoin('d.user', 'u')
            ->addSelect('u')
            ->leftJoin('d.appointments', 'a')
            ->where('u.isActive = true')
            ->groupBy('d.id')
            ->having('MAX(a.startTime) < :thirtyDaysAgo OR COUNT(a.id) = 0')
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->getQuery()
            ->getResult();
    }
}