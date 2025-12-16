<?php
// src/Controller/Reception/ReceptionController.php

namespace App\Controller\Reception;

use App\Entity\Appointment;
use App\Entity\Patient;
use App\Entity\Invoice;
use App\Form\AppointmentType;
use App\Form\PatientType;
use App\Form\InvoiceType;
use App\Repository\AppointmentRepository;
use App\Repository\PatientRepository;
use App\Repository\DoctorRepository;
use App\Repository\InvoiceRepository;
use App\Service\AppointmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/reception')]
#[IsGranted('ROLE_RECEPTIONIST')]
class ReceptionController extends AbstractController
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

    #[Route('/', name: 'reception_dashboard')]
    public function dashboard(
        AppointmentRepository $appointmentRepository,
        PatientRepository $patientRepository
    ): Response {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = clone $today;
        $tomorrow->modify('+1 day');

        $todaysAppointments = $appointmentRepository->findByDateRange($today, $tomorrow);
        $newPatients = $patientRepository->findBy([], ['createdAt' => 'DESC'], 5);
        $upcomingAppointments = $appointmentRepository->findUpcomingAppointments(10);

        return $this->render('reception/dashboard.html.twig', [
            'todays_appointments' => $todaysAppointments,
            'new_patients' => $newPatients,
            'upcoming_appointments' => $upcomingAppointments,
            'today' => $today
        ]);
    }

    #[Route('/appointments', name: 'reception_appointment_index')]
    public function appointmentIndex(
        Request $request,
        AppointmentRepository $appointmentRepository
    ): Response {
        $status = $request->query->get('status');
        $doctorId = $request->query->getInt('doctor');
        $date = $request->query->get('date');

        $appointments = $appointmentRepository->findWithFilters($status, $doctorId, $date);

        return $this->render('reception/appointment/index.html.twig', [
            'appointments' => $appointments,
            'status' => $status,
            'doctor_id' => $doctorId,
            'date' => $date
        ]);
    }

    #[Route('/appointments/new', name: 'reception_appointment_new')]
    public function appointmentNew(
        Request $request,
        DoctorRepository $doctorRepository
    ): Response {
        $appointment = new Appointment();
        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->persist($appointment);
                $this->entityManager->flush();

                $this->addFlash('success', 'Прием успешно создан');

                return $this->redirectToRoute('reception_appointment_show', ['id' => $appointment->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Ошибка при создании приема: ' . $e->getMessage());
            }
        }

        $doctors = $doctorRepository->findAll();

        return $this->render('reception/appointment/new.html.twig', [
            'form' => $form->createView(),
            'doctors' => $doctors
        ]);
    }

    #[Route('/patients', name: 'reception_patient_index')]
    public function patientIndex(
        Request $request,
        PatientRepository $patientRepository
    ): Response {
        $search = $request->query->get('search');
        $page = $request->query->getInt('page', 1);
        $limit = 20;

        $patients = $patientRepository->search($search, $page, $limit);

        return $this->render('reception/patient/index.html.twig', [
            'patients' => $patients,
            'search' => $search,
            'page' => $page
        ]);
    }

    #[Route('/patients/new', name: 'reception_patient_new')]
    public function patientNew(Request $request): Response
    {
        $patient = new Patient();
        $form = $this->createForm(PatientType::class, $patient);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($patient);
            $this->entityManager->flush();

            $this->addFlash('success', 'Пациент успешно создан');

            return $this->redirectToRoute('reception_patient_show', ['id' => $patient->getId()]);
        }

        return $this->render('reception/patient/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/invoices', name: 'reception_invoice_index')]
    public function invoiceIndex(
        Request $request,
        InvoiceRepository $invoiceRepository
    ): Response {
        $status = $request->query->get('status');
        $patientId = $request->query->getInt('patient');

        $invoices = $invoiceRepository->findWithFilters($status, $patientId);

        return $this->render('reception/invoice/index.html.twig', [
            'invoices' => $invoices,
            'status' => $status,
            'patient_id' => $patientId
        ]);
    }

    #[Route('/invoices/new', name: 'reception_invoice_new')]
    public function invoiceNew(Request $request): Response
    {
        $invoice = new Invoice();
        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($invoice);
            $this->entityManager->flush();

            $this->addFlash('success', 'Счет успешно создан');

            return $this->redirectToRoute('reception_invoice_show', ['id' => $invoice->getId()]);
        }

        return $this->render('reception/invoice/new.html.twig', [
            'form' => $form->createView()
        ]);
    }
}