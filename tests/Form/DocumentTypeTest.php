<?php

namespace App\Tests\Form;

use App\Entity\Document;
use App\Form\DocumentType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

class DocumentTypeTest extends TestCase
{
    public function testDocumentTypeFieldUsesTextInput(): void
    {
        $validator = Validation::createValidator();
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory();

        $form = $factory->create(DocumentType::class, new Document(), [
            'campus_choices' => ['Main'],
            'document_type_choices' => ['Memo'],
            'status_choices' => ['Pending'],
        ]);

        $field = $form->get('documentType');

        self::assertSame('text', $field->getConfig()->getType()->getBlockPrefix());
        self::assertTrue($field->getConfig()->getOption('required'));
        self::assertSame('Document Type', $field->getConfig()->getOption('label'));
    }

    public function testBlankAmountIsAccepted(): void
    {
        $validator = Validation::createValidator();
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory();

        $form = $factory->create(DocumentType::class, new Document(), [
            'campus_choices' => ['Main'],
            'document_type_choices' => ['Memo'],
            'status_choices' => ['Pending'],
        ]);

        $form->submit([
            'dateApproved' => '2026-01-01',
            'campus' => 'Main',
            'documentType' => 'Memo',
            'status' => 'Pending',
            'particulars' => 'Test particulars',
            'amount' => '',
            'nature' => 'Test nature',
        ]);

        self::assertTrue($form->isValid());
        self::assertNull($form->getData()->getAmount());
    }
}
