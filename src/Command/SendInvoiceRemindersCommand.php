<?php

namespace App\Command;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SendInvoiceRemindersCommand extends Command
{
    protected static $defaultName = 'app:send-invoice-reminders';
    protected static $defaultDescription = 'Send invoice payment reminders';

    private InvoiceRepository $invoiceRepository;
    private NotificationService $notificationService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        InvoiceRepository $invoiceRepository,
        NotificationService $notificationService,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->invoiceRepository = $invoiceRepository;
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
        $io->title('Отправка напоминаний об оплате счетов');

        $threeDaysFromNow = new \DateTime('+3 days');
        $threeDaysFromNow->setTime(0, 0, 0);

        $invoices = $this->invoiceRepository->createQueryBuilder('i')
            ->where('i.dueDate = :dueDate')
            ->andWhere('i.status IN (:statuses)')
            ->andWhere('i.reminderSent = false')
            ->setParameter('dueDate', $threeDaysFromNow->format('Y-m-d'))
            ->setParameter('statuses', [Invoice::STATUS_PENDING, Invoice::STATUS_PARTIALLY_PAID])
            ->getQuery()
            ->getResult();

        $io->text(sprintf('Найдено %d счетов, требующих напоминания', count($invoices)));

        if (empty($invoices)) {
            $io->success('Нет счетов, требующих напоминания');
            return Command::SUCCESS;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($invoices as $invoice) {
            try {
                $result = $this->notificationService->sendInvoiceReminder($invoice);
                
                if ($result) {
                    $successCount++;
                    $io->text(sprintf(
                        '✓ Напоминание отправлено для счета #%s (%s)',
                        $invoice->getInvoiceNumber(),
                        $invoice->getPatient()->getEmail()
                    ));
                } else {
                    $failCount++;
                    $io->warning(sprintf(
                        '✗ Не удалось отправить напоминание для счета #%s',
                        $invoice->getInvoiceNumber()
                    ));
                }
            } catch (\Exception $e) {
                $failCount++;
                $io->error(sprintf(
                    'Ошибка при отправке напоминания для счета #%s: %s',
                    $invoice->getInvoiceNumber(),
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