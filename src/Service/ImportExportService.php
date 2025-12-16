<?php

namespace App\Service;

use App\Entity\Patient;
use App\Entity\Doctor;
use App\Entity\Appointment;
use App\Repository\PatientRepository;
use App\Repository\DoctorRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class ImportExportService
{
    private EntityManagerInterface $entityManager;
    private PatientRepository $patientRepository;
    private DoctorRepository $doctorRepository;
    private ValidatorInterface $validator;
    private Environment $twig;
    private AuditLogger $auditLogger;

    public function __construct(
        EntityManagerInterface $entityManager,
        PatientRepository $patientRepository,
        DoctorRepository $doctorRepository,
        ValidatorInterface $validator,
        Environment $twig,
        AuditLogger $auditLogger
    ) {
        $this->entityManager = $entityManager;
        $this->patientRepository = $patientRepository;
        $this->doctorRepository = $doctorRepository;
        $this->validator = $validator;
        $this->twig = $twig;
        $this->auditLogger = $auditLogger;
    }

    public function importPatientsFromExcel(UploadedFile $file, array $options = []): array
    {
        $results = [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            array_shift($rows);

            foreach ($rows as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2; 
                
                try {
                    
                    $patientData = [
                        'lastName' => $row[0] ?? '',
                        'firstName' => $row[1] ?? '',
                        'middleName' => $row[2] ?? null,
                        'dateOfBirth' => $row[3] ?? null,
                        'gender' => $row[4] ?? 'other',
                        'email' => $row[5] ?? '',
                        'phone' => $row[6] ?? '',
                        'address' => $row[7] ?? null,
                        'insuranceCompany' => $row[8] ?? null,
                        'insurancePolicy' => $row[9] ?? null,
                        'bloodType' => $row[10] ?? null,
                        'allergies' => $row[11] ? explode(',', $row[11]) : null,
                        'chronicDiseases' => $row[12] ? explode(',', $row[12]) : null,
                    ];

                    if (empty($patientData['firstName']) || empty($patientData['lastName']) || empty($patientData['email'])) {
                        $results['errors'][] = "Row {$rowNumber}: Missing required fields";
                        $results['skipped']++;
                        continue;
                    }

                    $existingPatient = $this->patientRepository->findOneBy(['email' => $patientData['email']]);
                    
                    if ($existingPatient) {
                        if ($options['update_existing'] ?? false) {
                            $this->updatePatientFromData($existingPatient, $patientData);
                            $this->entityManager->flush();
                            $results['updated']++;
                        } else {
                            $results['skipped']++;
                            $results['errors'][] = "Row {$rowNumber}: Patient already exists (email: {$patientData['email']})";
                        }
                    } else {
                        $patient = $this->createPatientFromData($patientData);
                        
                        $errors = $this->validator->validate($patient);
                        if (count($errors) > 0) {
                            $errorMessages = [];
                            foreach ($errors as $error) {
                                $errorMessages[] = $error->getMessage();
                            }
                            $results['errors'][] = "Row {$rowNumber}: " . implode(', ', $errorMessages);
                            $results['skipped']++;
                            continue;
                        }

                        $this->entityManager->persist($patient);
                        $results['imported']++;
                    }

                    $results['total']++;

                    if ($results['total'] % 50 === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                    }

                } catch (\Exception $e) {
                    $results['errors'][] = "Row {$rowNumber}: " . $e->getMessage();
                    $results['skipped']++;
                }
            }

            $this->entityManager->flush();

            $this->auditLogger->log(
                'PATIENTS_IMPORTED',
                [
                    'file' => $file->getClientOriginalName(),
                    'total' => $results['total'],
                    'imported' => $results['imported'],
                    'updated' => $results['updated'],
                    'skipped' => $results['skipped']
                ],
                'patient_import'
            );

        } catch (\Exception $e) {
            $results['errors'][] = "File error: " . $e->getMessage();
        }

        return $results;
    }

    private function createPatientFromData(array $data): Patient
    {
        $patient = new Patient();
        
        $patient->setLastName($data['lastName']);
        $patient->setFirstName($data['firstName']);
        $patient->setMiddleName($data['middleName']);
        
        if ($data['dateOfBirth']) {
            if ($data['dateOfBirth'] instanceof \DateTime) {
                $patient->setDateOfBirth($data['dateOfBirth']);
            } else {
                $patient->setDateOfBirth(new \DateTime($data['dateOfBirth']));
            }
        }
        
        $patient->setGender($data['gender']);
        $patient->setEmail($data['email']);
        $patient->setPhone($data['phone']);
        $patient->setAddress($data['address']);
        $patient->setInsuranceCompany($data['insuranceCompany']);
        $patient->setInsurancePolicy($data['insurancePolicy']);
        $patient->setBloodType($data['bloodType']);
        $patient->setAllergies($data['allergies']);
        $patient->setChronicDiseases($data['chronicDiseases']);
        $patient->setConsentToDataProcessing(true);

        return $patient;
    }

    private function updatePatientFromData(Patient $patient, array $data): void
    {
        if (!empty($data['lastName'])) $patient->setLastName($data['lastName']);
        if (!empty($data['firstName'])) $patient->setFirstName($data['firstName']);
        if (isset($data['middleName'])) $patient->setMiddleName($data['middleName']);
        
        if ($data['dateOfBirth']) {
            if ($data['dateOfBirth'] instanceof \DateTime) {
                $patient->setDateOfBirth($data['dateOfBirth']);
            } else {
                $patient->setDateOfBirth(new \DateTime($data['dateOfBirth']));
            }
        }
        
        if (!empty($data['gender'])) $patient->setGender($data['gender']);
        if (!empty($data['email'])) $patient->setEmail($data['email']);
        if (!empty($data['phone'])) $patient->setPhone($data['phone']);
        if (isset($data['address'])) $patient->setAddress($data['address']);
        if (isset($data['insuranceCompany'])) $patient->setInsuranceCompany($data['insuranceCompany']);
        if (isset($data['insurancePolicy'])) $patient->setInsurancePolicy($data['insurancePolicy']);
        if (isset($data['bloodType'])) $patient->setBloodType($data['bloodType']);
        if (isset($data['allergies'])) $patient->setAllergies($data['allergies']);
        if (isset($data['chronicDiseases'])) $patient->setChronicDiseases($data['chronicDiseases']);
    }

    public function exportPatientsToExcel(array $filters = []): string
    {
        $patients = $this->patientRepository->findByFilters($filters);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'A1' => 'Фамилия',
            'B1' => 'Имя',
            'C1' => 'Отчество',
            'D1' => 'Дата рождения',
            'E1' => 'Пол',
            'F1' => 'Мед. номер',
            'G1' => 'Email',
            'H1' => 'Телефон',
            'I1' => 'Адрес',
            'J1' => 'Страховая компания',
            'K1' => 'Номер полиса',
            'L1' => 'Группа крови',
            'M1' => 'Аллергии',
            'N1' => 'Хронические заболевания',
            'O1' => 'Дата регистрации'
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $row = 2;
        foreach ($patients as $patient) {
            $sheet->setCellValue('A' . $row, $patient->getLastName());
            $sheet->setCellValue('B' . $row, $patient->getFirstName());
            $sheet->setCellValue('C' . $row, $patient->getMiddleName() ?? '');
            $sheet->setCellValue('D' . $row, $patient->getDateOfBirth()->format('d.m.Y'));
            $sheet->setCellValue('E' . $row, $this->translateGender($patient->getGender()));
            $sheet->setCellValue('F' . $row, $patient->getMedicalNumber());
            $sheet->setCellValue('G' . $row, $patient->getEmail());
            $sheet->setCellValue('H' . $row, $patient->getPhone());
            $sheet->setCellValue('I' . $row, $patient->getAddress() ?? '');
            $sheet->setCellValue('J' . $row, $patient->getInsuranceCompany() ?? '');
            $sheet->setCellValue('K' . $row, $patient->getInsurancePolicy() ?? '');
            $sheet->setCellValue('L' . $row, $patient->getBloodType() ?? '');
            $sheet->setCellValue('M' . $row, $patient->getAllergies() ? implode(', ', $patient->getAllergies()) : '');
            $sheet->setCellValue('N' . $row, $patient->getChronicDiseases() ? implode(', ', $patient->getChronicDiseases()) : '');
            $sheet->setCellValue('O' . $row, $patient->getCreatedAt()->format('d.m.Y H:i'));
            $row++;
        }

        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'E0E0E0']
            ]
        ];
        $sheet->getStyle('A1:O1')->applyFromArray($headerStyle);

        $tempFile = tempnam(sys_get_temp_dir(), 'patients_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    public function generateMedicalHistoryPdf(Patient $patient): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $appointments = $patient->getAppointments()->filter(
            fn(Appointment $a) => $a->getStatus() === Appointment::STATUS_COMPLETED
        )->toArray();

        usort($appointments, fn($a, $b) => $b->getStartTime() <=> $a->getStartTime());

        $medicalRecords = $patient->getMedicalRecords()->toArray();
        usort($medicalRecords, fn($a, $b) => $b->getRecordDate() <=> $a->getRecordDate());

        $prescriptions = $patient->getPrescriptions()->filter(
            fn($p) => $p->isActive() || $p->isCompleted()
        )->toArray();
        usort($prescriptions, fn($a, $b) => $b->getPrescribedDate() <=> $a->getPrescribedDate());

        $html = $this->twig->render('pdf/medical_history.html.twig', [
            'patient' => $patient,
            'appointments' => $appointments,
            'medical_records' => $medicalRecords,
            'prescriptions' => $prescriptions,
            'generated_at' => new \DateTime(),
            'clinic_name' => 'Медицинская клиника',
            'clinic_address' => 'г. Москва, ул. Медицинская, д. 1',
            'clinic_phone' => '+7 (999) 123-45-67'
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $tempFile = tempnam(sys_get_temp_dir(), 'medical_history_');
        file_put_contents($tempFile, $dompdf->output());

        $this->auditLogger->log(
            'MEDICAL_HISTORY_EXPORTED',
            [
                'patient_id' => $patient->getId(),
                'patient_name' => $patient->getFullName(),
                'format' => 'PDF'
            ],
            'patient',
            $patient->getId()
        );

        return $tempFile;
    }

    public function generateAppointmentReportPdf(array $reportData): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);

        $html = $this->twig->render('pdf/appointment_report.html.twig', [
            'report' => $reportData,
            'generated_at' => new \DateTime()
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $tempFile = tempnam(sys_get_temp_dir(), 'appointment_report_');
        file_put_contents($tempFile, $dompdf->output());

        return $tempFile;
    }

    private function translateGender(string $gender): string
    {
        return match ($gender) {
            'male' => 'Мужской',
            'female' => 'Женский',
            default => 'Другой'
        };
    }

    public function exportStatisticsToExcel(array $statistics, string $reportType): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Отчет: ' . $reportType);
        $sheet->setCellValue('A2', 'Сгенерирован: ' . date('d.m.Y H:i:s'));

        $row = 4;

        switch ($reportType) {
            case 'patient_statistics':
                $this->addPatientStatistics($sheet, $statistics, $row);
                break;
            case 'appointment_statistics':
                $this->addAppointmentStatistics($sheet, $statistics, $row);
                break;
            case 'financial_statistics':
                $this->addFinancialStatistics($sheet, $statistics, $row);
                break;
        }

        $lastColumn = $sheet->getHighestColumn();
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'statistics_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    private function addPatientStatistics($sheet, $statistics, &$row): void
    {
        $sheet->setCellValue('A' . $row, 'Общая статистика');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('A' . $row, 'Всего пациентов');
        $sheet->setCellValue('B' . $row, $statistics['total'] ?? 0);
        $row++;

        foreach ($statistics['byGender'] ?? [] as $gender => $count) {
            $sheet->setCellValue('A' . $row, 'Пол: ' . $this->translateGender($gender));
            $sheet->setCellValue('B' . $row, $count);
            $row++;
        }

        $row++;
        $sheet->setCellValue('A' . $row, 'Статистика по возрастным группам');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        foreach ($statistics['byAgeGroup'] ?? [] as $group => $count) {
            $sheet->setCellValue('A' . $row, $group . ' лет');
            $sheet->setCellValue('B' . $row, $count);
            $row++;
        }
    }

    private function addAppointmentStatistics($sheet, $statistics, &$row): void
    {
        $sheet->setCellValue('A' . $row, 'Статистика приемов');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $stats = [
            'Всего приемов' => $statistics['total'] ?? 0,
            'Запланировано' => $statistics['scheduled'] ?? 0,
            'Подтверждено' => $statistics['confirmed'] ?? 0,
            'Завершено' => $statistics['completed'] ?? 0,
            'Отменено' => $statistics['cancelled'] ?? 0,
            'Неявки' => $statistics['no_show'] ?? 0,
            'Процент завершения' => ($statistics['completion_rate'] ?? 0) . '%',
            'Средняя продолжительность' => ($statistics['avg_duration'] ?? 0) . ' мин.'
        ];

        foreach ($stats as $label => $value) {
            $sheet->setCellValue('A' . $row, $label);
            $sheet->setCellValue('B' . $row, $value);
            $row++;
        }
    }

    private function addFinancialStatistics($sheet, $statistics, &$row): void
    {
        $sheet->setCellValue('A' . $row, 'Финансовая статистика');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $stats = [
            'Общий доход' => ($statistics['total_revenue'] ?? 0) . ' руб.',
            'Ожидаемый доход' => ($statistics['expected_revenue'] ?? 0) . ' руб.',
            'Количество счетов' => $statistics['invoice_count'] ?? 0,
            'Оплачено' => $statistics['paid_invoices'] ?? 0,
            'В ожидании' => $statistics['pending_invoices'] ?? 0,
            'Просрочено' => $statistics['overdue_invoices'] ?? 0,
            'Средний чек' => ($statistics['average_invoice'] ?? 0) . ' руб.'
        ];

        foreach ($stats as $label => $value) {
            $sheet->setCellValue('A' . $row, $label);
            $sheet->setCellValue('B' . $row, $value);
            $row++;
        }
    }
}