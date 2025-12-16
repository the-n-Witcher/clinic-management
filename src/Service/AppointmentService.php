<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\Doctor;
use App\Entity\Patient;
use App\Repository\AppointmentRepository;
use App\Repository\DoctorRepository;
use App\Repository\PatientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class AppointmentService
{
    private EntityManagerInterface $entityManager;
    private AppointmentRepository $appointmentRepository;
    private DoctorRepository $doctorRepository;
    private PatientRepository $patientRepository;
    private ValidatorInterface $validator;
    private LoggerInterface $logger;
    private AuditLogger $auditLogger;
    private MailerInterface $mailer;
    private UrlGeneratorInterface $urlGenerator;
    private FilesystemAdapter $cache;
    private NotificationService $notificationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        AppointmentRepository $appointmentRepository,
        DoctorRepository $doctorRepository,
        PatientRepository $patientRepository,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        AuditLogger $auditLogger,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
        NotificationService $notificationService
    ) {
        $this->entityManager = $entityManager;
        $this->appointmentRepository = $appointmentRepository;
        $this->doctorRepository = $doctorRepository;
        $this->patientRepository = $patientRepository;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->auditLogger = $auditLogger;
        $this->mailer = $mailer;
        $this->urlGenerator = $urlGenerator;
        $this->notificationService = $notificationService;
        $this->cache = new FilesystemAdapter();
    }

    public function createAppointment(
        Patient $patient,
        Doctor $doctor,
        \DateTimeInterface $startTime,
        string $reason,
        ?string $notes = null,
        ?string $createdBy = null
    ): Appointment {
        $startDateTime = \DateTime::createFromInterface($startTime);
        $duration = $doctor->getConsultationDuration();
        $endDateTime = clone $startDateTime;
        $endDateTime->modify("+{$duration} minutes");

        $conflicts = $this->appointmentRepository->findConflictingAppointments(
            $doctor, 
            $startDateTime, 
            $endDateTime
        );
        
        if (!empty($conflicts)) {
            throw new \RuntimeException('Doctor has conflicting appointment at this time.');
        }

        $appointment = new Appointment();
        $appointment->setPatient($patient);
        $appointment->setDoctor($doctor);
        $appointment->setStartTime($startDateTime);
        $appointment->setEndTime($endDateTime);
        $appointment->setReason($reason);
        $appointment->setNotes($notes);
        $appointment->setStatus(Appointment::STATUS_SCHEDULED);
        
        if ($createdBy) {
            $appointment->setCreatedBy($createdBy);
        }

        $errors = $this->validator->validate($appointment);
        if (count($errors) > 0) {
            throw new \RuntimeException((string) $errors);
        }

        $this->entityManager->persist($appointment);
        $this->entityManager->flush();

        $this->clearAppointmentCache($doctor, $startDateTime);

        $this->auditLogger->log(
            'APPOINTMENT_CREATED',
            [
                'appointment_id' => $appointment->getId(),
                'patient_id' => $patient->getId(),
                'patient_name' => $patient->getFullName(),
                'doctor_id' => $doctor->getId(),
                'doctor_name' => $doctor->getFullName(),
                'start_time' => $startDateTime->format('Y-m-d H:i:s'),
                'reason' => $reason
            ],
            'appointment',
            $appointment->getId()
        );

        $this->logger->info('Appointment created', [
            'id' => $appointment->getId(),
            'patient' => $patient->getFullName(),
            'doctor' => $doctor->getFullName(),
            'time' => $startDateTime->format('Y-m-d H:i:s')
        ]);

        return $appointment;
    }

    public function updateAppointment(
        Appointment $appointment,
        array $data,
        ?string $updatedBy = null
    ): Appointment {
        $oldData = [
            'doctor_id' => $appointment->getDoctor()->getId(),
            'start_time' => $appointment->getStartTime()->format('Y-m-d H:i:s'),
            'end_time' => $appointment->getEndTime()->format('Y-m-d H:i:s'),
            'status' => $appointment->getStatus(),
            'reason' => $appointment->getReason()
        ];

        if (isset($data['doctor']) && $data['doctor'] instanceof Doctor) {
            $newDoctor = $data['doctor'];
            if ($newDoctor->getId() !== $appointment->getDoctor()->getId()) {
                $startTime = $data['start_time'] ?? $appointment->getStartTime();
                if (!$this->isDoctorAvailable($newDoctor, $startTime, $appointment)) {
                    throw new \RuntimeException('New doctor is not available at the selected time.');
                }
                $appointment->setDoctor($newDoctor);
            }
        }

        if (isset($data['start_time']) && $data['start_time'] instanceof \DateTimeInterface) {
            $newStartTime = $data['start_time'];
            $doctor = $appointment->getDoctor();
            $duration = $doctor->getConsultationDuration();
            $newEndTime = \DateTime::createFromInterface($newStartTime);
            $newEndTime->modify("+{$duration} minutes");

            $conflicts = $this->appointmentRepository->findConflictingAppointments(
                $doctor,
                $newStartTime,
                $newEndTime,
                $appointment
            );

            if (!empty($conflicts)) {
                throw new \RuntimeException('Doctor has conflicting appointment at the new time.');
            }

            $appointment->setStartTime($newStartTime);
            $appointment->setEndTime($newEndTime);
        }

        if (isset($data['status'])) {
            $appointment->setStatus($data['status']);
        }
        if (isset($data['reason'])) {
            $appointment->setReason($data['reason']);
        }
        if (isset($data['notes'])) {
            $appointment->setNotes($data['notes']);
        }
        if ($updatedBy) {
            $appointment->setUpdatedBy($updatedBy);
        }

        $errors = $this->validator->validate($appointment);
        if (count($errors) > 0) {
            throw new \RuntimeException((string) $errors);
        }

        $this->entityManager->flush();

        $this->clearAppointmentCache($appointment->getDoctor(), $appointment->getStartTime());

        $newData = [
            'doctor_id' => $appointment->getDoctor()->getId(),
            'start_time' => $appointment->getStartTime()->format('Y-m-d H:i:s'),
            'end_time' => $appointment->getEndTime()->format('Y-m-d H:i:s'),
            'status' => $appointment->getStatus(),
            'reason' => $appointment->getReason()
        ];

        $this->auditLogger->logMedicalDataChange(
            'APPOINTMENT_UPDATED',
            $oldData,
            $newData,
            'appointment',
            $appointment->getId()
        );

        if (isset($data['doctor']) || isset($data['start_time'])) {
            $this->notificationService->sendAppointmentUpdated($appointment);
        }

        return $appointment;
    }

    public function cancelAppointment(
        Appointment $appointment,
        string $reason,
        ?string $cancelledBy = null
    ): void {
        $oldStatus = $appointment->getStatus();
        $appointment->setStatus(Appointment::STATUS_CANCELLED);
        $appointment->setCancellationReason($reason);
        $appointment->setCancelledBy($cancelledBy);
        $appointment->setCancelledAt(new \DateTime());

        $this->entityManager->flush();

        $this->auditLogger->log(
            'APPOINTMENT_CANCELLED',
            [
                'appointment_id' => $appointment->getId(),
                'old_status' => $oldStatus,
                'reason' => $reason,
                'cancelled_by' => $cancelledBy
            ],
            'appointment',
            $appointment->getId()
        );

        $this->logger->info('Appointment cancelled', [
            'id' => $appointment->getId(),
            'reason' => $reason
        ]);

        $this->notificationService->sendAppointmentCancelled($appointment, $reason);
    }

    public function getAvailableSlots(
        Doctor $doctor,
        \DateTimeInterface $date,
        bool $useCache = true
    ): array {
        $cacheKey = "doctor_slots_{$doctor->getId()}_{$date->format('Y-m-d')}";

        if ($useCache) {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($doctor, $date) {
                $item->expiresAfter(300);
                
                $slots = $this->appointmentRepository->findAvailableSlots($doctor, $date);
                
                $formattedSlots = [];
                foreach ($slots as $slot) {
                    $formattedSlots[] = [
                        'start' => $slot['start']->format('Y-m-d H:i:s'),
                        'end' => $slot['end']->format('Y-m-d H:i:s'),
                        'display' => $slot['start']->format('H:i') . ' - ' . $slot['end']->format('H:i')
                    ];
                }
                
                return $formattedSlots;
            });
        }

        $slots = $this->appointmentRepository->findAvailableSlots($doctor, $date);
        
        $formattedSlots = [];
        foreach ($slots as $slot) {
            $formattedSlots[] = [
                'start' => $slot['start']->format('Y-m-d H:i:s'),
                'end' => $slot['end']->format('Y-m-d H:i:s'),
                'display' => $slot['start']->format('H:i') . ' - ' . $slot['end']->format('H:i')
            ];
        }
        
        return $formattedSlots;
    }

    public function isDoctorAvailable(
        Doctor $doctor,
        \DateTimeInterface $dateTime,
        ?Appointment $exclude = null
    ): bool {
        $dayOfWeek = strtolower($dateTime->format('l'));
        $time = $dateTime->format('H:i');
        $schedule = $doctor->getSchedule();

        if (!isset($schedule[$dayOfWeek])) {
            return false;
        }

        $isInWorkingHours = false;
        foreach ($schedule[$dayOfWeek] as $period) {
            if ($time >= $period['start'] && $time <= $period['end']) {
                $isInWorkingHours = true;
                break;
            }
        }

        if (!$isInWorkingHours) {
            return false;
        }

        $duration = $doctor->getConsultationDuration();
        $endTime = \DateTime::createFromInterface($dateTime);
        $endTime->modify("+{$duration} minutes");

        $conflicts = $this->appointmentRepository->findConflictingAppointments(
            $doctor,
            $dateTime,
            $endTime,
            $exclude
        );

        return empty($conflicts);
    }

    public function sendAppointmentReminders(): int
    {
        $appointments = $this->appointmentRepository->findAppointmentsNeedingReminder();
        $sentCount = 0;

        foreach ($appointments as $appointment) {
            try {
                $this->notificationService->sendAppointmentReminder($appointment);
                $appointment->setReminderSent(true);
                $sentCount++;
                
                $this->logger->info('Appointment reminder sent', [
                    'appointment_id' => $appointment->getId(),
                    'patient' => $appointment->getPatient()->getEmail(),
                    'time' => $appointment->getStartTime()->format('Y-m-d H:i:s')
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to send appointment reminder', [
                    'appointment_id' => $appointment->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($sentCount > 0) {
            $this->entityManager->flush();
        }

        return $sentCount;
    }

    private function sendAppointmentCreatedNotifications(Appointment $appointment): void
    {
     
    }

    private function sendAppointmentUpdatedNotification(Appointment $appointment): void
    {
    }

    private function clearAppointmentCache(Doctor $doctor, \DateTimeInterface $date): void
    {
        $cacheKey = "doctor_slots_{$doctor->getId()}_{$date->format('Y-m-d')}";
        $this->cache->delete($cacheKey);
        
        $weekStart = \DateTime::createFromInterface($date);
        $weekStart->modify('monday this week');
        for ($i = 0; $i < 7; $i++) {
            $day = clone $weekStart;
            $day->modify("+{$i} days");
            $dayCacheKey = "doctor_slots_{$doctor->getId()}_{$day->format('Y-m-d')}";
            $this->cache->delete($dayCacheKey);
        }
    }

    public function getDoctorScheduleForWeek(
        Doctor $doctor,
        \DateTimeInterface $weekStart,
        bool $includeBooked = false
    ): array {
        $schedule = [];
        
        $currentDate = \DateTime::createFromInterface($weekStart);
        $currentDate->setTime(0, 0, 0);
        
        for ($i = 0; $i < 7; $i++) {
            $date = clone $currentDate;
            $date->modify("+{$i} days");
            
            $dayOfWeek = strtolower($date->format('l'));
            $workingHours = $doctor->getSchedule()[$dayOfWeek] ?? [];
            
            if (empty($workingHours)) {
                $schedule[$date->format('Y-m-d')] = [
                    'date' => clone $date,
                    'working_hours' => [],
                    'available_slots' => [],
                    'appointments' => []
                ];
                continue;
            }
            
            $availableSlots = $this->getAvailableSlots($doctor, $date, false);
            
            if ($includeBooked) {
                $appointments = $this->appointmentRepository->createQueryBuilder('a')
                    ->where('a.doctor = :doctor')
                    ->andWhere('DATE(a.startTime) = :date')
                    ->andWhere('a.status NOT IN (:cancelled)')
                    ->setParameter('doctor', $doctor)
                    ->setParameter('date', $date->format('Y-m-d'))
                    ->setParameter('cancelled', Appointment::STATUS_CANCELLED)
                    ->orderBy('a.startTime', 'ASC')
                    ->getQuery()
                    ->getResult();
            } else {
                $appointments = [];
            }
            
            $schedule[$date->format('Y-m-d')] = [
                'date' => clone $date,
                'working_hours' => $workingHours,
                'available_slots' => $availableSlots,
                'appointments' => $appointments
            ];
        }
        
        return $schedule;
    }

    public function findAlternativeSlots(
        Doctor $originalDoctor,
        \DateTimeInterface $originalTime,
        int $lookAheadDays = 7
    ): array {
        $alternatives = [];
        
        $date = \DateTime::createFromInterface($originalTime);
        $date->setTime(0, 0, 0);
        
        $slots = $this->getAvailableSlots($originalDoctor, $date, false);
        if (!empty($slots)) {
            $alternatives['same_doctor'] = [
                'doctor' => $originalDoctor,
                'slots' => $slots
            ];
        }
        
        $otherDoctors = $this->doctorRepository->findBy([
            'specialization' => $originalDoctor->getSpecialization()
        ]);
        
        foreach ($otherDoctors as $doctor) {
            if ($doctor->getId() === $originalDoctor->getId()) {
                continue;
            }
            
            $slots = $this->getAvailableSlots($doctor, $date, false);
            if (!empty($slots)) {
                if (!isset($alternatives['other_doctors'])) {
                    $alternatives['other_doctors'] = [];
                }
                $alternatives['other_doctors'][] = [
                    'doctor' => $doctor,
                    'slots' => $slots
                ];
            }
        }
        
        $futureSlots = [];
        for ($i = 1; $i <= $lookAheadDays; $i++) {
            $futureDate = clone $date;
            $futureDate->modify("+{$i} days");
            
            $slots = $this->getAvailableSlots($originalDoctor, $futureDate, false);
            if (!empty($slots)) {
                $futureSlots[$futureDate->format('Y-m-d')] = $slots;
            }
        }
        
        if (!empty($futureSlots)) {
            $alternatives['future_days'] = [
                'doctor' => $originalDoctor,
                'slots_by_day' => $futureSlots
            ];
        }
        
        return $alternatives;
    }

    public function generateAppointmentReport(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $filters = []
    ): array {
        $report = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'summary' => [],
            'by_doctor' => [],
            'by_day' => [],
            'by_hour' => [],
            'trends' => []
        ];

        $report['summary'] = $this->appointmentRepository->getStatistics($startDate, $endDate);

        $doctors = $this->doctorRepository->findAll();
        foreach ($doctors as $doctor) {
            $stats = $this->appointmentRepository->getStatistics($startDate, $endDate, $doctor);
            if ($stats['total'] > 0) {
                $report['by_doctor'][] = [
                    'doctor' => $doctor->getFullName(),
                    'specialization' => $doctor->getSpecialization(),
                    'stats' => $stats
                ];
            }
        }

        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('date', 'date');
        $rsm->addScalarResult('count', 'count');

        $sql = "
            SELECT DATE(start_time) as date, COUNT(*) as count
            FROM appointments
            WHERE start_time BETWEEN :start AND :end
            AND status NOT IN ('cancelled')
            GROUP BY DATE(start_time)
            ORDER BY date
        ";

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('start', $startDate);
        $query->setParameter('end', $endDate);
        
        $report['by_day'] = $query->getResult();

        $rsm2 = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm2->addScalarResult('hour', 'hour');
        $rsm2->addScalarResult('count', 'count');

        $sql2 = "
            SELECT HOUR(start_time) as hour, COUNT(*) as count
            FROM appointments
            WHERE start_time BETWEEN :start AND :end
            AND status NOT IN ('cancelled')
            GROUP BY HOUR(start_time)
            ORDER BY hour
        ";

        $query2 = $this->entityManager->createNativeQuery($sql2, $rsm2);
        $query2->setParameter('start', $startDate);
        $query2->setParameter('end', $endDate);
        
        $report['by_hour'] = $query2->getResult();

        $report['trends'] = $this->appointmentRepository->getAppointmentTrends(6);

        return $report;
    }

    public function checkAndMarkNoShows(): int
    {
        $now = new \DateTime();
        $cutoffTime = clone $now;
        $cutoffTime->modify('-30 minutes'); 

        $noShowAppointments = $this->appointmentRepository->createQueryBuilder('a')
            ->where('a.startTime < :cutoffTime')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere('a.noShowMarked = false')
            ->setParameter('cutoffTime', $cutoffTime)
            ->setParameter('statuses', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED])
            ->getQuery()
            ->getResult();

        $markedCount = 0;
        foreach ($noShowAppointments as $appointment) {
            $appointment->setStatus(Appointment::STATUS_NO_SHOW);
            $appointment->setNoShowMarked(true);
            $markedCount++;

            $this->auditLogger->log(
                'APPOINTMENT_NO_SHOW',
                [
                    'appointment_id' => $appointment->getId(),
                    'patient_id' => $appointment->getPatient()->getId(),
                    'doctor_id' => $appointment->getDoctor()->getId(),
                    'scheduled_time' => $appointment->getStartTime()->format('Y-m-d H:i:s')
                ],
                'appointment',
                $appointment->getId()
            );

            $this->logger->warning('Appointment marked as no-show', [
                'id' => $appointment->getId(),
                'patient' => $appointment->getPatient()->getFullName(),
                'doctor' => $appointment->getDoctor()->getFullName(),
                'time' => $appointment->getStartTime()->format('Y-m-d H:i:s')
            ]);
        }

        if ($markedCount > 0) {
            $this->entityManager->flush();
        }

        return $markedCount;
    }

    public function completeAppointment(
        Appointment $appointment,
        ?string $completedBy = null
    ): void {
        $appointment->setStatus(Appointment::STATUS_COMPLETED);
        $appointment->setCompletedAt(new \DateTime());
        
        if ($completedBy) {
            $appointment->setUpdatedBy($completedBy);
        }

        $this->entityManager->flush();

        $this->auditLogger->log(
            'APPOINTMENT_COMPLETED',
            [
                'appointment_id' => $appointment->getId(),
                'patient_id' => $appointment->getPatient()->getId(),
                'doctor_id' => $appointment->getDoctor()->getId(),
                'completed_by' => $completedBy
            ],
            'appointment',
            $appointment->getId()
        );
    }
}