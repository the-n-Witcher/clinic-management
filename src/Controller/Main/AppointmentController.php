<?php
// src/Controller/Main/AppointmentController.php

namespace App\Controller\Main;

use App\Entity\Appointment;
use App\Form\AppointmentType;
use App\Repository\AppointmentRepository;
use App\Service\AppointmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/appointments')]
#[IsGranted('ROLE_RECEPTIONIST')]
class AppointmentController extends AbstractController
{
    private AppointmentService $appointmentService;
    private EntityManagerInterface $entityManager;

    public function __construct(AppointmentService $appointmentService, EntityManagerInterface $entityManager)
    {
        $this->appointmentService = $appointmentService;
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'appointment_index', methods: ['GET'])]
    public function index(AppointmentRepository $appointmentRepository): Response
    {
        $appointments = $appointmentRepository->findUpcomingAppointments(50);

        return $this->render('appointment/index.html.twig', [
            'appointments' => $appointments,
        ]);
    }

    #[Route('/new', name: 'appointment_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $appointment = new Appointment();
        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->persist($appointment);
                $this->entityManager->flush();

                $this->addFlash('success', 'Appointment created successfully.');

                return $this->redirectToRoute('appointment_show', ['id' => $appointment->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating appointment: ' . $e->getMessage());
            }
        }

        return $this->render('appointment/new.html.twig', [
            'appointment' => $appointment,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'appointment_show', methods: ['GET'])]
    public function show(Appointment $appointment): Response
    {
        return $this->render('appointment/show.html.twig', [
            'appointment' => $appointment,
        ]);
    }

    #[Route('/{id}/edit', name: 'appointment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Appointment $appointment): Response
    {
        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->flush();

                $this->addFlash('success', 'Appointment updated successfully.');

                return $this->redirectToRoute('appointment_show', ['id' => $appointment->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating appointment: ' . $e->getMessage());
            }
        }

        return $this->render('appointment/edit.html.twig', [
            'appointment' => $appointment,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/cancel', name: 'appointment_cancel', methods: ['POST'])]
    public function cancel(Request $request, Appointment $appointment): Response
    {
        if ($this->isCsrfTokenValid('cancel'.$appointment->getId(), $request->request->get('_token'))) {
            $reason = $request->request->get('reason', 'No reason provided');
            
            try {
                $this->appointmentService->cancelAppointment($appointment, $reason);
                $this->addFlash('success', 'Appointment cancelled successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error cancelling appointment: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('appointment_index');
    }

    #[Route('/{id}', name: 'appointment_delete', methods: ['POST'])]
    public function delete(Request $request, Appointment $appointment): Response
    {
        if ($this->isCsrfTokenValid('delete'.$appointment->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($appointment);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Appointment deleted successfully.');
        }

        return $this->redirectToRoute('appointment_index');
    }

    #[Route('/doctor/{doctorId}/slots/{date}', name: 'appointment_slots', methods: ['GET'])]
    public function getAvailableSlots(int $doctorId, \DateTimeInterface $date): Response
    {
        $doctor = $this->entityManager->getRepository(Doctor::class)->find($doctorId);
        
        if (!$doctor) {
            return $this->json(['error' => 'Doctor not found'], 404);
        }

        $slots = $this->appointmentService->getAvailableSlots($doctor, $date);

        return $this->json($slots);
    }
}