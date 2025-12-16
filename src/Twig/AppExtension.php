<?php
// src/Twig/AppExtension.php

namespace App\Twig;

use App\Entity\Appointment;
use App\Entity\MedicalRecord;
use App\Entity\Invoice;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\Environment;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_phone', [$this, 'formatPhone']),
            new TwigFilter('format_datetime', [$this, 'formatDateTime']),
            new TwigFilter('format_date', [$this, 'formatDate']),
            new TwigFilter('format_time', [$this, 'formatTime']),
            new TwigFilter('format_currency', [$this, 'formatCurrency']),
            new TwigFilter('status_badge', [$this, 'getStatusBadge'], ['is_safe' => ['html']]),
            new TwigFilter('truncate', [$this, 'truncateText']),
            new TwigFilter('age', [$this, 'calculateAge']),
            new TwigFilter('gender_icon', [$this, 'getGenderIcon'], ['is_safe' => ['html']]),
            new TwigFilter('medical_data', [$this, 'formatMedicalData'], ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_appointment_statuses', [$this, 'getAppointmentStatuses']),
            new TwigFunction('get_medical_record_types', [$this, 'getMedicalRecordTypes']),
            new TwigFunction('get_invoice_statuses', [$this, 'getInvoiceStatuses']),
            new TwigFunction('get_genders', [$this, 'getGenders']),
            new TwigFunction('is_overdue', [$this, 'isOverdue']),
            new TwigFunction('get_days_until', [$this, 'getDaysUntil']),
            new TwigFunction('get_time_ago', [$this, 'getTimeAgo']),
        ];
    }

    public function formatPhone(string $phone): string
    {
        // Убираем все нецифровые символы кроме плюса
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Форматируем российские номера
        if (preg_match('/^(\+7|8)(\d{3})(\d{3})(\d{2})(\d{2})$/', $phone, $matches)) {
            return "+7 ({$matches[2]}) {$matches[3]}-{$matches[4]}-{$matches[5]}";
        }
        
        return $phone;
    }

    public function formatDateTime(\DateTimeInterface $dateTime, string $format = 'd.m.Y H:i'): string
    {
        return $dateTime->format($format);
    }

    public function formatDate(\DateTimeInterface $date, string $format = 'd.m.Y'): string
    {
        return $date->format($format);
    }

    public function formatTime(\DateTimeInterface $time, string $format = 'H:i'): string
    {
        return $time->format($format);
    }

    public function formatCurrency(float $amount, string $currency = '₽'): string
    {
        return number_format($amount, 2, ',', ' ') . ' ' . $currency;
    }

    public function getStatusBadge(string $status, string $type = 'appointment'): string
    {
        $badgeClasses = [
            'appointment' => [
                'scheduled' => ['bg-warning text-dark', 'Запланирован'],
                'confirmed' => ['bg-primary', 'Подтвержден'],
                'completed' => ['bg-success', 'Завершен'],
                'cancelled' => ['bg-danger', 'Отменен'],
                'no_show' => ['bg-secondary', 'Неявка']
            ],
            'invoice' => [
                'pending' => ['bg-warning text-dark', 'Ожидает оплаты'],
                'paid' => ['bg-success', 'Оплачен'],
                'partially_paid' => ['bg-info', 'Частично оплачен'],
                'cancelled' => ['bg-secondary', 'Отменен'],
                'overdue' => ['bg-danger', 'Просрочен']
            ],
            'default' => [
                'active' => ['bg-success', 'Активен'],
                'inactive' => ['bg-secondary', 'Неактивен'],
                'pending' => ['bg-warning text-dark', 'В ожидании']
            ]
        ];

        $config = $badgeClasses[$type] ?? $badgeClasses['default'];
        $config = $config[$status] ?? ['bg-secondary', ucfirst($status)];

        return sprintf('<span class="badge %s">%s</span>', $config[0], $config[1]);
    }

    public function truncateText(string $text, int $length = 100, string $ending = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $text = mb_substr($text, 0, $length - mb_strlen($ending));
        return rtrim($text) . $ending;
    }

    public function calculateAge(\DateTimeInterface $birthDate): int
    {
        $now = new \DateTime();
        return $now->diff($birthDate)->y;
    }

    public function getGenderIcon(string $gender): string
    {
        return match($gender) {
            'male' => '<i class="fas fa-mars text-primary" title="Мужской"></i>',
            'female' => '<i class="fas fa-venus text-danger" title="Женский"></i>',
            default => '<i class="fas fa-genderless text-secondary" title="Другой"></i>'
        };
    }

    public function formatMedicalData(array $data): string
    {
        $output = '<div class="medical-data">';
        
        if (!empty($data['vital_signs'])) {
            $output .= '<div class="mb-2"><strong>Жизненные показатели:</strong><br>';
            foreach ($data['vital_signs'] as $key => $value) {
                $output .= '<span class="badge bg-info me-1">' . $key . ': ' . $value . '</span>';
            }
            $output .= '</div>';
        }
        
        if (!empty($data['symptoms'])) {
            $output .= '<div class="mb-2"><strong>Симптомы:</strong><br>';
            foreach ($data['symptoms'] as $symptom) {
                $output .= '<span class="badge bg-warning me-1">' . $symptom . '</span>';
            }
            $output .= '</div>';
        }
        
        if (!empty($data['diagnosis'])) {
            $output .= '<div class="mb-2"><strong>Диагноз:</strong><br>';
            foreach ($data['diagnosis'] as $diagnosis) {
                $output .= '<span class="badge bg-danger me-1">' . $diagnosis . '</span>';
            }
            $output .= '</div>';
        }
        
        if (!empty($data['treatment'])) {
            $output .= '<div class="mb-2"><strong>Лечение:</strong><br>';
            foreach ($data['treatment'] as $treatment) {
                $output .= '<span class="badge bg-success me-1">' . $treatment . '</span>';
            }
            $output .= '</div>';
        }
        
        if (!empty($data['recommendations'])) {
            $output .= '<div class="mb-2"><strong>Рекомендации:</strong><br>';
            foreach ($data['recommendations'] as $recommendation) {
                $output .= '<div class="text-muted small">• ' . $recommendation . '</div>';
            }
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }

    public function getAppointmentStatuses(): array
    {
        return [
            Appointment::STATUS_SCHEDULED => 'Запланирован',
            Appointment::STATUS_CONFIRMED => 'Подтвержден',
            Appointment::STATUS_COMPLETED => 'Завершен',
            Appointment::STATUS_CANCELLED => 'Отменен',
            Appointment::STATUS_NO_SHOW => 'Неявка'
        ];
    }

    public function getMedicalRecordTypes(): array
    {
        return [
            MedicalRecord::TYPE_CONSULTATION => 'Консультация',
            MedicalRecord::TYPE_DIAGNOSIS => 'Диагноз',
            MedicalRecord::TYPE_LAB_RESULT => 'Результаты анализов',
            MedicalRecord::TYPE_IMAGING => 'Визуализация',
            MedicalRecord::TYPE_PROCEDURE => 'Процедура',
            MedicalRecord::TYPE_VACCINATION => 'Вакцинация',
            MedicalRecord::TYPE_OTHER => 'Другое'
        ];
    }

    public function getInvoiceStatuses(): array
    {
        return [
            Invoice::STATUS_PENDING => 'Ожидает оплаты',
            Invoice::STATUS_PAID => 'Оплачен',
            Invoice::STATUS_PARTIALLY_PAID => 'Частично оплачен',
            Invoice::STATUS_CANCELLED => 'Отменен',
            Invoice::STATUS_OVERDUE => 'Просрочен'
        ];
    }

    public function getGenders(): array
    {
        return [
            'male' => 'Мужской',
            'female' => 'Женский',
            'other' => 'Другой'
        ];
    }

    public function isOverdue(\DateTimeInterface $dueDate): bool
    {
        return new \DateTime() > $dueDate;
    }

    public function getDaysUntil(\DateTimeInterface $date): int
    {
        $now = new \DateTime();
        $interval = $now->diff($date);
        return (int) $interval->format('%r%a');
    }

    public function getTimeAgo(\DateTimeInterface $date): string
    {
        $now = new \DateTime();
        $diff = $now->diff($date);
        
        if ($diff->y > 0) {
            return $diff->y . ' ' . $this->pluralize($diff->y, ['год', 'года', 'лет']) . ' назад';
        }
        if ($diff->m > 0) {
            return $diff->m . ' ' . $this->pluralize($diff->m, ['месяц', 'месяца', 'месяцев']) . ' назад';
        }
        if ($diff->d > 0) {
            return $diff->d . ' ' . $this->pluralize($diff->d, ['день', 'дня', 'дней']) . ' назад';
        }
        if ($diff->h > 0) {
            return $diff->h . ' ' . $this->pluralize($diff->h, ['час', 'часа', 'часов']) . ' назад';
        }
        if ($diff->i > 0) {
            return $diff->i . ' ' . $this->pluralize($diff->i, ['минуту', 'минуты', 'минут']) . ' назад';
        }
        
        return 'только что';
    }

    private function pluralize(int $number, array $forms): string
    {
        $cases = [2, 0, 1, 1, 1, 2];
        return $forms[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
    }
}