<?php
// src/Form/ImportPatientsType.php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ImportPatientsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Excel файл',
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv'
                        ],
                        'mimeTypesMessage' => 'Пожалуйста, загрузите файл Excel (.xlsx, .xls) или CSV',
                    ])
                ],
                'attr' => [
                    'accept' => '.xlsx,.xls,.csv',
                    'class' => 'form-control'
                ]
            ])
            ->add('updateExisting', CheckboxType::class, [
                'label' => 'Обновлять существующих пациентов',
                'required' => false,
                'help' => 'Если пациент с таким email уже существует, обновить его данные'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}