<?php

namespace App\DataTransformer;

use App\Entity\MedicalRecord;
use Symfony\Component\Form\DataTransformerInterface;

class MedicalRecordDataTransformer implements DataTransformerInterface
{
    public function transform($value): string
    {
        if ($value === null) {
            return '{}';
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function reverseTransform($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON data');
        }

        return $decoded;
    }
}