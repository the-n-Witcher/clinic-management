<?php
// src/Form/AppointmentType.php

namespace App\Form;

use App\Entity\Appointment;
use App\Entity\Patient;
use App\Entity\Doctor;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AppointmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('patient', EntityType::class, [
                'class' => Patient::class,
                'choice_label' => 'fullName',
                'placeholder' => 'Select patient',
                'required' => true,
            ])
            ->add('doctor', EntityType::class, [
                'class' => Doctor::class,
                'choice_label' => 'fullName',
                'placeholder' => 'Select doctor',
                'required' => true,
            ])
            ->add('startTime', DateTimeType::class, [
                'widget' => 'single_text',
                'html5' => false,
                'attr' => ['class' => 'datetime-picker'],
                'required' => true,
            ])
            ->add('reason', TextareaType::class, [
                'required' => true,
                'attr' => ['rows' => 3],
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Scheduled' => Appointment::STATUS_SCHEDULED,
                    'Confirmed' => Appointment::STATUS_CONFIRMED,
                    'Completed' => Appointment::STATUS_COMPLETED,
                    'Cancelled' => Appointment::STATUS_CANCELLED,
                    'No Show' => Appointment::STATUS_NO_SHOW,
                ],
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Appointment::class,
        ]);
    }
}