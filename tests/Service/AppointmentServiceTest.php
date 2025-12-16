<?php

namespace App\Tests\Service;

use App\Entity\Patient;
use App\Entity\Doctor;
use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
use App\Repository\DoctorRepository;
use App\Repository\PatientRepository;
use App\Service\AppointmentService;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AppointmentServiceTest extends TestCase
{
    private AppointmentService $appointmentService;
    private EntityManagerInterface $entityManager;
    private AppointmentRepository $appointmentRepository;
    private DoctorRepository $doctorRepository;
    private PatientRepository $patientRepository;
    private ValidatorInterface $validator;
    private LoggerInterface $logger;
    private AuditLogger $auditLogger;
    private MailerInterface $mailer;
    private UrlGeneratorInterface $urlGenerator;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->appointmentRepository = $this->createMock(AppointmentRepository::class);
        $this->doctorRepository = $this->createMock(DoctorRepository::class);
        $this->patientRepository = $this->createMock(PatientRepository::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $notificationService = $this->createMock(\App\Service\NotificationService::class);

        $this->appointmentService = new AppointmentService(
            $this->entityManager,
            $this->appointmentRepository,
            $this->doctorRepository,
            $this->patientRepository,
            $this->validator,
            $this->logger,
            $this->auditLogger,
            $this->mailer,
            $this->urlGenerator,
            $notificationService
        );
    }

    public function testCreateAppointmentSuccess(): void
    {
        $patient = new Patient();
        $patient->setFirstName('Иван');
        $patient->setLastName('Иванов');
        $patient->setEmail('ivan@example.com');

        $doctor = new Doctor();
        $doctor->setFirstName('Петр');
        $doctor->setLastName('Петров');
        $doctor->setConsultationDuration(30);
        $doctor->setSchedule([
            'monday' => [['start' => '09:00', 'end' => '17:00']]
        ]);

        $startTime = new \DateTime('next monday 10:00');

        $this->appointmentRepository->expects($this->once())
            ->method('findConflictingAppointments')
            ->willReturn([]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new \Symfony\Component\Validator\ConstraintViolationList());

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Appointment::class));
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $appointment = $this->appointmentService->createAppointment(
            $patient,
            $doctor,
            $startTime,
            'Регулярный осмотр'
        );

        $this->assertInstanceOf(Appointment::class, $appointment);
        $this->assertEquals($patient, $appointment->getPatient());
        $this->assertEquals($doctor, $appointment->getDoctor());
        $this->assertEquals('Регулярный осмотр', $appointment->getReason());
        $this->assertEquals(Appointment::STATUS_SCHEDULED, $appointment->getStatus());
        
        $expectedEndTime = clone $startTime;
        $expectedEndTime->modify('+30 minutes');
        $this->assertEquals($startTime, $appointment->getStartTime());
        $this->assertEquals($expectedEndTime, $appointment->getEndTime());
    }

    public function testCreateAppointmentWithConflict(): void
    {
        $patient = new Patient();
        $doctor = new Doctor();
        $doctor->setConsultationDuration(30);
        $startTime = new \DateTime('next monday 10:00');

        $conflictingAppointment = new Appointment();

        $this->appointmentRepository->expects($this->once())
            ->method('findConflictingAppointments')
            ->willReturn([$conflictingAppointment]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Doctor has conflicting appointment at this time.');

        $this->appointmentService->createAppointment(
            $patient,
            $doctor,
            $startTime,
            'Осмотр'
        );
    }

    public function testCancelAppointment(): void
    {
        $appointment = new Appointment();
        $appointment->setStatus(Appointment::STATUS_SCHEDULED);
        
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->appointmentService->cancelAppointment($appointment, 'Пациент отменил');

        $this->assertEquals(Appointment::STATUS_CANCELLED, $appointment->getStatus());
        $this->assertNotNull($appointment->getCancelledAt());
        $this->assertEquals('Пациент отменил', $appointment->getCancellationReason());
    }

    public function testGetAvailableSlots(): void
    {
        $doctor = new Doctor();
        $doctor->setConsultationDuration(30);
        $doctor->setSchedule([
            'monday' => [['start' => '09:00', 'end' => '12:00']]
        ]);

        $date = new \DateTime('next monday');
        
        $expectedSlots = [
            [
                'start' => new \DateTime('next monday 09:00'),
                'end' => new \DateTime('next monday 09:30'),
                'formatted' => '09:00 - 09:30'
            ]
        ];

        $this->appointmentRepository->expects($this->once())
            ->method('findAvailableSlots')
            ->with($doctor, $date, null)
            ->willReturn($expectedSlots);

        $slots = $this->appointmentService->getAvailableSlots($doctor, $date);

        $this->assertIsArray($slots);
        $this->assertNotEmpty($slots);
    }

    public function testIsDoctorAvailable(): void
    {
        $doctor = new Doctor();
        $doctor->setConsultationDuration(30);
        $doctor->setSchedule([
            'monday' => [['start' => '09:00', 'end' => '17:00']]
        ]);

        $availableTime = new \DateTime('next monday 10:00');
        $unavailableTime = new \DateTime('next monday 18:00');

        $this->appointmentRepository->expects($this->exactly(2))
            ->method('findConflictingAppointments')
            ->willReturn([]);

        $this->assertTrue($this->appointmentService->isDoctorAvailable($doctor, $availableTime));
        $this->assertFalse($this->appointmentService->isDoctorAvailable($doctor, $unavailableTime));
    }

    public function testUpdateAppointment(): void
    {
        $doctor = new Doctor();
        $doctor->setConsultationDuration(30);

        $patient = new Patient();

        $appointment = new Appointment();
        $appointment->setPatient($patient);
        $appointment->setDoctor($doctor);
        $appointment->setStartTime(new \DateTime('2024-01-15 10:00'));
        $appointment->setStatus(Appointment::STATUS_SCHEDULED);

        $newStartTime = new \DateTime('2024-01-15 11:00');
        $updateData = [
            'start_time' => $newStartTime,
            'status' => Appointment::STATUS_CONFIRMED,
            'reason' => 'Новая причина'
        ];

        $this->appointmentRepository->expects($this->once())
            ->method('findConflictingAppointments')
            ->willReturn([]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new \Symfony\Component\Validator\ConstraintViolationList());

        $this->entityManager->expects($this->once())
            ->method('flush');

        $updatedAppointment = $this->appointmentService->updateAppointment($appointment, $updateData);

        $this->assertEquals($newStartTime, $updatedAppointment->getStartTime());
        $this->assertEquals(Appointment::STATUS_CONFIRMED, $updatedAppointment->getStatus());
        $this->assertEquals('Новая причина', $updatedAppointment->getReason());
    }

    public function testCheckAndMarkNoShows(): void
    {
        // Создаем реальные объекты
        $appointment1 = new Appointment();
        $appointment2 = new Appointment();
        $appointments = [$appointment1, $appointment2];
        
        // Мокаем QueryBuilder правильно
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        
        $query->method('getResult')->willReturn($appointments);
        
        $this->appointmentRepository->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->appointmentService->checkAndMarkNoShows();

        $this->assertEquals(2, $result);
        foreach ($appointments as $appointment) {
            $this->assertEquals(Appointment::STATUS_NO_SHOW, $appointment->getStatus());
        }
    }

    public function testFindAlternativeSlots(): void
    {
        $originalDoctor = $this->createMock(Doctor::class);
        $originalDoctor->method('getId')->willReturn(1);
        $originalDoctor->method('getSpecialization')->willReturn('Терапевт');
        $originalDoctor->method('getConsultationDuration')->willReturn(30);
        $originalDoctor->method('getSchedule')->willReturn([
            'monday' => [['start' => '09:00', 'end' => '17:00']]
        ]);

        $otherDoctor = $this->createMock(Doctor::class);
        $otherDoctor->method('getId')->willReturn(2);
        $otherDoctor->method('getSpecialization')->willReturn('Терапевт');
        $otherDoctor->method('getConsultationDuration')->willReturn(30);
        $otherDoctor->method('getSchedule')->willReturn([
            'monday' => [['start' => '09:00', 'end' => '17:00']]
        ]);

        $originalTime = new \DateTime('2024-01-15 10:00');

        $this->doctorRepository->expects($this->once())
            ->method('findBy')
            ->with(['specialization' => 'Терапевт'])
            ->willReturn([$originalDoctor, $otherDoctor]);

        $this->appointmentRepository->expects($this->exactly(2))
            ->method('findAvailableSlots')
            ->willReturn([
                ['start' => new \DateTime('2024-01-15 11:00'), 'end' => new \DateTime('2024-01-15 11:30')]
            ]);

        $alternatives = $this->appointmentService->findAlternativeSlots(
            $originalDoctor,
            $originalTime,
            7
        );

        $this->assertIsArray($alternatives);
        $this->assertArrayHasKey('same_doctor', $alternatives);
        $this->assertArrayHasKey('other_doctors', $alternatives);
    }

    public function testCompleteAppointment(): void
    {
        $appointment = new Appointment();
        $appointment->setStatus(Appointment::STATUS_SCHEDULED);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->appointmentService->completeAppointment($appointment, 'doctor@example.com');

        $this->assertEquals(Appointment::STATUS_COMPLETED, $appointment->getStatus());
        $this->assertNotNull($appointment->getCompletedAt());
    }

    public function testSendAppointmentReminders(): void
    {
        $appointment = $this->createMock(Appointment::class);
        $patient = $this->createMock(Patient::class);
        
        $appointment->method('getPatient')->willReturn($patient);
        $appointment->method('getId')->willReturn(1);
        $patient->method('getEmail')->willReturn('patient@example.com');
        
        $appointments = [$appointment];
        
        $this->appointmentRepository->expects($this->once())
            ->method('findAppointmentsNeedingReminder')
            ->willReturn($appointments);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->appointmentService->sendAppointmentReminders();

        $this->assertEquals(1, $result);
    }
}