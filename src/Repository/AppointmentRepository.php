<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\Doctor;
use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    public function save(Appointment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Appointment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findConflictingAppointments(
        Doctor $doctor, 
        \DateTimeInterface $startTime, 
        \DateTimeInterface $endTime, 
        ?Appointment $exclude = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.status NOT IN (:excludedStatuses)')
            ->andWhere('(a.startTime < :endTime AND a.endTime > :startTime)')
            ->setParameter('doctor', $doctor)
            ->setParameter('excludedStatuses', [Appointment::STATUS_CANCELLED])
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime);

        if ($exclude && $exclude->getId()) {
            $qb->andWhere('a.id != :excludeId')
                ->setParameter('excludeId', $exclude->getId());
        }

        return $qb->getQuery()->getResult();
    }

    public function findAvailableSlots(Doctor $doctor, \DateTimeInterface $date, int $duration = null): array
    {
        $duration = $duration ?? $doctor->getConsultationDuration();
        $dayOfWeek = strtolower($date->format('l'));
        $schedule = $doctor->getSchedule();

        if (!isset($schedule[$dayOfWeek]) || empty($schedule[$dayOfWeek])) {
            return [];
        }

        $slots = [];
        $workingPeriods = $schedule[$dayOfWeek];

        foreach ($workingPeriods as $period) {
            $periodStart = new \DateTime($date->format('Y-m-d') . ' ' . $period['start']);
            $periodEnd = new \DateTime($date->format('Y-m-d') . ' ' . $period['end']);

            $appointments = $this->createQueryBuilder('a')
                ->where('a.doctor = :doctor')
                ->andWhere('DATE(a.startTime) = :date')
                ->andWhere('a.status NOT IN (:excludedStatuses)')
                ->setParameter('doctor', $doctor)
                ->setParameter('date', $date->format('Y-m-d'))
                ->setParameter('excludedStatuses', [Appointment::STATUS_CANCELLED])
                ->orderBy('a.startTime', 'ASC')
                ->getQuery()
                ->getResult();

            $currentTime = clone $periodStart;

            while ($currentTime < $periodEnd) {
                $slotEnd = clone $currentTime;
                $slotEnd->modify("+{$duration} minutes");

                if ($slotEnd > $periodEnd) {
                    break;
                }

                $hasConflict = false;
                foreach ($appointments as $appointment) {
                    if ($currentTime < $appointment->getEndTime() && $slotEnd > $appointment->getStartTime()) {
                        $hasConflict = true;
                        $currentTime = clone $appointment->getEndTime();
                        break;
                    }
                }

                if (!$hasConflict) {
                    $slots[] = [
                        'start' => clone $currentTime,
                        'end' => clone $slotEnd,
                        'formatted' => $currentTime->format('H:i') . ' - ' . $slotEnd->format('H:i')
                    ];
                    $currentTime->modify("+{$duration} minutes");
                }
            }
        }

        return $slots;
    }

    public function findUpcomingAppointments(int $limit = 10, bool $includeCancelled = false): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a', 'p', 'd')
            ->leftJoin('a.patient', 'p')
            ->leftJoin('a.doctor', 'd')
            ->where('a.startTime >= :now')
            ->setParameter('now', new \DateTime());

        if (!$includeCancelled) {
            $qb->andWhere('a.status NOT IN (:cancelled)')
                ->setParameter('cancelled', Appointment::STATUS_CANCELLED);
        }

        return $qb->orderBy('a.startTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAppointmentsByDateRange(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $filters = []
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->select('a', 'p', 'd')
            ->leftJoin('a.patient', 'p')
            ->leftJoin('a.doctor', 'd')
            ->where('a.startTime BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        if (!empty($filters['doctor'])) {
            $qb->andWhere('a.doctor = :doctor')
                ->setParameter('doctor', $filters['doctor']);
        }

        if (!empty($filters['patient'])) {
            $qb->andWhere('a.patient = :patient')
                ->setParameter('patient', $filters['patient']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['room'])) {
            $qb->andWhere('d.room = :room')
                ->setParameter('room', $filters['room']);
        }

        return $qb->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getStatistics(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Doctor $doctor = null,
        ?Patient $patient = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->select([
                'COUNT(a.id) as total',
                'SUM(CASE WHEN a.status = :scheduled THEN 1 ELSE 0 END) as scheduled',
                'SUM(CASE WHEN a.status = :confirmed THEN 1 ELSE 0 END) as confirmed',
                'SUM(CASE WHEN a.status = :completed THEN 1 ELSE 0 END) as completed',
                'SUM(CASE WHEN a.status = :cancelled THEN 1 ELSE 0 END) as cancelled',
                'SUM(CASE WHEN a.status = :no_show THEN 1 ELSE 0 END) as no_show',
                'AVG(TIMESTAMPDIFF(MINUTE, a.startTime, a.endTime)) as avg_duration'
            ])
            ->where('a.startTime BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('scheduled', Appointment::STATUS_SCHEDULED)
            ->setParameter('confirmed', Appointment::STATUS_CONFIRMED)
            ->setParameter('completed', Appointment::STATUS_COMPLETED)
            ->setParameter('cancelled', Appointment::STATUS_CANCELLED)
            ->setParameter('no_show', Appointment::STATUS_NO_SHOW);

        if ($doctor) {
            $qb->andWhere('a.doctor = :doctor')
                ->setParameter('doctor', $doctor);
        }

        if ($patient) {
            $qb->andWhere('a.patient = :patient')
                ->setParameter('patient', $patient);
        }

        $result = $qb->getQuery()->getSingleResult();

        $revenue = 0;
        if ($doctor && $doctor->getConsultationFee()) {
            $revenueQb = $this->createQueryBuilder('a')
                ->select('COUNT(a.id) * :fee as revenue')
                ->where('a.startTime BETWEEN :startDate AND :endDate')
                ->andWhere('a.doctor = :doctor')
                ->andWhere('a.status = :completed')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->setParameter('doctor', $doctor)
                ->setParameter('completed', Appointment::STATUS_COMPLETED)
                ->setParameter('fee', $doctor->getConsultationFee());

            $revenueResult = $revenueQb->getQuery()->getSingleScalarResult();
            $revenue = $revenueResult ?? 0;
        }

        return [
            'total' => (int) $result['total'],
            'scheduled' => (int) $result['scheduled'],
            'confirmed' => (int) $result['confirmed'],
            'completed' => (int) $result['completed'],
            'cancelled' => (int) $result['cancelled'],
            'no_show' => (int) $result['no_show'],
            'avg_duration' => round((float) $result['avg_duration'], 2),
            'revenue' => $revenue,
            'completion_rate' => $result['total'] > 0 ? 
                round(((int) $result['completed'] / (int) $result['total']) * 100, 2) : 0
        ];
    }

    public function getDailySchedule(\DateTimeInterface $date): array
    {
        $startOfDay = \DateTime::createFromInterface($date);
        $startOfDay->setTime(0, 0, 0);
        $endOfDay = \DateTime::createFromInterface($date);
        $endOfDay->setTime(23, 59, 59);

        $appointments = $this->createQueryBuilder('a')
            ->select('a', 'p', 'd', 'r')
            ->leftJoin('a.patient', 'p')
            ->leftJoin('a.doctor', 'd')
            ->leftJoin('d.room', 'r')
            ->where('a.startTime BETWEEN :start AND :end')
            ->andWhere('a.status NOT IN (:cancelled)')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->setParameter('cancelled', Appointment::STATUS_CANCELLED)
            ->orderBy('d.lastName', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        $schedule = [];
        foreach ($appointments as $appointment) {
            $doctorId = $appointment->getDoctor()->getId();
            if (!isset($schedule[$doctorId])) {
                $schedule[$doctorId] = [
                    'doctor' => $appointment->getDoctor(),
                    'appointments' => []
                ];
            }
            $schedule[$doctorId]['appointments'][] = $appointment;
        }

        return $schedule;
    }

    public function findNoShowAppointments(\DateTimeInterface $fromDate): array
    {
        return $this->createQueryBuilder('a')
            ->select('a', 'p', 'd')
            ->leftJoin('a.patient', 'p')
            ->leftJoin('a.doctor', 'd')
            ->where('a.status = :no_show')
            ->andWhere('a.startTime >= :fromDate')
            ->setParameter('no_show', Appointment::STATUS_NO_SHOW)
            ->setParameter('fromDate', $fromDate)
            ->orderBy('a.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAppointmentsNeedingReminder(): array
    {
        $now = new \DateTime();
        $reminderStart = clone $now;
        $reminderStart->modify('+23 hours');
        $reminderEnd = clone $now;
        $reminderEnd->modify('+25 hours');

        return $this->createQueryBuilder('a')
            ->select('a', 'p', 'd')
            ->leftJoin('a.patient', 'p')
            ->leftJoin('a.doctor', 'd')
            ->where('a.startTime BETWEEN :start AND :end')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere('a.reminderSent = false')
            ->setParameter('start', $reminderStart)
            ->setParameter('end', $reminderEnd)
            ->setParameter('statuses', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED])
            ->getQuery()
            ->getResult();
    }

    public function getBusiestTimes(): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('hour', 'hour');
        $rsm->addScalarResult('count', 'count');

        $sql = "
            SELECT HOUR(start_time) as hour, COUNT(*) as count
            FROM appointments
            WHERE status NOT IN ('cancelled')
            AND start_time >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY HOUR(start_time)
            ORDER BY count DESC
            LIMIT 5
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        return $query->getResult();
    }

    public function getAppointmentTrends(int $months = 6): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('month', 'month');
        $rsm->addScalarResult('total', 'total');
        $rsm->addScalarResult('completed', 'completed');
        $rsm->addScalarResult('cancelled', 'cancelled');

        $sql = "
            SELECT 
                DATE_FORMAT(start_time, '%Y-%m') as month,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM appointments
            WHERE start_time >= DATE_SUB(NOW(), INTERVAL :months MONTH)
            GROUP BY DATE_FORMAT(start_time, '%Y-%m')
            ORDER BY month DESC
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('months', $months);
        
        return $query->getResult();
    }
}