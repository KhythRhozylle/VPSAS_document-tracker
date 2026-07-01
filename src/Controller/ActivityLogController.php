<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use App\Service\ActivityLogService;
use App\Service\DateTimeFormatterService;
use App\Service\PaginationHelper;
use App\Service\SpreadsheetExportService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activity-logs')]
#[IsGranted(User::ROLE_ADMIN)]
final class ActivityLogController extends AbstractController
{
    private const SESSION_PER_PAGE = 'activity_logs_per_page';

    /** @var list<string> */
    private const EXPORT_HEADERS = [
        'Date & Time',
        'User',
        'Email',
        'Role',
        'Action',
        'Module',
        'Description',
        'IP Address',
    ];

    public function __construct(
        private readonly PaginationHelper $paginationHelper,
        private readonly SpreadsheetExportService $exportService,
        private readonly DateTimeFormatterService $dateTimeFormatter,
        private readonly ActivityLogService $activityLogService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'app_activity_logs', methods: ['GET'])]
    public function index(Request $request, ActivityLogRepository $repository): Response
    {
        $params = $this->resolveQueryParams($request);
        $result = $repository->findFiltered(
            $params['search'],
            $params['role'],
            $params['action'],
            $params['dateFrom'],
            $params['dateTo'],
            $params['sort'],
            $params['direction'],
            $params['page'],
            $params['perPage'],
        );

        return $this->render('activity_logs/index.html.twig', [
            'logs' => $result['items'],
            'total' => $result['total'],
            'page' => $params['page'],
            'perPage' => $params['perPage'],
            'perPageSelected' => $params['perPageSelected'],
            'search' => $params['search'],
            'role' => $params['roleStr'],
            'action' => $params['actionStr'],
            'dateFrom' => $params['dateFromStr'],
            'dateTo' => $params['dateToStr'],
            'sort' => $params['sort'],
            'direction' => $params['direction'],
            'actions' => $this->getActionOptions(),
            'roles' => ['Admin', 'Staff', 'Unknown', 'System'],
            'perPageOptions' => PaginationHelper::PER_PAGE_OPTIONS,
        ]);
    }

    #[Route('/data', name: 'app_activity_logs_data', methods: ['GET'])]
    public function data(Request $request, ActivityLogRepository $repository): Response
    {
        $params = $this->resolveQueryParams($request);
        $result = $repository->findFiltered(
            $params['search'],
            $params['role'],
            $params['action'],
            $params['dateFrom'],
            $params['dateTo'],
            $params['sort'],
            $params['direction'],
            $params['page'],
            $params['perPage'],
        );

        return $this->render('activity_logs/_table.html.twig', [
            'logs' => $result['items'],
            'total' => $result['total'],
            'page' => $params['page'],
            'perPage' => $params['perPage'],
            'perPageSelected' => $params['perPageSelected'],
            'sort' => $params['sort'],
            'direction' => $params['direction'],
        ]);
    }

    #[Route('/{id}', name: 'app_activity_logs_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(ActivityLog $log): JsonResponse
    {
        return $this->json([
            'id' => $log->getId(),
            'fullName' => $log->getFullName(),
            'email' => $log->getEmail(),
            'role' => $log->getRole(),
            'action' => $log->getAction(),
            'module' => $log->getModule(),
            'recordId' => $log->getRecordId(),
            'description' => $log->getDescription(),
            'oldData' => $log->getOldData(),
            'newData' => $log->getNewData(),
            'ipAddress' => $log->getIpAddress(),
            'userAgent' => $log->getUserAgent(),
            'createdAt' => $this->dateTimeFormatter->format($log->getCreatedAt()),
        ]);
    }

    #[Route('/export/csv', name: 'app_activity_logs_export_csv', methods: ['GET'])]
    public function exportCsv(Request $request, ActivityLogRepository $repository): Response
    {
        return $this->exportSpreadsheet($request, $repository, 'csv');
    }

    #[Route('/export/excel', name: 'app_activity_logs_export_excel', methods: ['GET'])]
    public function exportExcel(Request $request, ActivityLogRepository $repository): Response
    {
        return $this->exportSpreadsheet($request, $repository, 'excel');
    }

    #[Route('/export/pdf', name: 'app_activity_logs_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, ActivityLogRepository $repository): Response
    {
        return $this->renderExportView($request, $repository, 'activity_logs/pdf.html.twig', 'pdf');
    }

    #[Route('/print', name: 'app_activity_logs_print', methods: ['GET'])]
    public function print(Request $request, ActivityLogRepository $repository): Response
    {
        return $this->renderExportView($request, $repository, 'activity_logs/print.html.twig', 'print');
    }

    private function exportSpreadsheet(Request $request, ActivityLogRepository $repository, string $type): Response
    {
        try {
            $this->logExportAction($request, $type);
            $logs = $this->fetchAllLogs($request, $repository);
            $rows = array_map(fn (ActivityLog $log) => $this->serializeExportRow($log), $logs);
            $filename = 'activity-logs-'.date('Y-m-d');

            if ($type === 'csv') {
                return $this->exportService->createCsvResponse(self::EXPORT_HEADERS, $rows, $filename.'.csv');
            }

            return $this->exportService->createExcelResponse(self::EXPORT_HEADERS, $rows, $filename.'.xlsx');
        } catch (\Throwable $exception) {
            $this->logger->error('Activity log export failed.', [
                'type' => $type,
                'message' => $exception->getMessage(),
            ]);

            return new Response('Unable to export activity logs. Please try again.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function renderExportView(
        Request $request,
        ActivityLogRepository $repository,
        string $template,
        string $type,
    ): Response {
        try {
            $this->logExportAction($request, $type);
            $params = $this->resolveQueryParams($request, false);
            $logs = $this->fetchAllLogs($request, $repository);

            return $this->render($template, [
                'logs' => $logs,
                'search' => $params['search'],
                'role' => $params['roleStr'],
                'action' => $params['actionStr'],
                'dateFrom' => $params['dateFromStr'],
                'dateTo' => $params['dateToStr'],
                'direction' => $params['direction'],
                'filters' => $this->buildFilterSummary($params),
                'generatedAt' => $this->dateTimeFormatter->format($this->dateTimeFormatter->now()),
                'total' => count($logs),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Activity log export view failed.', [
                'type' => $type,
                'message' => $exception->getMessage(),
            ]);

            return new Response('Unable to prepare activity log export. Please try again.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @return list<ActivityLog>
     */
    private function fetchAllLogs(Request $request, ActivityLogRepository $repository): array
    {
        $params = $this->resolveQueryParams($request, false);

        return $repository->findAllFiltered(
            $params['search'],
            $params['role'],
            $params['action'],
            $params['dateFrom'],
            $params['dateTo'],
            $params['sort'],
            $params['direction'],
        );
    }

    private function logExportAction(Request $request, string $type): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return;
        }

        $description = match ($type) {
            'print' => sprintf('%s %s printed Activity Logs report.', $user->getPrimaryRoleLabel(), $user->getFullName()),
            'pdf' => sprintf('%s %s exported Activity Logs to PDF.', $user->getPrimaryRoleLabel(), $user->getFullName()),
            'excel' => sprintf('%s %s exported Activity Logs to Excel.', $user->getPrimaryRoleLabel(), $user->getFullName()),
            'csv' => sprintf('%s %s exported Activity Logs to CSV.', $user->getPrimaryRoleLabel(), $user->getFullName()),
            default => null,
        };

        match ($type) {
            'print' => $this->activityLogService->logPrint($user, 'Activity Logs', $description, $request),
            'pdf' => $this->activityLogService->logExportPdf($user, 'Activity Logs', $description, $request),
            'excel' => $this->activityLogService->logExportExcel($user, 'Activity Logs', $description, $request),
            'csv' => $this->activityLogService->logExportCsv($user, 'Activity Logs', $description, $request),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildFilterSummary(array $params): string
    {
        $parts = [];

        if (($params['search'] ?? '') !== '') {
            $parts[] = 'Search: "'.$params['search'].'"';
        }
        if (($params['roleStr'] ?? '') !== '') {
            $parts[] = 'Role: '.$params['roleStr'];
        }
        if (($params['actionStr'] ?? '') !== '') {
            $parts[] = 'Action: '.$params['actionStr'];
        }
        if (($params['dateFromStr'] ?? '') !== '') {
            $parts[] = 'From: '.$params['dateFromStr'];
        }
        if (($params['dateToStr'] ?? '') !== '') {
            $parts[] = 'To: '.$params['dateToStr'];
        }
        if (($params['direction'] ?? 'desc') === 'asc') {
            $parts[] = 'Sort: Oldest first';
        } else {
            $parts[] = 'Sort: Newest first';
        }

        return $parts === [] ? 'All activity logs' : implode(' | ', $parts);
    }

    /**
     * @return list<string>
     */
    private function getActionOptions(): array
    {
        return [
            ActivityLog::ACTION_LOGIN,
            ActivityLog::ACTION_LOGOUT,
            ActivityLog::ACTION_LOGIN_FAILED,
            ActivityLog::ACTION_PASSWORD_CHANGE,
            ActivityLog::ACTION_ACCOUNT_CREATION,
            ActivityLog::ACTION_CREATE,
            ActivityLog::ACTION_UPDATE,
            ActivityLog::ACTION_DELETE,
            ActivityLog::ACTION_RESTORE,
            ActivityLog::ACTION_PRINT,
            ActivityLog::ACTION_EXPORT_PDF,
            ActivityLog::ACTION_EXPORT_EXCEL,
            ActivityLog::ACTION_EXPORT_CSV,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveQueryParams(Request $request, bool $includePage = true): array
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = $this->paginationHelper->resolvePerPage($request, self::SESSION_PER_PAGE, 25);
        $perPageSelected = $this->paginationHelper->getStoredPerPage($request, self::SESSION_PER_PAGE, '25');
        $sort = (string) $request->query->get('sort', 'created_at');
        $direction = strtolower((string) $request->query->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!in_array($sort, ['created_at', 'full_name', 'email', 'action', 'module'], true)) {
            $sort = 'created_at';
        }

        $dateFromStr = trim((string) $request->query->get('date_from', ''));
        $dateToStr = trim((string) $request->query->get('date_to', ''));

        return [
            'search' => trim((string) $request->query->get('q', '')),
            'role' => $this->nullableString($request->query->get('role')),
            'action' => $this->nullableString($request->query->get('action')),
            'roleStr' => trim((string) $request->query->get('role', '')),
            'actionStr' => trim((string) $request->query->get('action', '')),
            'dateFrom' => $this->parseDate($dateFromStr, false),
            'dateTo' => $this->parseDate($dateToStr, true),
            'dateFromStr' => $dateFromStr,
            'dateToStr' => $dateToStr,
            'sort' => $sort,
            'direction' => $direction,
            'page' => $includePage ? $page : 1,
            'perPage' => $perPage,
            'perPageSelected' => $perPageSelected,
        ];
    }

    private function parseDate(string $value, bool $endOfDay): ?\DateTimeInterface
    {
        if ($value === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$date instanceof \DateTime) {
            return null;
        }

        $date->setTime($endOfDay ? 23 : 0, $endOfDay ? 59 : 0, $endOfDay ? 59 : 0);

        return $date;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string|int|null>
     */
    private function serializeExportRow(ActivityLog $log): array
    {
        return [
            $this->dateTimeFormatter->format($log->getCreatedAt()),
            $log->getFullName(),
            $log->getEmail(),
            $log->getRole(),
            $log->getAction(),
            $log->getModule(),
            $log->getDescription(),
            $log->getIpAddress(),
        ];
    }
}
