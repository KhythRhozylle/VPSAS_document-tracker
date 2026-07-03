<?php

namespace App\Service;

use App\Entity\SystemSetting;
use App\Entity\User;
use App\Repository\SystemSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class DocumentOptionsService
{
    public const KEY_CAMPUSES = 'campuses';
    public const KEY_DOCUMENT_TYPES = 'document_types';

    /** @var list<string> */
    private const DEFAULT_CAMPUSES = [
        'Main Campus I',
        'Main Campus II',
        'Bais Campus I & II',
        'Guihulngan Campus',
        'Bayawan - Sta. Catalina Campus',
        'Siaton Campus',
        'Pamplona Campus (Extension)',
    ];

    /** @var list<string> */
    private const DEFAULT_DOCUMENT_TYPES = [
        'Academic Matter',
        'Financial Matter',
        'Letter',
    ];

    /** @var list<string> */
    public const DOCUMENT_STATUSES = [
        'Pending',
        'Approved',
        'Cancelled',
        'Recommending Approval',
    ];

    public function __construct(
        private readonly SystemSettingRepository $settingRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getCampuses(): array
    {
        return $this->getSettingValues(self::KEY_CAMPUSES, self::DEFAULT_CAMPUSES);
    }

    /**
     * @return list<string>
     */
    public function getDocumentTypes(): array
    {
        return $this->getSettingValues(self::KEY_DOCUMENT_TYPES, self::DEFAULT_DOCUMENT_TYPES);
    }

    /**
     * @return list<string>
     */
    public function getDocumentStatuses(): array
    {
        return self::DOCUMENT_STATUSES;
    }

    /**
     * @return list<string>
     */
    public function getUserRoleFilterOptions(): array
    {
        return ['Admin', 'Staff'];
    }

    /**
     * @param list<string> $values
     */
    public function updateCampuses(array $values, User $admin): void
    {
        $this->updateSetting(self::KEY_CAMPUSES, $values);
    }

    /**
     * @param list<string> $values
     */
    public function updateDocumentTypes(array $values, User $admin): void
    {
        $this->updateSetting(self::KEY_DOCUMENT_TYPES, $values);
    }

    /**
     * @param list<string> $defaults
     *
     * @return list<string>
     */
    private function getSettingValues(string $key, array $defaults): array
    {
        $setting = $this->settingRepository->findValueByKey($key);
        if (!$setting instanceof SystemSetting) {
            return $defaults;
        }

        $values = array_values(array_filter($setting->getSettingValue(), static fn ($value) => is_string($value) && $value !== ''));

        return $values !== [] ? $values : $defaults;
    }

    /**
     * @param list<string> $values
     */
    private function updateSetting(string $key, array $values): void
    {
        $setting = $this->settingRepository->findValueByKey($key);
        if (!$setting instanceof SystemSetting) {
            $setting = (new SystemSetting())->setSettingKey($key);
            $this->entityManager->persist($setting);
        }

        $setting->setSettingValue(array_values(array_unique(array_filter(array_map('trim', $values)))));
        $this->entityManager->flush();
    }
}
