<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLogService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed>|null $oldData
     * @param array<string, mixed>|null $newData
     */
    public function log(
        string $action,
        string $module,
        ?string $description = null,
        ?User $user = null,
        ?int $recordId = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?Request $request = null,
    ): ActivityLog {
        $request ??= $this->requestStack->getCurrentRequest();

        $log = (new ActivityLog())
            ->setAction($action)
            ->setModule($module)
            ->setDescription($description)
            ->setRecordId($recordId)
            ->setOldData($oldData)
            ->setNewData($newData);

        if ($user instanceof User) {
            $log
                ->setUserId($user->getId())
                ->setFullName($user->getFullName())
                ->setEmail((string) $user->getEmail())
                ->setRole($user->getPrimaryRoleLabel());
        } elseif ($request !== null) {
            $attemptEmail = (string) $request->request->get('_username', $request->request->get('email', 'unknown@local'));
            $log
                ->setFullName('Unknown User')
                ->setEmail($attemptEmail)
                ->setRole('Unknown');
        }

        if ($request instanceof Request) {
            $log
                ->setIpAddress($request->getClientIp())
                ->setUserAgent(substr((string) $request->headers->get('User-Agent', ''), 0, 512));
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    public function logLogin(User $user, ?Request $request = null): void
    {
        $this->log(
            ActivityLog::ACTION_LOGIN,
            'Authentication',
            'User logged into the system',
            $user,
            $user->getId(),
            request: $request,
        );
    }

    public function logLogout(User $user, ?Request $request = null): void
    {
        $this->log(
            ActivityLog::ACTION_LOGOUT,
            'Authentication',
            'User logged out of the system',
            $user,
            $user->getId(),
            request: $request,
        );
    }

    public function logLoginFailed(?Request $request = null): void
    {
        $email = 'unknown@local';
        if ($request instanceof Request) {
            $email = (string) $request->request->get('_username', 'unknown@local');
        }

        $this->log(
            ActivityLog::ACTION_LOGIN_FAILED,
            'Authentication',
            sprintf('Failed login attempt for %s', $email),
            request: $request,
        );
    }

    public function logPasswordChange(User $user, ?Request $request = null): void
    {
        $this->log(
            ActivityLog::ACTION_PASSWORD_CHANGE,
            'Authentication',
            'User changed their password',
            $user,
            $user->getId(),
            request: $request,
        );
    }

    public function logAccountCreation(User $user, ?User $createdBy = null, ?Request $request = null): void
    {
        $this->log(
            ActivityLog::ACTION_ACCOUNT_CREATION,
            'Users',
            sprintf('Created account for %s (%s)', $user->getFullName(), $user->getEmail()),
            $createdBy ?? $user,
            $user->getId(),
            newData: $user->toProfileArray(),
            request: $request,
        );
    }

    /**
     * @param array<string, mixed>|null $oldData
     * @param array<string, mixed>|null $newData
     */
    public function logCreate(
        User $user,
        string $module,
        int $recordId,
        ?array $newData = null,
        ?string $description = null,
        ?Request $request = null,
    ): void {
        $this->log(
            ActivityLog::ACTION_CREATE,
            $module,
            $description ?? sprintf('Created record #%d in %s', $recordId, $module),
            $user,
            $recordId,
            newData: $newData,
            request: $request,
        );
    }

    /**
     * @param array<string, mixed>|null $oldData
     * @param array<string, mixed>|null $newData
     */
    public function logUpdate(
        User $user,
        string $module,
        int $recordId,
        ?array $oldData = null,
        ?array $newData = null,
        ?string $description = null,
        ?Request $request = null,
    ): void {
        $this->log(
            ActivityLog::ACTION_UPDATE,
            $module,
            $description ?? sprintf('Updated record #%d in %s', $recordId, $module),
            $user,
            $recordId,
            oldData: $oldData,
            newData: $newData,
            request: $request,
        );
    }

    /**
     * @param array<string, mixed>|null $oldData
     */
    public function logDelete(
        User $user,
        string $module,
        int $recordId,
        ?array $oldData = null,
        ?string $description = null,
        ?Request $request = null,
    ): void {
        $this->log(
            ActivityLog::ACTION_DELETE,
            $module,
            $description ?? sprintf('Deleted record #%d from %s', $recordId, $module),
            $user,
            $recordId,
            oldData: $oldData,
            request: $request,
        );
    }

    public function logPrint(User $user, string $module, ?string $description = null, ?Request $request = null): void
    {
        $this->log(
            ActivityLog::ACTION_PRINT,
            $module,
            $description ?? sprintf('Printed %s report', $module),
            $user,
            request: $request,
        );
    }

    public function logExportPdf(User $user, string $module, ?string $description = null, ?Request $request = null): void
    {
        $this->log(
            ActivityLog::ACTION_EXPORT_PDF,
            $module,
            $description ?? sprintf('Exported %s to PDF', $module),
            $user,
            request: $request,
        );
    }

    public function logExportExcel(User $user, string $module, ?string $description = null, ?Request $request = null): void
    {
        $this->log(
            ActivityLog::ACTION_EXPORT_EXCEL,
            $module,
            $description ?? sprintf('Exported %s to Excel', $module),
            $user,
            request: $request,
        );
    }

    public function logExportCsv(User $user, string $module, ?string $description = null, ?Request $request = null): void
    {
        $this->log(
            ActivityLog::ACTION_EXPORT_CSV,
            $module,
            $description ?? sprintf('Exported %s to CSV', $module),
            $user,
            request: $request,
        );
    }
}
