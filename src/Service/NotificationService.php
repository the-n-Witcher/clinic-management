<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\Invoice;
use App\Entity\Patient;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    private MailerInterface $mailer;
    private UrlGeneratorInterface $urlGenerator;
    private ParameterBagInterface $params;
    private LoggerInterface $logger;
    private Environment $twig;
    private EntityManagerInterface $entityManager;
    private ?SmsService $smsService;

    public function __construct(
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
        ParameterBagInterface $params,
        LoggerInterface $logger,
        Environment $twig,
        EntityManagerInterface $entityManager,
        ?SmsService $smsService = null
    ) {
        $this->mailer = $mailer;
        $this->urlGenerator = $urlGenerator;
        $this->params = $params;
        $this->logger = $logger;
        $this->twig = $twig;
        $this->entityManager = $entityManager;
        $this->smsService = $smsService;
    }

    public function sendAppointmentUpdated(Appointment $appointment, bool $sendSms = false): bool
    {
        $patient = $appointment->getPatient();
        $doctor = $appointment->getDoctor();

        try {
            $email = (new TemplatedEmail())
                ->from($this->params->get('clinic_email'))
                ->to($patient->getEmail())
                ->subject('Изменение в записи на прием')
                ->htmlTemplate('email/appointment_updated.html.twig')
                ->context([
                    'patient' => $patient,
                    'doctor' => $doctor,
                    'appointment' => $appointment,
                    'old_date' => $appointment->getStartTime()->format('d.m.Y H:i'),
                    'new_date' => $appointment->getStartTime()->format('d.m.Y H:i'),
                    'clinic_name' => $this->params->get('clinic_name'),
                    'clinic_phone' => $this->params->get('clinic_phone'),
                    'cancel_url' => $this->urlGenerator->generate(
                        'appointment_cancel', 
                        ['id' => $appointment->getId()],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    )
                ]);

            $this->mailer->send($email);

            if ($doctor->getUser() && $doctor->getUser()->getEmail()) {
                $doctorEmail = (new TemplatedEmail())
                    ->from($this->params->get('clinic_email'))
                    ->to($doctor->getUser()->getEmail())
                    ->subject('Изменение в записи на прием')
                    ->htmlTemplate('email/appointment_updated_doctor.html.twig')
                    ->context([
                        'patient' => $patient,
                        'appointment' => $appointment,
                        'new_date' => $appointment->getStartTime()->format('d.m.Y H:i')
                    ]);

                $this->mailer->send($doctorEmail);
            }

            if ($sendSms && $this->smsService && $patient->getPhone()) {
                $message = sprintf(
                    "Прием у %s изменен на %s %s",
                    $doctor->getFullName(),
                    $appointment->getStartTime()->format('d.m.Y'),
                    $appointment->getStartTime()->format('H:i')
                );
                $this->smsService->sendSms($patient->getPhone(), $message);
            }

            $this->logger->info('Appointment updated notification sent', [
                'appointment_id' => $appointment->getId(),
                'patient_email' => $patient->getEmail()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send appointment updated notification', [
                'appointment_id' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
    

    public function sendAppointmentReminder(Appointment $appointment, bool $sendSms = false): bool
    {
        $patient = $appointment->getPatient();
        $doctor = $appointment->getDoctor();

        try {
            $email = (new TemplatedEmail())
                ->from($this->params->get('clinic_email'))
                ->to($patient->getEmail())
                ->subject('Напоминание о приеме')
                ->htmlTemplate('email/appointment_reminder.html.twig')
                ->context([
                    'patient' => $patient,
                    'doctor' => $doctor,
                    'appointment' => $appointment,
                    'date' => $appointment->getStartTime()->format('d.m.Y'),
                    'time' => $appointment->getStartTime()->format('H:i'),
                    'cancel_url' => $this->urlGenerator->generate(
                        'appointment_cancel', 
                        ['id' => $appointment->getId()],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                    'clinic_name' => $this->params->get('clinic_name'),
                    'clinic_phone' => $this->params->get('clinic_phone')
                ]);

            $this->mailer->send($email);

            if ($sendSms && $this->smsService && $patient->getPhone()) {
                $message = sprintf(
                    "Напоминание: прием у %s завтра в %s. Телефон: %s",
                    $doctor->getFullName(),
                    $appointment->getStartTime()->format('H:i'),
                    $this->params->get('clinic_phone')
                );
                $this->smsService->sendSms($patient->getPhone(), $message);
            }

            $appointment->setReminderSent(true);
            $this->entityManager->persist($appointment);
            $this->entityManager->flush();

            $this->logger->info('Appointment reminder sent', [
                'appointment_id' => $appointment->getId(),
                'patient_email' => $patient->getEmail()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send appointment reminder', [
                'appointment_id' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendAppointmentCancelled(Appointment $appointment, string $reason, bool $sendSms = false): bool
    {
        $patient = $appointment->getPatient();
        $doctor = $appointment->getDoctor();

        try {
            $email = (new TemplatedEmail())
                ->from($this->params->get('clinic_email'))
                ->to($patient->getEmail())
                ->subject('Отмена приема')
                ->htmlTemplate('email/appointment_cancelled.html.twig')
                ->context([
                    'patient' => $patient,
                    'doctor' => $doctor,
                    'appointment' => $appointment,
                    'reason' => $reason,
                    'date' => $appointment->getStartTime()->format('d.m.Y'),
                    'time' => $appointment->getStartTime()->format('H:i'),
                    'clinic_name' => $this->params->get('clinic_name'),
                    'clinic_phone' => $this->params->get('clinic_phone')
                ]);

            $this->mailer->send($email);

            if ($doctor->getUser() && $doctor->getUser()->getEmail()) {
                $doctorEmail = (new TemplatedEmail())
                    ->from($this->params->get('clinic_email'))
                    ->to($doctor->getUser()->getEmail())
                    ->subject('Отмена приема')
                    ->htmlTemplate('email/appointment_cancelled_doctor.html.twig')
                    ->context([
                        'patient' => $patient,
                        'appointment' => $appointment,
                        'reason' => $reason
                    ]);

                $this->mailer->send($doctorEmail);
            }

            if ($sendSms && $this->smsService && $patient->getPhone()) {
                $message = sprintf(
                    "Прием у %s на %s %s отменен. Причина: %s",
                    $doctor->getFullName(),
                    $appointment->getStartTime()->format('d.m.Y'),
                    $appointment->getStartTime()->format('H:i'),
                    $reason
                );
                $this->smsService->sendSms($patient->getPhone(), $message);
            }

            $this->logger->info('Appointment cancellation notification sent', [
                'appointment_id' => $appointment->getId(),
                'patient_email' => $patient->getEmail(),
                'reason' => $reason
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send appointment cancellation notification', [
                'appointment_id' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendInvoiceCreated(Invoice $invoice, bool $sendSms = false): bool
    {
        $patient = $invoice->getPatient();

        try {
            $email = (new TemplatedEmail())
                ->from($this->params->get('clinic_email'))
                ->to($patient->getEmail())
                ->subject('Новый счет для оплаты')
                ->htmlTemplate('email/invoice_created.html.twig')
                ->context([
                    'patient' => $patient,
                    'invoice' => $invoice,
                    'amount' => $invoice->getAmount(),
                    'due_date' => $invoice->getDueDate()->format('d.m.Y'),
                    'invoice_number' => $invoice->getInvoiceNumber(),
                    'pay_url' => $this->urlGenerator->generate(
                        'invoice_pay', 
                        ['id' => $invoice->getId()],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                    'clinic_name' => $this->params->get('clinic_name'),
                    'clinic_phone' => $this->params->get('clinic_phone')
                ]);

            $this->mailer->send($email);

            if ($sendSms && $this->smsService && $patient->getPhone()) {
                $message = sprintf(
                    "Счет #%s на сумму %s руб. к оплате до %s",
                    $invoice->getInvoiceNumber(),
                    $invoice->getAmount(),
                    $invoice->getDueDate()->format('d.m.Y')
                );
                $this->smsService->sendSms($patient->getPhone(), $message);
            }

            $this->logger->info('Invoice created notification sent', [
                'invoice_id' => $invoice->getId(),
                'patient_email' => $patient->getEmail(),
                'invoice_number' => $invoice->getInvoiceNumber()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send invoice created notification', [
                'invoice_id' => $invoice->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendInvoiceReminder(Invoice $invoice, bool $sendSms = false): bool
    {
        $patient = $invoice->getPatient();
        
        $dueDate = \DateTime::createFromInterface($invoice->getDueDate());
        $today = new \DateTime();
        
        $dueDate->setTime(0, 0, 0);
        $today->setTime(0, 0, 0);
        
        $interval = $today->diff($dueDate);
        $daysUntilDue = (int) $interval->format('%r%a');

        try {
            $email = (new TemplatedEmail())
                ->from($this->params->get('clinic_email'))
                ->to($patient->getEmail())
                ->subject('Напоминание об оплате счета')
                ->htmlTemplate('email/invoice_reminder.html.twig')
                ->context([
                    'patient' => $patient,
                    'invoice' => $invoice,
                    'amount' => $invoice->getAmount(),
                    'due_date' => $invoice->getDueDate()->format('d.m.Y'),
                    'days_until_due' => $daysUntilDue,
                    'invoice_number' => $invoice->getInvoiceNumber(),
                    'pay_url' => $this->urlGenerator->generate(
                        'invoice_pay', 
                        ['id' => $invoice->getId()],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                    'clinic_name' => $this->params->get('clinic_name'),
                    'clinic_phone' => $this->params->get('clinic_phone')
                ]);

            $this->mailer->send($email);

            if ($sendSms && $this->smsService && $patient->getPhone()) {
                if ($daysUntilDue === 0) {
                    $message = "Счет #{$invoice->getInvoiceNumber()} должен быть оплачен сегодня!";
                } else {
                    $message = "Напоминание: счет #{$invoice->getInvoiceNumber()} к оплате через {$daysUntilDue} дн.";
                }
                $this->smsService->sendSms($patient->getPhone(), $message);
            }

            $invoice->setReminderSent(true);
            $this->entityManager->persist($invoice);
            $this->entityManager->flush();

            $this->logger->info('Invoice reminder sent', [
                'invoice_id' => $invoice->getId(),
                'patient_email' => $patient->getEmail(),
                'days_until_due' => $daysUntilDue
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send invoice reminder', [
                'invoice_id' => $invoice->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendPaymentReceived(Invoice $invoice, string $paymentAmount, bool $sendSms = false): bool
    {
        $patient = $invoice->getPatient();

        try {
            $email = (new TemplatedEmail())
                ->from($this->params->get('clinic_email'))
                ->to($patient->getEmail())
                ->subject('Оплата получена')
                ->htmlTemplate('email/payment_received.html.twig')
                ->context([
                    'patient' => $patient,
                    'invoice' => $invoice,
                    'payment_amount' => $paymentAmount,
                    'invoice_number' => $invoice->getInvoiceNumber(),
                    'balance' => $invoice->getBalance(),
                    'clinic_name' => $this->params->get('clinic_name')
                ]);

            $this->mailer->send($email);

            if ($sendSms && $this->smsService && $patient->getPhone()) {
                $message = sprintf(
                    "Оплата по счету #%s на сумму %s руб. получена. Остаток: %s руб.",
                    $invoice->getInvoiceNumber(),
                    $paymentAmount,
                    $invoice->getBalance()
                );
                $this->smsService->sendSms($patient->getPhone(), $message);
            }

            $this->logger->info('Payment received notification sent', [
                'invoice_id' => $invoice->getId(),
                'patient_email' => $patient->getEmail(),
                'payment_amount' => $paymentAmount
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send payment received notification', [
                'invoice_id' => $invoice->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendMedicalRecordAvailable(Patient $patient, string $recordType, \DateTimeInterface $date, bool $sendSms = false): bool
    {
        try {
            $email = (new TemplatedEmail())
                ->from($this->params->get('clinic_email'))
                ->to($patient->getEmail())
                ->subject('Медицинская запись доступна')
                ->htmlTemplate('email/medical_record_available.html.twig')
                ->context([
                    'patient' => $patient,
                    'record_type' => $recordType,
                    'date' => $date->format('d.m.Y'),
                    'patient_portal_url' => $this->urlGenerator->generate(
                        'patient_medical_records',
                        [],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                    'clinic_name' => $this->params->get('clinic_name')
                ]);

            $this->mailer->send($email);

            if ($sendSms && $this->smsService && $patient->getPhone()) {
                $message = sprintf(
                    "Медицинская запись (%s) от %s доступна в личном кабинете",
                    $recordType,
                    $date->format('d.m.Y')
                );
                $this->smsService->sendSms($patient->getPhone(), $message);
            }

            $this->logger->info('Medical record available notification sent', [
                'patient_id' => $patient->getId(),
                'record_type' => $recordType,
                'patient_email' => $patient->getEmail()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send medical record available notification', [
                'patient_id' => $patient->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendPrescriptionReady(Patient $patient, \DateTimeInterface $prescriptionDate, bool $sendSms = false): bool
    {
        try {
            $validUntil = \DateTime::createFromInterface($prescriptionDate);
            $validUntil->modify('+30 days');
            
            $email = (new TemplatedEmail())
                ->from($this->params->get('clinic_email'))
                ->to($patient->getEmail())
                ->subject('Рецепт готов')
                ->htmlTemplate('email/prescription_ready.html.twig')
                ->context([
                    'patient' => $patient,
                    'prescription_date' => $prescriptionDate->format('d.m.Y'),
                    'valid_until' => $validUntil->format('d.m.Y'),
                    'patient_portal_url' => $this->urlGenerator->generate(
                        'patient_prescriptions',
                        [],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                    'clinic_name' => $this->params->get('clinic_name'),
                    'clinic_phone' => $this->params->get('clinic_phone')
                ]);

            $this->mailer->send($email);

            if ($sendSms && $this->smsService && $patient->getPhone()) {
                $message = sprintf(
                    "Рецепт от %s готов. Действителен до %s",
                    $prescriptionDate->format('d.m.Y'),
                    $validUntil->format('d.m.Y')
                );
                $this->smsService->sendSms($patient->getPhone(), $message);
            }

            $this->logger->info('Prescription ready notification sent', [
                'patient_id' => $patient->getId(),
                'patient_email' => $patient->getEmail()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send prescription ready notification', [
                'patient_id' => $patient->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendSystemAlert(string $subject, string $message, array $recipients = []): bool
    {
        try {
            if (empty($recipients)) {
                $recipients = [$this->params->get('admin_email')];
            }

            $email = (new Email())
                ->from($this->params->get('clinic_email'))
                ->to(...$recipients)
                ->subject('[Системное уведомление] ' . $subject)
                ->text($message)
                ->html($this->twig->render('email/system_alert.html.twig', [
                    'subject' => $subject,
                    'message' => $message,
                    'timestamp' => new \DateTime()
                ]));

            $this->mailer->send($email);

            $this->logger->info('System alert sent', [
                'subject' => $subject,
                'recipients' => $recipients
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send system alert', [
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendWelcomeEmail(User $user): bool
    {
        try {
            $email = (new TemplatedEmail())
                ->from($this->params->get('clinic_email'))
                ->to($user->getEmail())
                ->subject('Добро пожаловать в ' . $this->params->get('clinic_name'))
                ->htmlTemplate('email/welcome.html.twig')
                ->context([
                    'user' => $user,
                    'login_url' => $this->urlGenerator->generate(
                        'app_login',
                        [],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                    'clinic_name' => $this->params->get('clinic_name'),
                    'clinic_phone' => $this->params->get('clinic_phone'),
                    'clinic_address' => $this->params->get('clinic_address')
                ]);

            $this->mailer->send($email);

            $this->logger->info('Welcome email sent', [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send welcome email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function sendPasswordReset(User $user, string $resetToken): bool
    {
        try {
            $email = (new TemplatedEmail())
                ->from($this->params->get('clinic_email'))
                ->to($user->getEmail())
                ->subject('Сброс пароля')
                ->htmlTemplate('email/password_reset.html.twig')
                ->context([
                    'user' => $user,
                    'reset_url' => $this->urlGenerator->generate(
                        'app_reset_password',
                        ['token' => $resetToken],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                    'expires_in' => '24 часа',
                    'clinic_name' => $this->params->get('clinic_name')
                ]);

            $this->mailer->send($email);

            $this->logger->info('Password reset email sent', [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send password reset email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function batchSendAppointmentReminders(array $appointments): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($appointments as $appointment) {
            try {
                $success = $this->sendAppointmentReminder($appointment);
                if ($success) {
                    $results['success']++;
                    $results['details'][] = [
                        'appointment_id' => $appointment->getId(),
                        'status' => 'success',
                        'patient_email' => $appointment->getPatient()->getEmail()
                    ];
                } else {
                    $results['failed']++;
                    $results['details'][] = [
                        'appointment_id' => $appointment->getId(),
                        'status' => 'failed',
                        'error' => 'Unknown error'
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'appointment_id' => $appointment->getId(),
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    public function batchSendInvoiceReminders(array $invoices): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($invoices as $invoice) {
            try {
                $success = $this->sendInvoiceReminder($invoice);
                if ($success) {
                    $results['success']++;
                    $results['details'][] = [
                        'invoice_id' => $invoice->getId(),
                        'status' => 'success',
                        'patient_email' => $invoice->getPatient()->getEmail()
                    ];
                } else {
                    $results['failed']++;
                    $results['details'][] = [
                        'invoice_id' => $invoice->getId(),
                        'status' => 'failed',
                        'error' => 'Unknown error'
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'invoice_id' => $invoice->getId(),
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}