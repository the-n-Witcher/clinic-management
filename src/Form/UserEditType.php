<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;

class UserEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Пожалуйста, введите email']),
                    new Email(['message' => 'Пожалуйста, введите корректный email адрес'])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'user@example.com'
                ]
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Имя',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Пожалуйста, введите имя'])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Иван'
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Фамилия',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Пожалуйста, введите фамилию'])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Иванов'
                ]
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Роли',
                'choices' => [
                    'Администратор' => 'ROLE_ADMIN',
                    'Врач' => 'ROLE_DOCTOR',
                    'Регистратор' => 'ROLE_RECEPTIONIST',
                    'Медсестра' => 'ROLE_NURSE',
                    'Пациент' => 'ROLE_PATIENT'
                ],
                'multiple' => true,
                'expanded' => false,
                'required' => true,
                'attr' => [
                    'class' => 'form-select select2',
                    'data-placeholder' => 'Выберите роли'
                ],
                'help' => 'Удерживайте Ctrl для выбора нескольких ролей'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Активен',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}