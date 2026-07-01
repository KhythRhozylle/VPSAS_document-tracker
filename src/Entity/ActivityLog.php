<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_logs')]
#[ORM\Index(name: 'idx_activity_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_activity_action', columns: ['action'])]
#[ORM\Index(name: 'idx_activity_role', columns: ['role'])]
class ActivityLog
{
    public const ACTION_LOGIN = 'Login';
    public const ACTION_LOGOUT = 'Logout';
    public const ACTION_LOGIN_FAILED = 'Login Failed';
    public const ACTION_PASSWORD_CHANGE = 'Password Change';
    public const ACTION_ACCOUNT_CREATION = 'Account Creation';
    public const ACTION_CREATE = 'Create';
    public const ACTION_UPDATE = 'Update';
    public const ACTION_DELETE = 'Delete';
    public const ACTION_RESTORE = 'Restore';
    public const ACTION_PRINT = 'Print';
    public const ACTION_EXPORT_PDF = 'Export PDF';
    public const ACTION_EXPORT_EXCEL = 'Export Excel';
    public const ACTION_EXPORT_CSV = 'Export CSV';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id', nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(name: 'full_name', length: 200)]
    private string $fullName = 'System';

    #[ORM\Column(length: 180)]
    private string $email = 'system@local';

    #[ORM\Column(length: 20)]
    private string $role = 'System';

    #[ORM\Column(length: 50)]
    private string $action;

    #[ORM\Column(length: 100)]
    private string $module;

    #[ORM\Column(name: 'record_id', nullable: true)]
    private ?int $recordId = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'old_data', type: Types::JSON, nullable: true)]
    private ?array $oldData = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'new_data', type: Types::JSON, nullable: true)]
    private ?array $newData = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'ip_address', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'user_agent', length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function setModule(string $module): static
    {
        $this->module = $module;

        return $this;
    }

    public function getRecordId(): ?int
    {
        return $this->recordId;
    }

    public function setRecordId(?int $recordId): static
    {
        $this->recordId = $recordId;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getOldData(): ?array
    {
        return $this->oldData;
    }

    /** @param array<string, mixed>|null $oldData */
    public function setOldData(?array $oldData): static
    {
        $this->oldData = $oldData;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getNewData(): ?array
    {
        return $this->newData;
    }

    /** @param array<string, mixed>|null $newData */
    public function setNewData(?array $newData): static
    {
        $this->newData = $newData;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
