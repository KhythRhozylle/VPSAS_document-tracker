<?php

namespace App\Twig;

use App\Entity\User;
use App\Service\DateTimeFormatterService;
use App\Service\NotificationService;
use App\Service\ProfileService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ProfileService $profileService,
        private readonly NotificationService $notificationService,
        private readonly DateTimeFormatterService $dateTimeFormatter,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('app_datetime', [$this->dateTimeFormatter, 'format']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [
                'navbarUser' => null,
                'navbarProfilePictureUrl' => null,
                'navbarUnreadCount' => 0,
                'currentUser' => null,
                'isAdmin' => false,
                'appTimezone' => $this->dateTimeFormatter->getTimezone()->getName(),
            ];
        }

        return [
            'navbarUser' => $user,
            'navbarProfilePictureUrl' => $this->profileService->getProfilePictureUrl($user),
            'navbarUnreadCount' => $this->notificationService->countUnread(),
            'currentUser' => $user,
            'isAdmin' => $this->security->isGranted(User::ROLE_ADMIN),
            'appTimezone' => $this->dateTimeFormatter->getTimezone()->getName(),
        ];
    }
}
