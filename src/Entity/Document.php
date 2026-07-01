<?php

namespace App\Entity;

use App\Entity\User;
use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'documents')]
#[ORM\HasLifecycleCallbacks]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'date_approved', type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'Date approved is required.')]
    private ?\DateTimeInterface $dateApproved = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Campus is required.')]
    private ?string $campus = null;

    #[ORM\Column(name: 'document_type', length: 255)]
    #[Assert\NotBlank(message: 'Type of document is required.')]
    private ?string $documentType = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Particulars is required.')]
    private ?string $particulars = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotBlank(message: 'Amount is required.')]
    #[Assert\PositiveOrZero(message: 'Amount must be a valid number.')]
    private ?string $amount = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Nature of document is required.')]
    private ?string $nature = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Status is required.')]
    private string $status = 'Approved';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateApproved(): ?\DateTimeInterface
    {
        return $this->dateApproved;
    }

    public function setDateApproved(?\DateTimeInterface $dateApproved): static
    {
        $this->dateApproved = $dateApproved;

        return $this;
    }

    public function getCampus(): ?string
    {
        return $this->campus;
    }

    public function setCampus(?string $campus): static
    {
        $this->campus = $campus;

        return $this;
    }

    public function getDocumentType(): ?string
    {
        return $this->documentType;
    }

    public function setDocumentType(?string $documentType): static
    {
        $this->documentType = $documentType;

        return $this;
    }

    public function getParticulars(): ?string
    {
        return $this->particulars;
    }

    public function setParticulars(?string $particulars): static
    {
        $this->particulars = $particulars;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getNature(): ?string
    {
        return $this->nature;
    }

    public function setNature(?string $nature): static
    {
        $this->nature = $nature;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
