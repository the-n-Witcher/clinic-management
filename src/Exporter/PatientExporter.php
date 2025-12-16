<?php
// src/Exporter/PatientExporter.php

namespace App\Exporter;

use App\Entity\Patient;
use App\Entity\MedicalRecord;
use App\Entity\Appointment;
use App\Entity\Prescription;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PatientExporter
{
    public function exportPatientHistory(Patient $patient): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Заголовок
        $sheet->setCellValue('A1', 'Медицинская история пациента');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Информация о пациенте
        $sheet->setCellValue('A3', 'Пациент:');
        $sheet->setCellValue('B3', $patient->getFullName());
        $sheet->setCellValue('A4', 'Мед. номер:');
        $sheet->setCellValue('B4', $patient->getMedicalNumber());
        $sheet->setCellValue('A5', 'Дата рождения:');
        $sheet->setCellValue('B5', $patient->getDateOfBirth()->format('d.m.Y'));
        $sheet->setCellValue('A6', 'Возраст:');
        $sheet->setCellValue('B6', $patient->getAge() . ' лет');
        $sheet->setCellValue('A7', 'Пол:');
        $sheet->setCellValue('B7', $this->translateGender($patient->getGender()));
        $sheet->setCellValue('A8', 'Группа крови:');
        $sheet->setCellValue('B8', $patient->getBloodType() ?? 'Не указана');
        
        if ($patient->getAllergies()) {
            $sheet->setCellValue('A9', 'Аллергии:');
            $sheet->setCellValue('B9', implode(', ', $patient->getAllergies()));
        }
        
        if ($patient->getChronicDiseases()) {
            $sheet->setCellValue('A10', 'Хронические заболевания:');
            $sheet->setCellValue('B10', implode(', ', $patient->getChronicDiseases()));
        }

        // Приемы
        $row = 12;
        $sheet->setCellValue('A' . $row, 'История приемов');
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
        
        $row++;
        $this->addAppointmentHeaders($sheet, $row);
        $row++;

        $appointments = $patient->getAppointments();
        foreach ($appointments as $appointment) {
            $sheet->setCellValue('A' . $row, $appointment->getStartTime()->format('d.m.Y H:i'));
            $sheet->setCellValue('B' . $row, $appointment->getDoctor()->getFullName());
            $sheet->setCellValue('C' . $row, $appointment->getDoctor()->getSpecialization());
            $sheet->setCellValue('D' . $row, $this->translateStatus($appointment->getStatus()));
            $sheet->setCellValue('E' . $row, $appointment->getReason());
            $row++;
        }

        // Медицинские записи
        $row += 2;
        $sheet->setCellValue('A' . $row, 'Медицинские записи');
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
        
        $row++;
        $this->addMedicalRecordHeaders($sheet, $row);
        $row++;

        $medicalRecords = $patient->getMedicalRecords();
        foreach ($medicalRecords as $record) {
            $sheet->setCellValue('A' . $row, $record->getRecordDate()->format('d.m.Y'));
            $sheet->setCellValue('B' . $row, $record->getDoctor()->getFullName());
            $sheet->setCellValue('C' . $row, $this->translateRecordType($record->getType()));
            $sheet->setCellValue('D' . $row, $record->getNotes() ?? '');
            
            // Форматируем данные
            $data = $record->getData();
            $formattedData = [];
            
            if (!empty($data['vital_signs'])) {
                $formattedData[] = 'Показатели: ' . json_encode($data['vital_signs'], JSON_UNESCAPED_UNICODE);
            }
            if (!empty($data['symptoms'])) {
                $formattedData[] = 'Симптомы: ' . implode(', ', $data['symptoms']);
            }
            if (!empty($data['diagnosis'])) {
                $formattedData[] = 'Диагноз: ' . implode(', ', $data['diagnosis']);
            }
            if (!empty($data['treatment'])) {
                $formattedData[] = 'Лечение: ' . implode(', ', $data['treatment']);
            }
            
            $sheet->setCellValue('E' . $row, implode('; ', $formattedData));
            $row++;
        }

        // Рецепты
        $row += 2;
        $sheet->setCellValue('A' . $row, 'Рецепты');
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
        
        $row++;
        $this->addPrescriptionHeaders($sheet, $row);
        $row++;

        $prescriptions = $patient->getPrescriptions();
        foreach ($prescriptions as $prescription) {
            $sheet->setCellValue('A' . $row, $prescription->getPrescribedDate()->format('d.m.Y'));
            $sheet->setCellValue('B' . $row, $prescription->getDoctor()->getFullName());
            $sheet->setCellValue('C' . $row, $prescription->getValidUntil()->format('d.m.Y'));
            $sheet->setCellValue('D' . $row, $prescription->isActive() ? 'Активен' : 'Завершен');
            
            // Форматируем лекарства
            $medications = [];
            foreach ($prescription->getMedications() as $med) {
                $medications[] = sprintf('%s - %s, %s', 
                    $med['name'], 
                    $med['dosage'], 
                    $med['frequency']
                );
            }
            $sheet->setCellValue('E' . $row, implode('; ', $medications));
            $row++;
        }

        // Автоподбор ширины колонок
        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Границы для таблиц
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];

        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A3:E' . $lastRow)->applyFromArray($styleArray);

        // Сохраняем во временный файл
        $tempFile = tempnam(sys_get_temp_dir(), 'patient_history_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    private function addAppointmentHeaders($sheet, int $row): void
    {
        $headers = [
            'A' => 'Дата и время',
            'B' => 'Врач',
            'C' => 'Специальность',
            'D' => 'Статус',
            'E' => 'Причина'
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0F0F0');
        }
    }

    private function addMedicalRecordHeaders($sheet, int $row): void
    {
        $headers = [
            'A' => 'Дата',
            'B' => 'Врач',
            'C' => 'Тип записи',
            'D' => 'Заметки',
            'E' => 'Данные'
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0F0F0');
        }
    }

    private function addPrescriptionHeaders($sheet, int $row): void
    {
        $headers = [
            'A' => 'Дата выписки',
            'B' => 'Врач',
            'C' => 'Действителен до',
            'D' => 'Статус',
            'E' => 'Лекарства'
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0F0F0');
        }
    }

    private function translateGender(string $gender): string
    {
        return match($gender) {
            'male' => 'Мужской',
            'female' => 'Женский',
            default => 'Другой'
        };
    }

    private function translateStatus(string $status): string
    {
        return match($status) {
            'scheduled' => 'Запланирован',
            'confirmed' => 'Подтвержден',
            'completed' => 'Завершен',
            'cancelled' => 'Отменен',
            'no_show' => 'Неявка',
            default => $status
        };
    }

    private function translateRecordType(string $type): string
    {
        return match($type) {
            'consultation' => 'Консультация',
            'diagnosis' => 'Диагноз',
            'lab_result' => 'Анализы',
            'imaging' => 'Визуализация',
            'procedure' => 'Процедура',
            'vaccination' => 'Вакцинация',
            'other' => 'Другое',
            default => $type
        };
    }
}