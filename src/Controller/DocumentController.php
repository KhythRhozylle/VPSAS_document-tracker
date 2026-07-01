<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use App\Service\ActivityLogService;
use App\Service\DateTimeFormatterService;
use App\Service\DocumentOptionsService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/documents')]
final class DocumentController extends AbstractController
{
    public function __construct(
        private readonly DocumentOptionsService $documentOptions,
        private readonly DateTimeFormatterService $dateTimeFormatter,
    ) {
    }

    #[Route('', name: 'app_documents', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService,
        ActivityLogService $activityLogService,
    ): Response {
        $document = new Document();
        $document->setStatus('Pending');
        $form = $this->createDocumentForm($document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();
            $document->setCreatedBy($user);

            $entityManager->persist($document);
            $entityManager->flush();

            $notificationService->notifyDocumentCreated($document);
            $activityLogService->logCreate(
                $user,
                'Documents',
                (int) $document->getId(),
                $this->serializeDocument($document),
                sprintf('Created document for %s', $document->getCampus()),
                $request,
            );

            $this->addFlash('success', 'Document saved successfully.');

            return $this->redirectToRoute('app_documents');
        }

        return $this->render('documents/index.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_documents_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Document $document): JsonResponse
    {
        return $this->json($this->serializeDocument($document));
    }

    #[Route('/{id}/edit', name: 'app_documents_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Document $document,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService,
        ActivityLogService $activityLogService,
    ): Response {
        $oldData = $this->serializeDocument($document);
        $form = $this->createDocumentForm($document);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $entityManager->flush();

                $notificationService->notifyDocumentUpdated($document);

                /** @var User $user */
                $user = $this->getUser();
                $activityLogService->logUpdate(
                    $user,
                    'Documents',
                    (int) $document->getId(),
                    $oldData,
                    $this->serializeDocument($document),
                    sprintf('Updated document #%d for %s', $document->getId(), $document->getCampus()),
                    $request,
                );

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => true,
                        'message' => 'Document updated successfully.',
                        'document' => $this->serializeDocument($document),
                    ]);
                }

                $this->addFlash('success', 'Document updated successfully.');

                return $this->redirectToRoute('app_reports');
            }

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'errors' => $this->getFormErrors($form),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if ($request->isXmlHttpRequest() && $request->isMethod('GET')) {
            return $this->render('documents/_edit_form.html.twig', [
                'form' => $form->createView(),
                'document' => $document,
            ]);
        }

        return $this->redirectToRoute('app_reports');
    }

    #[Route('/{id}/delete', name: 'app_documents_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Request $request,
        Document $document,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService,
        ActivityLogService $activityLogService,
    ): Response {
        if (!$this->isCsrfTokenValid('delete'.$document->getId(), (string) $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'message' => 'Invalid security token.'], Response::HTTP_FORBIDDEN);
            }

            $this->addFlash('error', 'Invalid security token.');

            return $this->redirectToRoute('app_reports');
        }

        $campus = $document->getCampus() ?? '';
        $documentId = (int) $document->getId();
        $oldData = $this->serializeDocument($document);

        $entityManager->remove($document);
        $entityManager->flush();

        $notificationService->notifyDocumentDeleted($campus, $documentId);

        /** @var User $user */
        $user = $this->getUser();
        $activityLogService->logDelete(
            $user,
            'Documents',
            $documentId,
            $oldData,
            sprintf('Deleted document #%d for %s', $documentId, $campus),
            $request,
        );

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true, 'message' => 'Document deleted successfully.']);
        }

        $this->addFlash('success', 'Document deleted successfully.');

        return $this->redirectToRoute('app_reports');
    }

    private function createDocumentForm(Document $document): FormInterface
    {
        return $this->createForm(DocumentType::class, $document, [
            'campus_choices' => $this->documentOptions->getCampuses(),
            'document_type_choices' => $this->documentOptions->getDocumentTypes(),
            'status_choices' => $this->documentOptions->getDocumentStatuses(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDocument(Document $document): array
    {
        return [
            'id' => $document->getId(),
            'dateApproved' => $document->getDateApproved()?->format('Y-m-d'),
            'campus' => $document->getCampus(),
            'documentType' => $document->getDocumentType(),
            'status' => $document->getStatus(),
            'particulars' => $document->getParticulars(),
            'amount' => number_format((float) $document->getAmount(), 2, '.', ''),
            'nature' => $document->getNature(),
            'createdAt' => $this->dateTimeFormatter->format($document->getCreatedAt()),
            'updatedAt' => $this->dateTimeFormatter->format($document->getUpdatedAt()),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getFormErrors(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $fieldName = $origin?->getName() ?? '_form';
            $errors[$fieldName] = $error->getMessage();
        }

        return $errors;
    }
}
