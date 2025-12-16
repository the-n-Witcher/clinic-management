<?php
// src/Repository/InvoiceRepository.php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function save(Invoice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Invoice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findWithFilters(?string $status = null, ?int $patientId = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.patient', 'p')
            ->addSelect('p')
            ->orderBy('i.issueDate', 'DESC');

        if ($status) {
            $qb->andWhere('i.status = :status')
                ->setParameter('status', $status);
        }

        if ($patientId) {
            $qb->andWhere('p.id = :patientId')
                ->setParameter('patientId', $patientId);
        }

        return $qb->getQuery()->getResult();
    }

    public function search(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.patient', 'p')
            ->addSelect('p')
            ->where('i.invoiceNumber LIKE :query')
            ->orWhere('p.firstName LIKE :query')
            ->orWhere('p.lastName LIKE :query')
            ->orWhere('p.medicalNumber LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('i.issueDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.patient', 'p')
            ->addSelect('p')
            ->where('i.issueDate BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('i.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOverdueInvoices(): array
    {
        $today = new \DateTime();

        return $this->createQueryBuilder('i')
            ->leftJoin('i.patient', 'p')
            ->addSelect('p')
            ->where('i.dueDate < :today')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('today', $today)
            ->setParameter('statuses', [Invoice::STATUS_PENDING, Invoice::STATUS_PARTIALLY_PAID])
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalRevenue(\DateTimeInterface $startDate, \DateTimeInterface $endDate): float
    {
        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.amount) as total_revenue')
            ->where('i.issueDate BETWEEN :startDate AND :endDate')
            ->andWhere('i.status = :paid')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('paid', Invoice::STATUS_PAID)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function getRevenueByMonth(int $year): array
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('month', 'month');
        $rsm->addScalarResult('revenue', 'revenue');

        $sql = "
            SELECT 
                DATE_FORMAT(issue_date, '%Y-%m') as month,
                SUM(amount) as revenue
            FROM invoices
            WHERE YEAR(issue_date) = :year
                AND status = 'paid'
            GROUP BY DATE_FORMAT(issue_date, '%Y-%m')
            ORDER BY month
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('year', $year);
        
        return $query->getResult();
    }

    public function getOutstandingBalance(): float
    {
        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.amount - COALESCE(i.paidAmount, 0)) as balance')
            ->where('i.status IN (:statuses)')
            ->setParameter('statuses', [Invoice::STATUS_PENDING, Invoice::STATUS_PARTIALLY_PAID])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function getStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('i')
            ->select([
                'COUNT(i.id) as total_invoices',
                'SUM(CASE WHEN i.status = :pending THEN 1 ELSE 0 END) as pending',
                'SUM(CASE WHEN i.status = :paid THEN 1 ELSE 0 END) as paid',
                'SUM(CASE WHEN i.status = :partially_paid THEN 1 ELSE 0 END) as partially_paid',
                'SUM(CASE WHEN i.status = :cancelled THEN 1 ELSE 0 END) as cancelled',
                'SUM(CASE WHEN i.status = :overdue THEN 1 ELSE 0 END) as overdue',
                'SUM(i.amount) as total_amount',
                'SUM(CASE WHEN i.status = :paid THEN i.amount ELSE 0 END) as paid_amount',
                'AVG(i.amount) as average_invoice'
            ])
            ->where('i.issueDate BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('pending', Invoice::STATUS_PENDING)
            ->setParameter('paid', Invoice::STATUS_PAID)
            ->setParameter('partially_paid', Invoice::STATUS_PARTIALLY_PAID)
            ->setParameter('cancelled', Invoice::STATUS_CANCELLED)
            ->setParameter('overdue', Invoice::STATUS_OVERDUE);

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total_invoices' => (int) $result['total_invoices'],
            'pending' => (int) $result['pending'],
            'paid' => (int) $result['paid'],
            'partially_paid' => (int) $result['partially_paid'],
            'cancelled' => (int) $result['cancelled'],
            'overdue' => (int) $result['overdue'],
            'total_amount' => (float) $result['total_amount'],
            'paid_amount' => (float) $result['paid_amount'],
            'average_invoice' => (float) $result['average_invoice'],
            'collection_rate' => $result['total_amount'] > 0 ? 
                round(($result['paid_amount'] / $result['total_amount']) * 100, 2) : 0
        ];
    }

    public function findInvoicesForPatient(Patient $patient, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('i.issueDate', 'DESC');

        if ($status) {
            $qb->andWhere('i.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function findInvoicesByAppointment(int $appointmentId): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.appointment', 'a')
            ->addSelect('a')
            ->leftJoin('i.patient', 'p')
            ->addSelect('p')
            ->where('a.id = :appointmentId')
            ->setParameter('appointmentId', $appointmentId)
            ->orderBy('i.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getTopPatientsBySpending(int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->select('p.id', 'p.firstName', 'p.lastName', 'SUM(i.amount) as total_spent')
            ->leftJoin('i.patient', 'p')
            ->where('i.status = :paid')
            ->groupBy('p.id')
            ->orderBy('total_spent', 'DESC')
            ->setParameter('paid', Invoice::STATUS_PAID)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findInvoicesWithPayments(): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.patient', 'p')
            ->addSelect('p')
            ->where('i.payments IS NOT NULL')
            ->andWhere('JSON_LENGTH(i.payments) > 0')
            ->orderBy('i.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getDailyRevenue(\DateTimeInterface $date): array
    {
        $startOfDay = \DateTime::createFromInterface($date);
        $startOfDay->setTime(0, 0, 0);
        $endOfDay = \DateTime::createFromInterface($date);
        $endOfDay->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('i')
            ->select([
                'COUNT(i.id) as invoice_count',
                'SUM(i.amount) as total_revenue',
                'SUM(CASE WHEN i.status = :paid THEN i.amount ELSE 0 END) as paid_revenue'
            ])
            ->where('i.issueDate BETWEEN :start AND :end')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->setParameter('paid', Invoice::STATUS_PAID)
            ->getQuery()
            ->getSingleResult();

        return [
            'date' => $date->format('Y-m-d'),
            'invoice_count' => (int) $result['invoice_count'],
            'total_revenue' => (float) $result['total_revenue'],
            'paid_revenue' => (float) $result['paid_revenue'],
            'collection_rate' => $result['total_revenue'] > 0 ? 
                round(($result['paid_revenue'] / $result['total_revenue']) * 100, 2) : 0
        ];
    }

    public function findInvoicesNeedingReminder(): array
    {
        $today = new \DateTime();
        $reminderDate = clone $today;
        $reminderDate->modify('+3 days'); // Напоминать за 3 дня до срока

        return $this->createQueryBuilder('i')
            ->leftJoin('i.patient', 'p')
            ->addSelect('p')
            ->where('i.dueDate = :reminderDate')
            ->andWhere('i.status = :pending')
            ->andWhere('i.reminderSent = false')
            ->setParameter('reminderDate', $reminderDate->format('Y-m-d'))
            ->setParameter('pending', Invoice::STATUS_PENDING)
            ->getQuery()
            ->getResult();
    }

    public function markRemindersAsSent(array $invoiceIds): void
    {
        if (empty($invoiceIds)) {
            return;
        }

        $this->createQueryBuilder('i')
            ->update()
            ->set('i.reminderSent', true)
            ->where('i.id IN (:ids)')
            ->setParameter('ids', $invoiceIds)
            ->getQuery()
            ->execute();
    }

    public function getInvoiceCountByStatus(): array
    {
        $result = $this->createQueryBuilder('i')
            ->select('i.status, COUNT(i.id) as count')
            ->groupBy('i.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    public function findRecentInvoices(int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.patient', 'p')
            ->addSelect('p')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getInvoiceSummaryForPatient(Patient $patient): array
    {
        $qb = $this->createQueryBuilder('i')
            ->select([
                'COUNT(i.id) as total_invoices',
                'SUM(i.amount) as total_amount',
                'SUM(CASE WHEN i.status = :paid THEN i.amount ELSE 0 END) as paid_amount',
                'SUM(CASE WHEN i.status = :pending THEN i.amount ELSE 0 END) as pending_amount',
                'SUM(CASE WHEN i.status = :overdue THEN i.amount ELSE 0 END) as overdue_amount'
            ])
            ->where('i.patient = :patient')
            ->setParameter('patient', $patient)
            ->setParameter('paid', Invoice::STATUS_PAID)
            ->setParameter('pending', Invoice::STATUS_PENDING)
            ->setParameter('overdue', Invoice::STATUS_OVERDUE);

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total_invoices' => (int) $result['total_invoices'],
            'total_amount' => (float) $result['total_amount'],
            'paid_amount' => (float) $result['paid_amount'],
            'pending_amount' => (float) $result['pending_amount'],
            'overdue_amount' => (float) $result['overdue_amount'],
            'balance' => (float) $result['total_amount'] - (float) $result['paid_amount'],
            'payment_rate' => $result['total_amount'] > 0 ? 
                round(($result['paid_amount'] / $result['total_amount']) * 100, 2) : 0
        ];
    }

    public function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        
        // Ищем последний номер счета за текущий месяц
        $lastInvoice = $this->createQueryBuilder('i')
            ->where('i.invoiceNumber LIKE :pattern')
            ->setParameter('pattern', 'INV-' . $year . $month . '-%')
            ->orderBy('i.invoiceNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->getInvoiceNumber(), -4);
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }

        return 'INV-' . $year . $month . '-' . $nextNumber;
    }

    public function findInvoicesWithPartialPayments(): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.patient', 'p')
            ->addSelect('p')
            ->where('i.status = :partially_paid')
            ->orWhere('i.paidAmount > 0 AND i.paidAmount < i.amount')
            ->setParameter('partially_paid', Invoice::STATUS_PARTIALLY_PAID)
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getMonthlyRevenueTrend(int $months = 12): array
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('month', 'month');
        $rsm->addScalarResult('revenue', 'revenue');
        $rsm->addScalarResult('invoice_count', 'invoice_count');

        $sql = "
            SELECT 
                DATE_FORMAT(issue_date, '%Y-%m') as month,
                SUM(amount) as revenue,
                COUNT(*) as invoice_count
            FROM invoices
            WHERE issue_date >= DATE_SUB(NOW(), INTERVAL :months MONTH)
                AND status = 'paid'
            GROUP BY DATE_FORMAT(issue_date, '%Y-%m')
            ORDER BY month DESC
            LIMIT :months
        ";

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('months', $months);
        
        return $query->getResult();
    }
}