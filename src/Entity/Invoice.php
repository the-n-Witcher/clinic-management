<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: "App\Repository\InvoiceRepository")]
#[ORM\Table(name: "invoices")]
#[ORM\HasLifecycleCallbacks]
class Invoice
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_OVERDUE = 'overdue';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: "invoices")]
    #[ORM\JoinColumn(nullable: false)]
    private Patient $patient;

    #[ORM\Column(type: "string", length: 50, unique: true)]
    private string $invoiceNumber;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    #[Assert\Positive]
    private string $amount;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, nullable: true)]
    private ?string $paidAmount = null;

    #[ORM\Column(type: "string", length: 20)]
    #[Assert\Choice(choices: [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_PARTIALLY_PAID,
        self::STATUS_CANCELLED,
        self::STATUS_OVERDUE
    ])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: "date")]
    #[Assert\NotNull]
    private \DateTimeInterface $issueDate;

    #[ORM\Column(type: "date")]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(propertyPath: "issueDate")]
    private \DateTimeInterface $dueDate;

    #[ORM\Column(type: "json")]
    private array $items = [];

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $updatedAt;

    #[ORM\ManyToOne(targetEntity: Appointment::class)]
    private ?Appointment $appointment = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $payments = null;

    #[ORM\Column(type: "decimal", precision: 5, scale: 2, nullable: true)]
    private ?string $taxRate = null;

    #[ORM\Column(type: "boolean")]
    private bool $reminderSent = false;

    public function __construct()
    {
        $this->issueDate = new \DateTime();
        $this->dueDate = (new \DateTime())->modify('+30 days');
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->generateInvoiceNumber();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }

    private function generateInvoiceNumber(): void
    {
        if (empty($this->invoiceNumber)) {
            $this->invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        }
    }
    public function isReminderSent(): bool
    {
        return $this->reminderSent;
    }
    public function getId(): ?int { return $this->id; }
    public function getPatient(): Patient { return $this->patient; }
    public function setPatient(Patient $patient): self { $this->patient = $patient; return $this; }
    public function getInvoiceNumber(): string { return $this->invoiceNumber; }
    public function setInvoiceNumber(string $number): self { $this->invoiceNumber = $number; return $this; }
    public function getAmount(): string { return $this->amount; }
    public function setAmount(string $amount): self { $this->amount = $amount; return $this; }
    public function getPaidAmount(): ?string { return $this->paidAmount; }
    public function setPaidAmount(?string $amount): self { $this->paidAmount = $amount; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getIssueDate(): \DateTimeInterface { return $this->issueDate; }
    public function setIssueDate(\DateTimeInterface $date): self { $this->issueDate = $date; return $this; }
    public function getDueDate(): \DateTimeInterface { return $this->dueDate; }
    public function setDueDate(\DateTimeInterface $date): self { $this->dueDate = $date; return $this; }
    public function getItems(): array { return $this->items; }
    public function setItems(array $items): self { $this->items = $items; return $this; }
    public function addItem(string $description, string $amount, int $quantity = 1): self { 
        $this->items[] = [
            'description' => $description,
            'amount' => $amount,
            'quantity' => $quantity,
            'total' => bcmul($amount, $quantity, 2)
        ]; 
        return $this; 
    }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function getAppointment(): ?Appointment { return $this->appointment; }
    public function setAppointment(?Appointment $appointment): self { $this->appointment = $appointment; return $this; }
    public function getPayments(): ?array { return $this->payments; }
    public function setPayments(?array $payments): self { $this->payments = $payments; return $this; }
    public function addPayment(string $amount, string $method, string $transactionId = null): self { 
        $this->payments[] = [
            'date' => (new \DateTime())->format('Y-m-d H:i:s'),
            'amount' => $amount,
            'method' => $method,
            'transaction_id' => $transactionId
        ]; 
        
        $this->paidAmount = bcadd($this->paidAmount ?? '0', $amount, 2);
        
        if ($this->paidAmount >= $this->amount) {
            $this->status = self::STATUS_PAID;
        } elseif ($this->paidAmount > '0') {
            $this->status = self::STATUS_PARTIALLY_PAID;
        }
        
        return $this; 
    }
    public function getTaxRate(): ?string { return $this->taxRate; }
    public function setTaxRate(?string $rate): self { $this->taxRate = $rate; return $this; }

    public function getBalance(): string
    {
        return bcsub($this->amount, $this->paidAmount ?? '0', 2);
    }

    public function getTotalWithTax(): string
    {
        if ($this->taxRate) {
            $tax = bcdiv(bcmul($this->amount, $this->taxRate, 4), '100', 2);
            return bcadd($this->amount, $tax, 2);
        }
        return $this->amount;
    }

    public function isOverdue(): bool
    {
        $today = new \DateTime();
        return $this->status !== self::STATUS_PAID && 
               $this->status !== self::STATUS_CANCELLED && 
               $today > $this->dueDate;
    }

    public function getItemsTotal(): string
    {
        $total = '0';
        foreach ($this->items as $item) {
            $total = bcadd($total, $item['total'], 2);
        }
        return $total;
    }

    public function calculateTotal(): void
    {
        $this->amount = $this->getItemsTotal();
    }
    public function setReminderSent(bool $reminderSent): self
    {
        $this->reminderSent = $reminderSent;
        return $this;
    }
}