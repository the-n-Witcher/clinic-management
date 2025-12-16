<?php

namespace App\Importer;

use App\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class PatientImporter
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private array $importResults = [
        'success' => 0,
        'failed' => 0,
        'errors' => []
    ];

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ) {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
    }

    public function importFromArray(array $data, array $mapping = null): array
    {
        foreach ($data as $index => $row) {
            try {
                $patient = $this->createPatientFromRow($row, $mapping);
                $this->validatePatient($patient);
                
                $this->entityManager->persist($patient);
                $this->importResults['success']++;
                
                if (($index + 1) % 50 === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
            } catch (\Exception $e) {
                $this->importResults['failed']++;
                $this->importResults['errors'][] = [
                    'row' => $index + 1,
                    'error' => $e->getMessage(),
                    'data' => $row
                ];
            }
        }

        $this->entityManager->flush();

        return $this->importResults;
    }

    private function createPatientFromRow(array $row, ?array $mapping): Patient
    {
        $patient = new Patient();
        $accessor = PropertyAccess::createPropertyAccessor();

        if ($mapping) {
            foreach ($mapping as $field => $column) {
                if (isset($row[$column])) {
                    $value = $row[$column];
                    $this->setPatientField($patient, $field, $value);
                }
            }
        } else {
            $this->autoMapFields($patient, $row);
        }

        return $patient;
    }

    private function autoMapFields(Patient $patient, array $row): void
    {
        $fieldMapping = [
            'last_name' => ['фамилия', 'lastname', 'last_name'],
            'first_name' => ['имя', 'firstname', 'first_name'],
            'middle_name' => ['отчество', 'middlename', 'middle_name'],
            'date_of_birth' => ['дата рождения', 'birthdate', 'dob', 'date_of_birth'],
            'gender' => ['пол', 'gender'],
            'email' => ['email', 'e-mail'],
            'phone' => ['телефон', 'phone', 'телефон'],
            'address' => ['адрес', 'address'],
            'insurance_company' => ['страховая', 'insurance', 'insurance_company'],
            'insurance_policy' => ['номер полиса', 'policy', 'insurance_policy'],
            'blood_type' => ['группа крови', 'blood', 'blood_type'],
            'allergies' => ['аллергии', 'allergies'],
            'chronic_diseases' => ['хронические заболевания', 'diseases', 'chronic_diseases']
        ];

        foreach ($row as $header => $value) {
            $headerLower = mb_strtolower($header);
            
            foreach ($fieldMapping as $field => $possibleHeaders) {
                foreach ($possibleHeaders as $possibleHeader) {
                    if (str_contains($headerLower, $possibleHeader)) {
                        $this->setPatientField($patient, $field, $value);
                        break 2;
                    }
                }
            }
        }
    }

    private function setPatientField(Patient $patient, string $field, $value): void
    {
        if (empty($value)) {
            return;
        }

        switch ($field) {
            case 'last_name':
                $patient->setLastName($value);
                break;
            case 'first_name':
                $patient->setFirstName($value);
                break;
            case 'middle_name':
                $patient->setMiddleName($value);
                break;
            case 'date_of_birth':
                if ($value instanceof \DateTime) {
                    $patient->setDateOfBirth($value);
                } else {
                    $date = \DateTime::createFromFormat('d.m.Y', $value) ?: 
                           \DateTime::createFromFormat('Y-m-d', $value) ?:
                           new \DateTime($value);
                    $patient->setDateOfBirth($date);
                }
                break;
            case 'gender':
                $gender = mb_strtolower($value);
                if (in_array($gender, ['мужской', 'male', 'м'])) {
                    $patient->setGender('male');
                } elseif (in_array($gender, ['женский', 'female', 'ж'])) {
                    $patient->setGender('female');
                } else {
                    $patient->setGender('other');
                }
                break;
            case 'email':
                $patient->setEmail($value);
                break;
            case 'phone':
                // Очищаем номер телефона
                $phone = preg_replace('/[^0-9+]/', '', $value);
                $patient->setPhone($phone);
                break;
            case 'address':
                $patient->setAddress($value);
                break;
            case 'insurance_company':
                $patient->setInsuranceCompany($value);
                break;
            case 'insurance_policy':
                $patient->setInsurancePolicy($value);
                break;
            case 'blood_type':
                $patient->setBloodType($value);
                break;
            case 'allergies':
                if (is_string($value)) {
                    $allergies = array_map('trim', explode(',', $value));
                    $patient->setAllergies($allergies);
                } elseif (is_array($value)) {
                    $patient->setAllergies($value);
                }
                break;
            case 'chronic_diseases':
                if (is_string($value)) {
                    $diseases = array_map('trim', explode(',', $value));
                    $patient->setChronicDiseases($diseases);
                } elseif (is_array($value)) {
                    $patient->setChronicDiseases($value);
                }
                break;
        }
    }

    private function validatePatient(Patient $patient): void
    {
        $errors = $this->validator->validate($patient);
        
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            throw new \Exception(implode(', ', $errorMessages));
        }
    }

    public function getImportTemplate(): array
    {
        return [
            [
                'Фамилия' => 'Иванов',
                'Имя' => 'Иван',
                'Отчество' => 'Иванович',
                'Дата рождения' => '15.05.1980',
                'Пол' => 'Мужской',
                'Email' => 'ivanov@example.com',
                'Телефон' => '+79991234567',
                'Адрес' => 'г. Москва, ул. Примерная, д. 1',
                'Страховая компания' => 'Страховая компания',
                'Номер полиса' => '1234567890',
                'Группа крови' => 'A(II) Rh+',
                'Аллергии' => 'Пенициллин, аспирин',
                'Хронические заболевания' => 'Гипертония, диабет'
            ],
            [
                'Фамилия' => 'Петрова',
                'Имя' => 'Мария',
                'Отчество' => 'Сергеевна',
                'Дата рождения' => '20.10.1990',
                'Пол' => 'Женский',
                'Email' => 'petrova@example.com',
                'Телефон' => '+79997654321',
                'Адрес' => 'г. Москва, ул. Тестовая, д. 2',
                'Страховая компания' => 'Другая страховая',
                'Номер полиса' => '0987654321',
                'Группа крови' => 'B(III) Rh-',
                'Аллергии' => '',
                'Хронические заболевания' => ''
            ]
        ];
    }
}