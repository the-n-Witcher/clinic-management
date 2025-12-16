<?php
// src/Form/MedicalRecordType.php

namespace App\Form;

use App\Entity\MedicalRecord;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class MedicalRecordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Тип записи',
                'choices' => [
                    'Консультация' => MedicalRecord::TYPE_CONSULTATION,
                    'Диагноз' => MedicalRecord::TYPE_DIAGNOSIS,
                    'Результаты анализов' => MedicalRecord::TYPE_LAB_RESULT,
                    'Визуализация' => MedicalRecord::TYPE_IMAGING,
                    'Процедура' => MedicalRecord::TYPE_PROCEDURE,
                    'Вакцинация' => MedicalRecord::TYPE_VACCINATION,
                    'Другое' => MedicalRecord::TYPE_OTHER
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Заметки',
                'required' => false,
                'attr' => ['rows' => 5]
            ])
            ->add('isConfidential', CheckboxType::class, [
                'label' => 'Конфиденциально',
                'required' => false
            ])
            ->add('vitalSigns', CollectionType::class, [
                'label' => 'Жизненные показатели',
                'entry_type' => TextType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false
            ])
            ->add('symptoms', CollectionType::class, [
                'label' => 'Симптомы',
                'entry_type' => TextType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false
            ])
            ->add('diagnosis', CollectionType::class, [
                'label' => 'Диагнозы',
                'entry_type' => TextType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false
            ])
            ->add('treatment', CollectionType::class, [
                'label' => 'Лечение',
                'entry_type' => TextType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false
            ])
            ->add('recommendations', CollectionType::class, [
                'label' => 'Рекомендации',
                'entry_type' => TextType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MedicalRecord::class,
        ]);
    }
}