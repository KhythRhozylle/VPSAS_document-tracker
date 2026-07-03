<?php

namespace App\Tests\Repository;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DocumentRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DocumentRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(DocumentRepository::class);

        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        parent::tearDown();
    }

    public function testSearchMatchesNumericAndTextContent(): void
    {
        $document = new Document();
        $document->setDateApproved(new \DateTime('2026-01-15'));
        $document->setCampus('Main Campus');
        $document->setDocumentType('Receipt');
        $document->setParticulars('Payment voucher 12345 for office supplies');
        $document->setAmount('1250.50');
        $document->setNature('Operating Expense');
        $document->setStatus('Approved');

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $result = $this->repository->findFiltered('12345');

        $this->assertCount(1, $result['items']);
        $this->assertSame('Payment voucher 12345 for office supplies', $result['items'][0]->getParticulars());
    }
}
