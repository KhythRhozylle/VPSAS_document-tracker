<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\ActivityLogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class SecurityActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->activityLogService->logLogin($user, $this->requestStack->getCurrentRequest());
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $this->activityLogService->logLoginFailed($event->getRequest());
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->activityLogService->logLogout($user, $event->getRequest());
    }
}
