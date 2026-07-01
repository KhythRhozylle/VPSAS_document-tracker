<?php

namespace App\Form;

use App\Entity\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Regex;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $campusChoices = array_combine($options['campus_choices'], $options['campus_choices']);
        $documentTypeChoices = array_combine($options['document_type_choices'], $options['document_type_choices']);
        $statusChoices = array_combine($options['status_choices'], $options['status_choices']);

        $builder
            ->add('dateApproved', DateType::class, [
                'label' => 'Date Approved',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(message: 'Date approved is required.')],
            ])
            ->add('campus', ChoiceType::class, [
                'label' => 'Campus',
                'choices' => $campusChoices,
                'placeholder' => 'Select Campus',
                'constraints' => [new NotBlank(message: 'Campus is required.')],
            ])
            ->add('documentType', ChoiceType::class, [
                'label' => 'Document Type',
                'choices' => $documentTypeChoices,
                'placeholder' => 'Select Document Type',
                'constraints' => [new NotBlank(message: 'Type of document is required.')],
            ])
            ->add('documentTypeOther', TextType::class, [
                'label' => 'Specify Document Type',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'document-type-other-field d-none'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => $statusChoices,
                'constraints' => [new NotBlank(message: 'Status is required.')],
            ])
            ->add('particulars', TextareaType::class, [
                'label' => 'Particulars',
                'attr' => ['rows' => 3],
                'constraints' => [new NotBlank(message: 'Particulars is required.')],
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Amount',
                'scale' => 2,
                'html5' => true,
                'constraints' => [
                    new NotBlank(message: 'Amount is required.'),
                    new PositiveOrZero(message: 'Amount must be a valid number.'),
                    new Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Amount must contain only numeric values.'),
                ],
            ])
            ->add('nature', TextType::class, [
                'label' => 'Nature of Document',
                'constraints' => [new NotBlank(message: 'Nature of document is required.')],
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($documentTypeChoices): void {
            $document = $event->getData();
            if (!$document instanceof Document) {
                return;
            }

            $currentType = $document->getDocumentType();
            if ($currentType && !in_array($currentType, $documentTypeChoices, true)) {
                $event->getForm()->get('documentType')->setData('Other');
                $event->getForm()->get('documentTypeOther')->setData($currentType);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $document = $event->getData();
            if (!$document instanceof Document) {
                return;
            }

            $form = $event->getForm();
            if ($form->get('documentType')->getData() === 'Other') {
                $other = trim((string) $form->get('documentTypeOther')->getData());
                if ($other !== '') {
                    $document->setDocumentType($other);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
            'campus_choices' => [],
            'document_type_choices' => [],
            'status_choices' => [],
        ]);

        $resolver->setAllowedTypes('campus_choices', 'array');
        $resolver->setAllowedTypes('document_type_choices', 'array');
        $resolver->setAllowedTypes('status_choices', 'array');
    }
}
