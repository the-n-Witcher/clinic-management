<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Patient;
use App\Entity\Doctor;
use App\Entity\Appointment;
use App\Entity\AuditLog;
use App\Form\UserEditType;
use App\Form\ImportPatientsType;
use App\Service\ImportExportService;
use App\Service\AppointmentService;
use App\Repository\UserRepository;
use App\Repository\PatientRepository;
use App\Repository\DoctorRepository;
use App\Repository\AppointmentRepository;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ImportExportService $importExportService;
    private AppointmentService $appointmentService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ImportExportService $importExportService,
        AppointmentService $appointmentService
    ) {
        $this->entityManager = $entityManager;
        $this->importExportService = $importExportService;
        $this->appointmentService = $appointmentService;
    }

    #[Route('/', name: 'admin_dashboard')]
    public function dashboard(
        PatientRepository $patientRepository,
        AppointmentRepository $appointmentRepository,
        DoctorRepository $doctorRepository
    ): Response {
        $patientStats = $patientRepository->getStatistics();
        $today = new \DateTime();
        $startOfMonth = new \DateTime('first day of this month');
        $endOfMonth = new \DateTime('last day of this month');
        
        $appointmentStats = $appointmentRepository->getStatistics($startOfMonth, $endOfMonth);
        
        $recentPatients = $patientRepository->findBy([], ['createdAt' => 'DESC'], 5);
        $recentAppointments = $appointmentRepository->findUpcomingAppointments(5);
        
        $upcomingAppointments = $appointmentRepository->findAppointmentsByDateRange(
            new \DateTime(),
            (new \DateTime())->modify('+7 days')
        );

        return $this->render('admin/dashboard.html.twig', [
            'patient_stats' => $patientStats,
            'appointment_stats' => $appointmentStats,
            'recent_patients' => $recentPatients,
            'recent_appointments' => $recentAppointments,
            'upcoming_appointments' => $upcomingAppointments,
            'doctor_count' => $doctorRepository->count([]),
            'today' => $today
        ]);
    }

    #[Route('/patients', name: 'admin_patient_index')]
    public function patientIndex(
        Request $request,
        PatientRepository $patientRepository
    ): Response {
        $filters = [
            'name' => $request->query->get('name'),
            'medicalNumber' => $request->query->get('medicalNumber'),
            'gender' => $request->query->get('gender'),
            'minAge' => $request->query->get('minAge'),
            'maxAge' => $request->query->get('maxAge'),
            'hasAllergy' => $request->query->get('allergy'),
            'hasChronicDisease' => $request->query->get('disease')
        ];

        $patients = $patientRepository->findByFilters($filters);

        return $this->render('admin/patient/index.html.twig', [
            'patients' => $patients,
            'filters' => $filters
        ]);
    }

    #[Route('/patients/import', name: 'admin_patient_import')]
    public function importPatients(Request $request): Response
    {
        $form = $this->createForm(ImportPatientsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            $options = [
                'update_existing' => $form->get('updateExisting')->getData()
            ];

            $results = $this->importExportService->importPatientsFromExcel($file, $options);

            if (!empty($results['errors'])) {
                foreach ($results['errors'] as $error) {
                    $this->addFlash('error', $error);
                }
            }

            $this->addFlash('success', sprintf(
                'Импорт завершен: %d обработано, %d импортировано, %d обновлено, %d пропущено',
                $results['total'],
                $results['imported'],
                $results['updated'],
                $results['skipped']
            ));

            return $this->redirectToRoute('admin_patient_index');
        }

        return $this->render('admin/patient/import.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/patients/export', name: 'admin_patient_export')]
    public function exportPatients(Request $request): Response
    {
        $filters = [
            'name' => $request->query->get('name'),
            'medicalNumber' => $request->query->get('medicalNumber'),
            'gender' => $request->query->get('gender')
        ];

        $tempFile = $this->importExportService->exportPatientsToExcel($filters);

        $response = new BinaryFileResponse($tempFile);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'patients_' . date('Y-m-d') . '.xlsx'
        );

        return $response;
    }

    #[Route('/patients/{id}/medical-history', name: 'admin_patient_medical_history')]
    public function exportMedicalHistory(Patient $patient): Response
    {
        $tempFile = $this->importExportService->generateMedicalHistoryPdf($patient);

        $response = new BinaryFileResponse($tempFile);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'medical_history_' . $patient->getMedicalNumber() . '.pdf'
        );

        return $response;
    }

    #[Route('/doctors', name: 'admin_doctor_index')]
    public function doctorIndex(DoctorRepository $doctorRepository): Response
    {
        $doctors = $doctorRepository->findAll();

        return $this->render('admin/doctor/index.html.twig', [
            'doctors' => $doctors
        ]);
    }

    #[Route('/doctors/{id}/schedule', name: 'admin_doctor_schedule')]
    public function doctorSchedule(Doctor $doctor, Request $request): Response
    {
        $weekStart = new \DateTime($request->query->get('week', 'monday this week'));
        $schedule = $this->appointmentService->getDoctorScheduleForWeek($doctor, $weekStart, true);

        return $this->render('admin/doctor/schedule.html.twig', [
            'doctor' => $doctor,
            'schedule' => $schedule,
            'week_start' => $weekStart
        ]);
    }

    #[Route('/appointments/report', name: 'admin_appointment_report')]
    public function appointmentReport(Request $request): Response
    {
        $startDate = new \DateTime($request->query->get('start_date', 'first day of this month'));
        $endDate = new \DateTime($request->query->get('end_date', 'last day of this month'));
        
        $format = $request->query->get('format', 'html');

        $report = $this->appointmentService->generateAppointmentReport($startDate, $endDate);

        if ($format === 'pdf') {
            $tempFile = $this->importExportService->generateAppointmentReportPdf($report);
            
            $response = new BinaryFileResponse($tempFile);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'appointment_report_' . date('Y-m-d') . '.pdf'
            );
            
            return $response;
        } elseif ($format === 'excel') {
            $tempFile = $this->importExportService->exportStatisticsToExcel($report, 'appointment_statistics');
            
            $response = new BinaryFileResponse($tempFile);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'appointment_report_' . date('Y-m-d') . '.xlsx'
            );
            
            return $response;
        }

        return $this->render('admin/report/appointments.html.twig', [
            'report' => $report,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    #[Route('/audit-logs', name: 'admin_audit_logs')]
    public function auditLogs(
        Request $request,
        AuditLogRepository $auditLogRepository
    ): Response {
        $page = $request->query->getInt('page', 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $filters = [
            'entity_type' => $request->query->get('entity_type'),
            'action' => $request->query->get('action'),
            'username' => $request->query->get('username'),
            'start_date' => $request->query->get('start_date'),
            'end_date' => $request->query->get('end_date'),
            'ip_address' => $request->query->get('ip_address')
        ];

        $logs = $auditLogRepository->findByFilters($filters, $limit, $offset);
        $total = $auditLogRepository->countByFilters($filters);
        $pages = ceil($total / $limit);

        return $this->render('admin/audit/logs.html.twig', [
            'logs' => $logs,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'filters' => array_filter($filters)
        ]);
    }

    #[Route('/audit-logs/{id}', name: 'admin_audit_log_show')]
    public function auditLogShow(int $id, AuditLogRepository $auditLogRepository): Response
    {
        $log = $auditLogRepository->find($id);
        
        if (!$log) {
            throw $this->createNotFoundException('Лог не найден');
        }

        return $this->render('admin/audit/show.html.twig', [
            'log' => $log
        ]);
    }

    #[Route('/audit-logs/export', name: 'admin_audit_logs_export')]
    public function auditLogsExport(
        Request $request,
        AuditLogRepository $auditLogRepository
    ): Response {
        $startDate = new \DateTime($request->query->get('start_date', '-30 days'));
        $endDate = new \DateTime($request->query->get('end_date', 'now'));
        $format = $request->query->get('format', 'json');

        $logs = $auditLogRepository->exportLogs($startDate, $endDate, $format);

        if ($format === 'json') {
            $response = new Response(json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Content-Disposition', 'attachment; filename="audit_logs_' . date('Y-m-d') . '.json"');
            return $response;
        }

        if ($format === 'csv') {
            $csv = $this->convertToCsv($logs);
            $response = new Response($csv);
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');
            return $response;
        }

        return $this->redirectToRoute('admin_audit_logs');
    }

    #[Route('/audit-logs/statistics', name: 'admin_audit_statistics')]
    public function auditStatistics(
        Request $request,
        AuditLogRepository $auditLogRepository
    ): Response {
        $startDate = new \DateTime($request->query->get('start_date', '-30 days'));
        $endDate = new \DateTime($request->query->get('end_date', 'now'));

        $statistics = $auditLogRepository->getStatistics($startDate, $endDate);

        return $this->render('admin/audit/statistics.html.twig', [
            'statistics' => $statistics,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    #[Route('/audit-logs/cleanup', name: 'admin_audit_cleanup', methods: ['POST'])]
    public function auditCleanup(
        Request $request,
        AuditLogRepository $auditLogRepository
    ): Response {
        if ($this->isCsrfTokenValid('cleanup-logs', $request->request->get('_token'))) {
            $daysToKeep = (int) $request->request->get('days_to_keep', 90);
            $deletedCount = $auditLogRepository->cleanupOldLogs($daysToKeep);

            $this->addFlash('success', sprintf('Удалено %d старых записей аудита', $deletedCount));
        }

        return $this->redirectToRoute('admin_audit_logs');
    }

    private function convertToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        
        fputcsv($output, array_keys($data[0]));

        foreach ($data as $row) {
            $row['data'] = is_array($row['data']) ? json_encode($row['data'], JSON_UNESCAPED_UNICODE) : $row['data'];
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    #[Route('/users', name: 'admin_user_index')]
    public function userIndex(
        Request $request,
        UserRepository $userRepository
    ): Response {
        $search = $request->query->get('search');
        $role = $request->query->get('role');

        $qb = $userRepository->createQueryBuilder('u');

        if ($search) {
            $qb->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($role) {
            $qb->andWhere('JSON_CONTAINS(u.roles, :role) = 1')
                ->setParameter('role', json_encode($role));
        }

        $users = $qb->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'search' => $search,
            'role' => $role
        ]);
    }

    #[Route('/users/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function userEdit(User $user, Request $request): Response
    {
        if ($this->getUser()->getId() === $user->getId()) {
            throw new AccessDeniedException('Нельзя редактировать свой собственный аккаунт');
        }

        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->flush();

                $this->addFlash('success', 'Пользователь успешно обновлен');

                return $this->redirectToRoute('admin_user_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Ошибка при обновлении пользователя: ' . $e->getMessage());
            }
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView()
        ]);
    }

    #[Route('/users/{id}/toggle-active', name: 'admin_user_toggle_active', methods: ['POST'])]
    public function userToggleActive(User $user, Request $request): Response
    {
        if ($this->getUser()->getId() === $user->getId()) {
            throw new AccessDeniedException('Нельзя деактивировать свой собственный аккаунт');
        }

        if ($this->isCsrfTokenValid('toggle-active' . $user->getId(), $request->request->get('_token'))) {
            $user->setIsActive(!$user->isActive());
            $this->entityManager->flush();

            $action = $user->isActive() ? 'активирован' : 'деактивирован';
            $this->addFlash('success', "Пользователь {$user->getEmail()} {$action}");
        }

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/users/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function userDelete(User $user, Request $request): Response
    {
        if ($this->getUser()->getId() === $user->getId()) {
            throw new AccessDeniedException('Нельзя удалить свой собственный аккаунт');
        }

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            try {
                $this->entityManager->remove($user);
                $this->entityManager->flush();

                $this->addFlash('success', "Пользователь {$user->getEmail()} удален");
            } catch (\Exception $e) {
                $this->addFlash('error', 'Ошибка при удалении пользователя: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/users/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function userNew(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user->setPassword(password_hash('temp_password123', PASSWORD_DEFAULT));
                
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $this->addFlash('success', 'Пользователь успешно создан');

                return $this->redirectToRoute('admin_user_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Ошибка при создании пользователя: ' . $e->getMessage());
            }
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/system/health', name: 'admin_system_health')]
    public function systemHealth(): Response
    {
        try {
            $connection = $this->entityManager->getConnection();
            $connection->connect();
            $dbStatus = 'connected';
            $dbVersion = $connection->getWrappedConnection()->getServerVersion();
        } catch (\Exception $e) {
            $dbStatus = 'disconnected';
            $dbVersion = 'N/A';
        }

        try {
            $cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter();
            $cache->get('health_check', fn() => 'ok');
            $cacheStatus = 'working';
        } catch (\Exception $e) {
            $cacheStatus = 'failed';
        }

        $storagePath = $this->getParameter('kernel.project_dir') . '/var/storage';
        $storageFree = disk_free_space($storagePath);
        $storageTotal = disk_total_space($storagePath);
        $storageUsage = $storageTotal ? (($storageTotal - $storageFree) / $storageTotal) * 100 : 0;

        $load = sys_getloadavg();

        $phpVersion = PHP_VERSION;
        $memoryLimit = ini_get('memory_limit');
        $maxExecutionTime = ini_get('max_execution_time');

        return $this->render('admin/system/health.html.twig', [
            'db_status' => $dbStatus,
            'db_version' => $dbVersion,
            'cache_status' => $cacheStatus,
            'storage_usage' => round($storageUsage, 2),
            'storage_free' => $this->formatBytes($storageFree),
            'storage_total' => $this->formatBytes($storageTotal),
            'system_load' => $load,
            'php_version' => $phpVersion,
            'memory_limit' => $memoryLimit,
            'max_execution_time' => $maxExecutionTime,
            'symfony_env' => $this->getParameter('kernel.environment'),
            'symfony_debug' => $this->getParameter('kernel.debug')
        ]);
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    #[Route('/system/tasks', name: 'admin_system_tasks')]
    public function systemTasks(): Response
    {
        $tasks = [
            [
                'name' => 'Отправка напоминаний о приемах',
                'command' => 'app:send-appointment-reminders',
                'schedule' => 'каждый час',
                'last_run' => $this->getLastTaskRun('send-appointment-reminders'),
                'status' => 'active'
            ],
            [
                'name' => 'Отметка неявок',
                'command' => 'app:mark-no-shows',
                'schedule' => 'каждые 30 минут',
                'last_run' => $this->getLastTaskRun('mark-no-shows'),
                'status' => 'active'
            ],
            [
                'name' => 'Очистка старых кэшей',
                'command' => 'cache:clear',
                'schedule' => 'ежедневно в 3:00',
                'last_run' => $this->getLastTaskRun('cache-clear'),
                'status' => 'active'
            ],
            [
                'name' => 'Резервное копирование базы данных',
                'command' => 'doctrine:database:backup',
                'schedule' => 'ежедневно в 2:00',
                'last_run' => $this->getLastTaskRun('database-backup'),
                'status' => 'active'
            ]
        ];

        return $this->render('admin/system/tasks.html.twig', [
            'tasks' => $tasks
        ]);
    }

    private function getLastTaskRun(string $task): ?string
    {
        return null;
    }
}