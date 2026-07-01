<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_STAFF = 'ROLE_STAFF';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'first_name', length: 100)]
    #[Assert\NotBlank(message: 'First name is required.')]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', length: 100)]
    #[Assert\NotBlank(message: 'Last name is required.')]
    private ?string $lastName = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(message: 'Please enter a valid email address.')]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(name: 'job_title', length: 150, nullable: true)]
    private ?string $jobTitle = null;

    #[ORM\Column(name: 'profile_picture', length: 255, nullable: true)]
    private ?string $profilePicture = null;

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

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique($roles));

        return $this;
    }

    public function getPrimaryRoleLabel(): string
    {
        if (in_array(self::ROLE_ADMIN, $this->getRoles(), true)) {
            return 'Admin';
        }

        if (in_array(self::ROLE_STAFF, $this->getRoles(), true)) {
            return 'Staff';
        }

        return 'User';
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim(($this->firstName ?? '').' '.($this->lastName ?? ''));
    }

    public function getInitials(): string
    {
        $first = $this->firstName ? mb_strtoupper(mb_substr($this->firstName, 0, 1)) : '';
        $last = $this->lastName ? mb_strtoupper(mb_substr($this->lastName, 0, 1)) : '';

        return $first.$last ?: '?';
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): static
    {
        $this->department = $department;

        return $this;
    }

    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    public function setJobTitle(?string $jobTitle): static
    {
        $this->jobTitle = $jobTitle;

        return $this;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): static
    {
        $this->profilePicture = $profilePicture;

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

    /**
     * @return array<string, mixed>
     */
    public function toProfileArray(): array
    {
        return [
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'department' => $this->department,
            'jobTitle' => $this->jobTitle,
            'profilePicture' => $this->profilePicture,
        ];
    }
}
