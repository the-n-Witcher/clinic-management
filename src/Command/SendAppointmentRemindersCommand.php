<?php

namespace App\Command;

use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SendAppointmentRemindersCommand extends Command
{
    protected static $defaultName = 'app:send-appointment-reminders';
    protected static $defaultDescription = 'Send appointment reminders to patients';

    private AppointmentRepository $appointmentRepository;
    private NotificationService $notificationService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        AppointmentRepository $appointmentRepository,
        NotificationService $notificationService,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->appointmentRepository = $appointmentRepository;
        $this->notificationService = $notificationService;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Отправка напоминаний о приемах');

        $tomorrow = new \DateTime('tomorrow');
        $tomorrow->setTime(0, 0, 0);
        $endOfTomorrow = clone $tomorrow;
        $endOfTomorrow->setTime(23, 59, 59);

        $appointments = $this->appointmentRepository->createQueryBuilder('a')
            ->where('a.startTime BETWEEN :start AND :end')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere('a.reminderSent = false')
            ->setParameter('start', $tomorrow)
            ->setParameter('end', $endOfTomorrow)
            ->setParameter('statuses', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED])
            ->getQuery()
            ->getResult();

        $io->text(sprintf('Найдено %d приемов, требующих напоминания', count($appointments)));

        if (empty($appointments)) {
            $io->success('Нет приемов, требующих напоминания');
            return Command::SUCCESS;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($appointments as $appointment) {
            try {
                $result = $this->notificationService->sendAppointmentReminder($appointment);
                
                if ($result) {
                    $successCount++;
                    $io->text(sprintf(
                        '✓ Напоминание отправлено для приема #%d (%s)',
                        $appointment->getId(),
                        $appointment->getPatient()->getEmail()
                    ));
                } else {
                    $failCount++;
                    $io->warning(sprintf(
                        '✗ Не удалось отправить напоминание для приема #%d',
                        $appointment->getId()
                    ));
                }
            } catch (\Exception $e) {
                $failCount++;
                $io->error(sprintf(
                    'Ошибка при отправке напоминания для приема #%d: %s',
                    $appointment->getId(),
                    $e->getMessage()
                ));
            }
        }

        $this->entityManager->flush();

        if ($failCount === 0) {
            $io->success(sprintf('Успешно отправлено %d напоминаний', $successCount));
        } else {
            $io->warning(sprintf('Отправлено %d напоминаний, не удалось отправить %d', $successCount, $failCount));
        }

        return $failCount === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}