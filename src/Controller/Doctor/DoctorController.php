<?php
// src/Controller/Doctor/DoctorController.php

namespace App\Controller\Doctor;

use App\Entity\Doctor;
use App\Entity\Patient;
use App\Entity\Appointment;
use App\Entity\MedicalRecord;
use App\Entity\Prescription;
use App\Form\MedicalRecordType;
use App\Form\PrescriptionType;
use App\Repository\AppointmentRepository;
use App\Repository\PatientRepository;
use App\Repository\MedicalRecordRepository;
use App\Repository\PrescriptionRepository;
use App\Service\AppointmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/doctor')]
#[IsGranted('ROLE_DOCTOR')]
class DoctorController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private AppointmentService $appointmentService;

    public function __construct(
        EntityManagerInterface $entityManager,
        AppointmentService $appointmentService
    ) {
        $this->entityManager = $entityManager;
        $this->appointmentService = $appointmentService;
    }

    #[Route('/dashboard', name: 'doctor_dashboard')]
    public function dashboard(
        AppointmentRepository $appointmentRepository
    ): Response {
        /** @var Doctor $doctor */
        $doctor = $this->getUser()->getDoctor();
        
        if (!$doctor) {
            throw new AccessDeniedException('Доступ только для врачей');
        }

        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = clone $today;
        $tomorrow->modify('+1 day');

        // Today's appointments
        $todaysAppointments = $appointmentRepository->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startTime >= :start')
            ->andWhere('a.startTime < :end')
            ->andWhere('a.status NOT IN (:cancelled)')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $today)
            ->setParameter('end', $tomorrow)
            ->setParameter('cancelled', ['cancelled'])
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        // Upcoming appointments
        $upcomingAppointments = $appointmentRepository->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startTime >= :tomorrow')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('doctor', $doctor)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->orderBy('a.startTime', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Weekly statistics
        $startOfWeek = new \DateTime('monday this week');
        $endOfWeek = new \DateTime('sunday this week 23:59:59');
        $weeklyStats = $appointmentRepository->getStatistics($startOfWeek, $endOfWeek, $doctor);

        // Recent patients
        $recentPatients = $this->entityManager->getRepository(Patient::class)
            ->createQueryBuilder('p')
            ->innerJoin('p.appointments', 'a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startTime >= :lastMonth')
            ->setParameter('doctor', $doctor)
            ->setParameter('lastMonth', new \DateTime('-30 days'))
            ->groupBy('p.id')
            ->orderBy('MAX(a.startTime)', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('doctor/dashboard.html.twig', [
            'doctor' => $doctor,
            'todays_appointments' => $todaysAppointments,
            'upcoming_appointments' => $upcomingAppointments,
            'weekly_stats' => $weeklyStats,
            'recent_patients' => $recentPatients,
            'today' => $today
        ]);
    }

    #[Route('/appointments', name: 'doctor_appointment_index')]
    public function appointmentIndex(
        Request $request,
        AppointmentRepository $appointmentRepository
    ): Response {
        /** @var Doctor $doctor */
        $doctor = $this->getUser()->getDoctor();
        
        $status = $request->query->get('status');
        $date = $request->query->get('date');
        
        $qb = $appointmentRepository->createQueryBuilder('a')
            ->leftJoin('a.patient', 'p')
            ->where('a.doctor = :doctor')
            ->setParameter('doctor', $doctor);

        if ($status && $status !== 'all') {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        if ($date) {
            $dateObj = new \DateTime($date);
            $startOfDay = clone $dateObj;
            $startOfDay->setTime(0, 0, 0);
            $endOfDay = clone $dateObj;
            $endOfDay->setTime(23, 59, 59);
            
            $qb->andWhere('a.startTime BETWEEN :start AND :end')
                ->setParameter('start', $startOfDay)
                ->setParameter('end', $endOfDay);
        }

        $appointments = $qb->orderBy('a.startTime', $date ? 'ASC' : 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('doctor/appointment/index.html.twig', [
            'appointments' => $appointments,
            'status' => $status,
            'date' => $date
        ]);
    }

    #[Route('/appointments/{id}', name: 'doctor_appointment_show')]
    public function appointmentShow(Appointment $appointment): Response
    {
        $this->checkDoctorOwnership($appointment);

        return $this->render('doctor/appointment/show.html.twig', [
            'appointment' => $appointment,
            'patient' => $appointment->getPatient()
        ]);
    }

    #[Route('/appointments/{id}/start', name: 'doctor_appointment_start', methods: ['POST'])]
    public function appointmentStart(Appointment $appointment, Request $request): Response
    {
        $this->checkDoctorOwnership($appointment);

        if ($this->isCsrfTokenValid('start' . $appointment->getId(), $request->request->get('_token'))) {
            $appointment->setStatus(Appointment::STATUS_IN_PROGRESS);
            $this->entityManager->flush();

            $this->addFlash('success', 'Прием начат');
        }

        return $this->redirectToRoute('doctor_appointment_show', ['id' => $appointment->getId()]);
    }

    #[Route('/appointments/{id}/complete', name: 'doctor_appointment_complete')]
    public function appointmentComplete(
        Appointment $appointment,
        Request $request,
        MedicalRecordRepository $medicalRecordRepository
    ): Response {
        $this->checkDoctorOwnership($appointment);

        $medicalRecord = $medicalRecordRepository->findOneBy(['appointment' => $appointment]) 
            ?? new MedicalRecord();
        
        $form = $this->createForm(MedicalRecordType::class, $medicalRecord);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$medicalRecord->getId()) {
                $medicalRecord->setPatient($appointment->getPatient());
                $medicalRecord->setDoctor($appointment->getDoctor());
                $medicalRecord->setAppointment($appointment);
                $medicalRecord->setType(MedicalRecord::TYPE_CONSULTATION);
                $medicalRecord->setRecordDate(new \DateTime());
                
                $this->entityManager->persist($medicalRecord);
            }

            $appointment->setStatus(Appointment::STATUS_COMPLETED);
            $appointment->setCompletedAt(new \DateTime());
            
            $this->entityManager->flush();

            $this->addFlash('success', 'Прием завершен и медицинская запись сохранена');

            return $this->redirectToRoute('doctor_appointment_show', ['id' => $appointment->getId()]);
        }

        return $this->render('doctor/appointment/complete.html.twig', [
            'appointment' => $appointment,
            'form' => $form->createView(),
            'medical_record' => $medicalRecord
        ]);
    }

    #[Route('/patients', name: 'doctor_patient_index')]
    public function patientIndex(
        Request $request,
        PatientRepository $patientRepository
    ): Response {
        /** @var Doctor $doctor */
        $doctor = $this->getUser()->getDoctor();

        $search = $request->query->get('search');
        $page = $request->query->getInt('page', 1);
        $limit = 20;

        $qb = $patientRepository->createQueryBuilder('p')
            ->innerJoin('p.appointments', 'a')
            ->where('a.doctor = :doctor')
            ->setParameter('doctor', $doctor)
            ->groupBy('p.id');

        if ($search) {
            $qb->andWhere('p.firstName LIKE :search OR p.lastName LIKE :search OR p.medicalNumber LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $total = count($qb->getQuery()->getResult());
        $patients = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('doctor/patient/index.html.twig', [
            'patients' => $patients,
            'search' => $search,
            'page' => $page,
            'pages' => ceil($total / $limit),
            'total' => $total
        ]);
    }

    #[Route('/patients/{id}', name: 'doctor_patient_show')]
    public function patientShow(
        Patient $patient,
        MedicalRecordRepository $medicalRecordRepository,
        PrescriptionRepository $prescriptionRepository
    ): Response {
        $this->checkDoctorAccessToPatient($patient);

        $medicalRecords = $medicalRecordRepository->findBy(
            ['patient' => $patient, 'doctor' => $this->getUser()->getDoctor()],
            ['recordDate' => 'DESC']
        );

        $prescriptions = $prescriptionRepository->findBy(
            ['patient' => $patient, 'doctor' => $this->getUser()->getDoctor()],
            ['prescribedDate' => 'DESC']
        );

        $appointments = $this->entityManager->getRepository(Appointment::class)
            ->findBy(
                ['patient' => $patient, 'doctor' => $this->getUser()->getDoctor()],
                ['startTime' => 'DESC'],
                10
            );

        return $this->render('doctor/patient/show.html.twig', [
            'patient' => $patient,
            'medical_records' => $medicalRecords,
            'prescriptions' => $prescriptions,
            'appointments' => $appointments
        ]);
    }

    #[Route('/patients/{id}/medical-record/new', name: 'doctor_medical_record_new')]
    public function medicalRecordNew(
        Patient $patient,
        Request $request
    ): Response {
        $this->checkDoctorAccessToPatient($patient);

        $medicalRecord = new MedicalRecord();
        $form = $this->createForm(MedicalRecordType::class, $medicalRecord);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Doctor $doctor */
            $doctor = $this->getUser()->getDoctor();
            
            $medicalRecord->setPatient($patient);
            $medicalRecord->setDoctor($doctor);
            $medicalRecord->setRecordDate(new \DateTime());

            $this->entityManager->persist($medicalRecord);
            $this->entityManager->flush();

            $this->addFlash('success', 'Медицинская запись сохранена');

            return $this->redirectToRoute('doctor_patient_show', ['id' => $patient->getId()]);
        }

        return $this->render('doctor/medical_record/new.html.twig', [
            'patient' => $patient,
            'form' => $form->createView()
        ]);
    }

    #[Route('/patients/{id}/prescription/new', name: 'doctor_prescription_new')]
    public function prescriptionNew(
        Patient $patient,
        Request $request
    ): Response {
        $this->checkDoctorAccessToPatient($patient);

        $prescription = new Prescription();
        $form = $this->createForm(PrescriptionType::class, $prescription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Doctor $doctor */
            $doctor = $this->getUser()->getDoctor();
            
            $prescription->setPatient($patient);
            $prescription->setDoctor($doctor);
            $prescription->setPrescribedDate(new \DateTime());

            $this->entityManager->persist($prescription);
            $this->entityManager->flush();

            $this->addFlash('success', 'Рецепт выписан');

            return $this->redirectToRoute('doctor_patient_show', ['id' => $patient->getId()]);
        }

        return $this->render('doctor/prescription/new.html.twig', [
            'patient' => $patient,
            'form' => $form->createView()
        ]);
    }

    #[Route('/schedule', name: 'doctor_schedule')]
    public function schedule(Request $request): Response
    {
        /** @var Doctor $doctor */
        $doctor = $this->getUser()->getDoctor();
        
        $weekStart = new \DateTime($request->query->get('week', 'monday this week'));
        $schedule = $this->appointmentService->getDoctorScheduleForWeek($doctor, $weekStart, true);

        return $this->render('doctor/schedule/index.html.twig', [
            'doctor' => $doctor,
            'schedule' => $schedule,
            'week_start' => $weekStart
        ]);
    }

    #[Route('/schedule/update', name: 'doctor_schedule_update', methods: ['POST'])]
    public function scheduleUpdate(Request $request): Response
    {
        /** @var Doctor $doctor */
        $doctor = $this->getUser()->getDoctor();

        $scheduleData = json_decode($request->request->get('schedule'), true);
        
        if ($scheduleData) {
            $doctor->setSchedule($scheduleData);
            $this->entityManager->flush();

            $this->addFlash('success', 'Расписание обновлено');
        } else {
            $this->addFlash('error', 'Ошибка при обновлении расписания');
        }

        return $this->redirectToRoute('doctor_schedule');
    }

    #[Route('/statistics', name: 'doctor_statistics')]
    public function statistics(
        Request $request,
        AppointmentRepository $appointmentRepository
    ): Response {
        /** @var Doctor $doctor */
        $doctor = $this->getUser()->getDoctor();

        $period = $request->query->get('period', 'month');
        
        switch ($period) {
            case 'week':
                $startDate = new \DateTime('monday this week');
                $endDate = new \DateTime('sunday this week');
                break;
            case 'month':
                $startDate = new \DateTime('first day of this month');
                $endDate = new \DateTime('last day of this month');
                break;
            case 'quarter':
                $startDate = new \DateTime('first day of this quarter');
                $endDate = new \DateTime('last day of this quarter');
                break;
            case 'year':
                $startDate = new \DateTime('first day of January this year');
                $endDate = new \DateTime('last day of December this year');
                break;
            default:
                $startDate = new \DateTime('-30 days');
                $endDate = new \DateTime();
        }

        $statistics = $appointmentRepository->getStatistics($startDate, $endDate, $doctor);

        // Patient statistics
        $patientStats = $this->entityManager->getRepository(Patient::class)
            ->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id) as patient_count')
            ->innerJoin('p.appointments', 'a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startTime BETWEEN :start AND :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        // Most common diagnoses
        $diagnoses = $this->entityManager->getRepository(MedicalRecord::class)
            ->createQueryBuilder('mr')
            ->select('mr.data->>"$.diagnosis" as diagnosis, COUNT(mr.id) as count')
            ->where('mr.doctor = :doctor')
            ->andWhere('mr.recordDate BETWEEN :start AND :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('diagnosis')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->render('doctor/statistics/index.html.twig', [
            'doctor' => $doctor,
            'statistics' => $statistics,
            'patient_count' => $patientStats,
            'diagnoses' => $diagnoses,
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    private function checkDoctorOwnership(Appointment $appointment): void
    {
        /** @var Doctor $doctor */
        $doctor = $this->getUser()->getDoctor();
        
        if ($appointment->getDoctor()->getId() !== $doctor->getId()) {
            throw new AccessDeniedException('У вас нет доступа к этому приему');
        }
    }

    private function checkDoctorAccessToPatient(Patient $patient): void
    {
        /** @var Doctor $doctor */
        $doctor = $this->getUser()->getDoctor();
        
        $hasAccess = $this->entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.patient = :patient')
            ->andWhere('a.doctor = :doctor')
            ->setParameter('patient', $patient)
            ->setParameter('doctor', $doctor)
            ->getQuery()
            ->getSingleScalarResult();

        if (!$hasAccess) {
            throw new AccessDeniedException('У вас нет доступа к этому пациенту');
        }
    }
}