<?php

namespace App\Event;

use App\Entity\Appointment;
use Symfony\Contracts\EventDispatcher\Event;

class AppointmentEvent extends Event
{
    public const CREATED = 'appointment.created';
    public const UPDATED = 'appointment.updated';
    public const CANCELLED = 'appointment.cancelled';
    public const COMPLETED = 'appointment.completed';

    private Appointment $appointment;
    private array $data;

    public function __construct(Appointment $appointment, array $data = [])
    {
        $this->appointment = $appointment;
        $this->data = $data;
    }

    public function getAppointment(): Appointment
    {
        return $this->appointment;
    }

    public function getData(): array
    {
        return $this->data;
    }
}