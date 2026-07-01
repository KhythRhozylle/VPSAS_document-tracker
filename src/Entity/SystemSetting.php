<?php

namespace App\Entity;

use App\Repository\SystemSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemSettingRepository::class)]
#[ORM\Table(name: 'system_settings')]
class SystemSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'setting_key', length: 100, unique: true)]
    private string $settingKey;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $settingValue = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettingKey(): string
    {
        return $this->settingKey;
    }

    public function setSettingKey(string $settingKey): static
    {
        $this->settingKey = $settingKey;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getSettingValue(): array
    {
        return $this->settingValue;
    }

    /**
     * @param list<string> $settingValue
     */
    public function setSettingValue(array $settingValue): static
    {
        $this->settingValue = array_values($settingValue);

        return $this;
    }
}
