<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Service\ActivityLogService;
use App\Service\DateTimeFormatterService;
use App\Service\DocumentOptionsService;
use App\Service\PaginationHelper;
use App\Service\SpreadsheetExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reports')]
final class ReportController extends AbstractController
{
    private const SESSION_PER_PAGE = 'reports_per_page';

    public function __construct(
        private readonly DocumentOptionsService $documentOptions,
        private readonly PaginationHelper $paginationHelper,
        private readonly SpreadsheetExportService $exportService,
        private readonly DateTimeFormatterService $dateTimeFormatter,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    #[Route('', name: 'app_reports', methods: ['GET'])]
    public function index(Request $request, DocumentRepository $documentRepository): Response
    {
        $params = $this->resolveQueryParams($request);
        $result = $documentRepository->findFiltered(
            $params['search'],
            $params['campus'],
            $params['documentType'],
            $params['status'],
            $params['userRole'],
            $params['dateFrom'],
            $params['dateTo'],
            $params['sort'],
            $params['direction'],
            $params['page'],
            $params['perPage'],
        );

        return $this->render('reports/index.html.twig', array_merge($this->getFilterOptions(), [
            'documents' => $result['items'],
            'total' => $result['total'],
            'page' => $params['page'],
            'perPage' => $params['perPage'],
            'perPageSelected' => $params['perPageSelected'],
            'search' => $params['search'],
            'campus' => $params['campusStr'],
            'documentType' => $params['documentTypeStr'],
            'status' => $params['statusStr'],
            'userRole' => $params['userRoleStr'],
            'dateFrom' => $params['dateFromStr'],
            'dateTo' => $params['dateToStr'],
            'sort' => $params['sort'],
            'direction' => $params['direction'],
        ]));
    }

    #[Route('/data', name: 'app_reports_data', methods: ['GET'])]
    public function data(Request $request, DocumentRepository $documentRepository): Response
    {
        $params = $this->resolveQueryParams($request);
        $result = $documentRepository->findFiltered(
            $params['search'],
            $params['campus'],
            $params['documentType'],
            $params['status'],
            $params['userRole'],
            $params['dateFrom'],
            $params['dateTo'],
            $params['sort'],
            $params['direction'],
            $params['page'],
            $params['perPage'],
        );

        return $this->render('reports/_table.html.twig', [
            'documents' => $result['items'],
            'total' => $result['total'],
            'page' => $params['page'],
            'perPage' => $params['perPage'],
            'perPageSelected' => $params['perPageSelected'],
            'sort' => $params['sort'],
            'direction' => $params['direction'],
        ]);
    }

    #[Route('/print', name: 'app_reports_print', methods: ['GET'])]
    public function print(Request $request, DocumentRepository $documentRepository): Response
    {
        $this->logExportAction($request, 'print');
        $params = $this->resolveQueryParams($request, false);
        $documents = $this->fetchAllDocuments($request, $documentRepository);

        return $this->render('reports/print.html.twig', [
            'documents' => $documents,
            'search' => $params['search'],
            'generatedAt' => $this->dateTimeFormatter->format($this->dateTimeFormatter->now()),
        ]);
    }

    #[Route('/export/csv', name: 'app_reports_export_csv', methods: ['GET'])]
    public function exportCsv(Request $request, DocumentRepository $documentRepository): Response
    {
        $this->logExportAction($request, 'csv');
        $documents = $this->fetchAllDocuments($request, $documentRepository);

        return $this->exportService->createCsvResponse(
            ['Date Approved', 'Campus', 'Document Type', 'Status', 'Particulars', 'Amount', 'Nature', 'Created At'],
            array_map(fn (Document $document) => $this->serializeExportRow($document), $documents),
            'vpsas-reports-'.date('Y-m-d').'.csv',
        );
    }

    #[Route('/export/excel', name: 'app_reports_export_excel', methods: ['GET'])]
    public function exportExcel(Request $request, DocumentRepository $documentRepository): Response
    {
        $this->logExportAction($request, 'excel');
        $documents = $this->fetchAllDocuments($request, $documentRepository);

        return $this->exportService->createExcelResponse(
            ['Date Approved', 'Campus', 'Document Type', 'Status', 'Particulars', 'Amount', 'Nature', 'Created At'],
            array_map(fn (Document $document) => $this->serializeExportRow($document), $documents),
            'vpsas-reports-'.date('Y-m-d').'.xls',
        );
    }

    #[Route('/export/pdf', name: 'app_reports_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, DocumentRepository $documentRepository): Response
    {
        $this->logExportAction($request, 'pdf');
        $params = $this->resolveQueryParams($request, false);
        $documents = $this->fetchAllDocuments($request, $documentRepository);

        return $this->render('reports/pdf.html.twig', [
            'documents' => $documents,
            'search' => $params['search'],
            'generatedAt' => $this->dateTimeFormatter->format($this->dateTimeFormatter->now()),
        ]);
    }

    /**
     * @return list<Document>
     */
    private function fetchAllDocuments(Request $request, DocumentRepository $documentRepository): array
    {
        $params = $this->resolveQueryParams($request, false);

        return $documentRepository->findAllFiltered(
            $params['search'],
            $params['campus'],
            $params['documentType'],
            $params['status'],
            $params['userRole'],
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

        match ($type) {
            'print' => $this->activityLogService->logPrint($user, 'Reports', 'Printed document reports', $request),
            'pdf' => $this->activityLogService->logExportPdf($user, 'Reports', 'Exported document reports to PDF', $request),
            'excel' => $this->activityLogService->logExportExcel($user, 'Reports', 'Exported document reports to Excel', $request),
            'csv' => $this->activityLogService->logExportCsv($user, 'Reports', 'Exported document reports to CSV', $request),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function getFilterOptions(): array
    {
        return [
            'campuses' => $this->documentOptions->getCampuses(),
            'documentTypes' => $this->documentOptions->getDocumentTypes(),
            'statuses' => $this->documentOptions->getDocumentStatuses(),
            'userRoles' => $this->documentOptions->getUserRoleFilterOptions(),
            'perPageOptions' => PaginationHelper::PER_PAGE_OPTIONS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveQueryParams(Request $request, bool $includePage = true): array
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = $this->paginationHelper->resolvePerPage($request, self::SESSION_PER_PAGE, 10);
        $perPageSelected = $this->paginationHelper->getStoredPerPage($request, self::SESSION_PER_PAGE, '10');
        $sort = (string) $request->query->get('sort', 'date_approved');
        $direction = strtolower((string) $request->query->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!in_array($sort, ['date_approved', 'campus', 'amount', 'created_at'], true)) {
            $sort = 'date_approved';
        }

        $dateFromStr = trim((string) $request->query->get('date_from', ''));
        $dateToStr = trim((string) $request->query->get('date_to', ''));

        return [
            'search' => trim((string) $request->query->get('q', '')),
            'campus' => $this->nullableString($request->query->get('campus')),
            'documentType' => $this->nullableString($request->query->get('document_type')),
            'status' => $this->nullableString($request->query->get('status')),
            'userRole' => $this->nullableString($request->query->get('user_role')),
            'dateFrom' => $this->parseDate($dateFromStr, false),
            'dateTo' => $this->parseDate($dateToStr, true),
            'campusStr' => trim((string) $request->query->get('campus', '')),
            'documentTypeStr' => trim((string) $request->query->get('document_type', '')),
            'statusStr' => trim((string) $request->query->get('status', '')),
            'userRoleStr' => trim((string) $request->query->get('user_role', '')),
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
     * @return list<string|int|float|null>
     */
    private function serializeExportRow(Document $document): array
    {
        return [
            $document->getDateApproved()?->format('Y-m-d'),
            $document->getCampus(),
            $document->getDocumentType(),
            $document->getStatus(),
            $document->getParticulars(),
            $document->getAmount(),
            $document->getNature(),
            $this->dateTimeFormatter->format($document->getCreatedAt()),
        ];
    }
}
