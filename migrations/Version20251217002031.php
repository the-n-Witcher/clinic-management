<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251217002031 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, last_login DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE patients (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, middle_name VARCHAR(100) DEFAULT NULL, date_of_birth DATE NOT NULL, gender VARCHAR(10) NOT NULL, medical_number VARCHAR(20) NOT NULL, email VARCHAR(100) NOT NULL, phone VARCHAR(20) NOT NULL, address TEXT DEFAULT NULL, insurance_company VARCHAR(100) DEFAULT NULL, insurance_policy VARCHAR(50) DEFAULT NULL, blood_type VARCHAR(20) DEFAULT NULL, allergies JSON DEFAULT NULL, chronic_diseases JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, consent_to_data_processing TINYINT(1) NOT NULL, emergency_contact_name VARCHAR(100) DEFAULT NULL, emergency_contact_phone VARCHAR(20) DEFAULT NULL, UNIQUE INDEX UNIQ_8CCC7B2C2A4D7BDF (medical_number), UNIQUE INDEX UNIQ_8CCC7B2CA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE doctors (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, room_id INT DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, middle_name VARCHAR(100) DEFAULT NULL, specialization VARCHAR(100) NOT NULL, license_number VARCHAR(50) DEFAULT NULL, qualification VARCHAR(50) DEFAULT NULL, consultation_duration INT NOT NULL, schedule JSON NOT NULL, consultation_fee NUMERIC(10, 2) DEFAULT NULL, bio TEXT DEFAULT NULL, languages JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_B67687E0A76ED395 (user_id), INDEX IDX_B67687E054177093 (room_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE appointments (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, doctor_id INT NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME NOT NULL, status VARCHAR(20) NOT NULL, reason TEXT DEFAULT NULL, notes TEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, reminder_sent TINYINT(1) DEFAULT NULL, cancellation_reason TEXT DEFAULT NULL, cancelled_by VARCHAR(100) DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, no_show_marked TINYINT(1) DEFAULT NULL, completed_at DATETIME DEFAULT NULL, created_by VARCHAR(100) DEFAULT NULL, updated_by VARCHAR(100) DEFAULT NULL, INDEX IDX_6A41727A6B899279 (patient_id), INDEX IDX_6A41727A87F4FB17 (doctor_id), INDEX idx_appointment_date (start_time), INDEX idx_appointment_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE medical_records (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, doctor_id INT NOT NULL, appointment_id INT DEFAULT NULL, type VARCHAR(50) NOT NULL, data JSON NOT NULL, notes TEXT DEFAULT NULL, record_date DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_confidential TINYINT(1) NOT NULL, attachments JSON DEFAULT NULL, INDEX IDX_BB116D5A6B899279 (patient_id), INDEX IDX_BB116D5A87F4FB17 (doctor_id), UNIQUE INDEX UNIQ_BB116D5AE5B533F9 (appointment_id), INDEX idx_record_date (record_date), INDEX idx_record_type (type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE prescriptions (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, doctor_id INT NOT NULL, medical_record_id INT DEFAULT NULL, medications JSON NOT NULL, prescribed_date DATE NOT NULL, valid_until DATE NOT NULL, instructions TEXT DEFAULT NULL, is_completed TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, pharmacy_notes VARCHAR(50) DEFAULT NULL, INDEX IDX_A292B9C36B899279 (patient_id), INDEX IDX_A292B9C387F4FB17 (doctor_id), INDEX IDX_A292B9C39B9DD7AB (medical_record_id), INDEX idx_prescription_date (prescribed_date), INDEX idx_prescription_valid (valid_until), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invoices (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, appointment_id INT DEFAULT NULL, invoice_number VARCHAR(50) NOT NULL, amount NUMERIC(10, 2) NOT NULL, paid_amount NUMERIC(10, 2) DEFAULT NULL, status VARCHAR(20) NOT NULL, issue_date DATE NOT NULL, due_date DATE NOT NULL, items JSON NOT NULL, description TEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, payments JSON DEFAULT NULL, tax_rate NUMERIC(5, 2) DEFAULT NULL, reminder_sent TINYINT(1) DEFAULT NULL, UNIQUE INDEX UNIQ_6A2F2F95D234F6A (invoice_number), INDEX IDX_6A2F2F96B899279 (patient_id), INDEX IDX_6A2F2F9E5B533F9 (appointment_id), INDEX idx_invoice_due_date (due_date), INDEX idx_invoice_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rooms (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, type VARCHAR(50) DEFAULT NULL, floor INT DEFAULT NULL, features JSON DEFAULT NULL, is_available TINYINT(1) NOT NULL, description TEXT DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_7CA11A9696901F54 (number), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE equipment (id INT AUTO_INCREMENT NOT NULL, room_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, serial_number VARCHAR(50) DEFAULT NULL, type VARCHAR(100) NOT NULL, model VARCHAR(100) DEFAULT NULL, manufacturer VARCHAR(100) DEFAULT NULL, purchase_date DATE DEFAULT NULL, warranty_until DATE DEFAULT NULL, purchase_price NUMERIC(10, 2) DEFAULT NULL, status VARCHAR(20) NOT NULL, last_maintenance DATE DEFAULT NULL, next_maintenance DATE DEFAULT NULL, specifications TEXT DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_D338D583E5D9A6DA (serial_number), INDEX IDX_D338D58354177093 (room_id), INDEX idx_equipment_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE audit_logs (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(100) NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id INT DEFAULT NULL, data JSON NOT NULL, username VARCHAR(100) NOT NULL, ip_address VARCHAR(45) NOT NULL, user_agent VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_entity (entity_type, entity_id), INDEX idx_action (action), INDEX idx_created_at (created_at), INDEX idx_username (username), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE doctor_equipment (doctor_id INT NOT NULL, equipment_id INT NOT NULL, INDEX IDX_7CF6A55D87F4FB17 (doctor_id), INDEX IDX_7CF6A55D517FE9FE (equipment_id), PRIMARY KEY(doctor_id, equipment_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE patients ADD CONSTRAINT FK_8CCC7B2CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE doctors ADD CONSTRAINT FK_B67687E0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE doctors ADD CONSTRAINT FK_B67687E054177093 FOREIGN KEY (room_id) REFERENCES rooms (id)');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727A6B899279 FOREIGN KEY (patient_id) REFERENCES patients (id)');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727A87F4FB17 FOREIGN KEY (doctor_id) REFERENCES doctors (id)');
        $this->addSql('ALTER TABLE medical_records ADD CONSTRAINT FK_BB116D5A6B899279 FOREIGN KEY (patient_id) REFERENCES patients (id)');
        $this->addSql('ALTER TABLE medical_records ADD CONSTRAINT FK_BB116D5A87F4FB17 FOREIGN KEY (doctor_id) REFERENCES doctors (id)');
        $this->addSql('ALTER TABLE medical_records ADD CONSTRAINT FK_BB116D5AE5B533F9 FOREIGN KEY (appointment_id) REFERENCES appointments (id)');
        $this->addSql('ALTER TABLE prescriptions ADD CONSTRAINT FK_A292B9C36B899279 FOREIGN KEY (patient_id) REFERENCES patients (id)');
        $this->addSql('ALTER TABLE prescriptions ADD CONSTRAINT FK_A292B9C387F4FB17 FOREIGN KEY (doctor_id) REFERENCES doctors (id)');
        $this->addSql('ALTER TABLE prescriptions ADD CONSTRAINT FK_A292B9C39B9DD7AB FOREIGN KEY (medical_record_id) REFERENCES medical_records (id)');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F96B899279 FOREIGN KEY (patient_id) REFERENCES patients (id)');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F9E5B533F9 FOREIGN KEY (appointment_id) REFERENCES appointments (id)');
        $this->addSql('ALTER TABLE equipment ADD CONSTRAINT FK_D338D58354177093 FOREIGN KEY (room_id) REFERENCES rooms (id)');
        $this->addSql('ALTER TABLE doctor_equipment ADD CONSTRAINT FK_7CF6A55D87F4FB17 FOREIGN KEY (doctor_id) REFERENCES doctors (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE doctor_equipment ADD CONSTRAINT FK_7CF6A55D517FE9FE FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patients DROP FOREIGN KEY FK_8CCC7B2CA76ED395');
        $this->addSql('ALTER TABLE doctors DROP FOREIGN KEY FK_B67687E0A76ED395');
        $this->addSql('ALTER TABLE doctors DROP FOREIGN KEY FK_B67687E054177093');
        $this->addSql('ALTER TABLE appointments DROP FOREIGN KEY FK_6A41727A6B899279');
        $this->addSql('ALTER TABLE appointments DROP FOREIGN KEY FK_6A41727A87F4FB17');
        $this->addSql('ALTER TABLE medical_records DROP FOREIGN KEY FK_BB116D5A6B899279');
        $this->addSql('ALTER TABLE medical_records DROP FOREIGN KEY FK_BB116D5A87F4FB17');
        $this->addSql('ALTER TABLE medical_records DROP FOREIGN KEY FK_BB116D5AE5B533F9');
        $this->addSql('ALTER TABLE prescriptions DROP FOREIGN KEY FK_A292B9C36B899279');
        $this->addSql('ALTER TABLE prescriptions DROP FOREIGN KEY FK_A292B9C387F4FB17');
        $this->addSql('ALTER TABLE prescriptions DROP FOREIGN KEY FK_A292B9C39B9DD7AB');
        $this->addSql('ALTER TABLE invoices DROP FOREIGN KEY FK_6A2F2F96B899279');
        $this->addSql('ALTER TABLE invoices DROP FOREIGN KEY FK_6A2F2F9E5B533F9');
        $this->addSql('ALTER TABLE equipment DROP FOREIGN KEY FK_D338D58354177093');
        $this->addSql('ALTER TABLE doctor_equipment DROP FOREIGN KEY FK_7CF6A55D87F4FB17');
        $this->addSql('ALTER TABLE doctor_equipment DROP FOREIGN KEY FK_7CF6A55D517FE9FE');
    
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE patients');
        $this->addSql('DROP TABLE doctors');
        $this->addSql('DROP TABLE appointments');
        $this->addSql('DROP TABLE medical_records');
        $this->addSql('DROP TABLE prescriptions');
        $this->addSql('DROP TABLE invoices');
        $this->addSql('DROP TABLE rooms');
        $this->addSql('DROP TABLE equipment');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE doctor_equipment');
    }
}