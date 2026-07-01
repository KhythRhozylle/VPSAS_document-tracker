<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'constraints' => [new NotBlank(message: 'First name is required.')],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'constraints' => [new NotBlank(message: 'Last name is required.')],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [new NotBlank(message: 'Email is required.')],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone',
                'required' => false,
            ])
            ->add('department', TextType::class, [
                'label' => 'Department',
                'required' => false,
            ])
            ->add('jobTitle', TextType::class, [
                'label' => 'Job Title',
                'required' => false,
            ])
            ->add('profilePictureFile', FileType::class, [
                'label' => 'Profile Picture',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        maxSizeMessage: 'Profile picture must be 5 MB or smaller.',
                    ),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => ['label' => 'New Password', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Confirm New Password', 'attr' => ['autocomplete' => 'new-password']],
                'invalid_message' => 'Password fields must match.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
