<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'constraints' => [new NotBlank()],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'constraints' => [new NotBlank()],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [new NotBlank()],
            ])
            ->add('department', TextType::class, [
                'label' => 'Department',
                'required' => false,
            ])
            ->add('jobTitle', TextType::class, [
                'label' => 'Job Title',
                'required' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => 'Confirm Password'],
                'invalid_message' => 'Password fields must match.',
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 8, minMessage: 'Password must be at least 8 characters.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
