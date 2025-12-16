<?php

namespace App\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class ScheduleDataTransformer implements DataTransformerInterface
{
    private array $days = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday'
    ];

    public function transform($value): array
    {
        if (empty($value)) {
            return array_fill_keys($this->days, ['start' => '09:00', 'end' => '17:00']);
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        foreach ($this->days as $day) {
            if (!isset($value[$day])) {
                $value[$day] = [];
            }
        }

        return $value;
    }

    public function reverseTransform($value): string
    {
        if (is_string($value)) {
            return $value;
        }

        foreach ($value as $day => $slots) {
            $value[$day] = array_filter($slots, function($slot) {
                return !empty($slot['start']) && !empty($slot['end']);
            });
            $value[$day] = array_values($value[$day]);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}