<?php

namespace App\Controller;

use App\Entity\Organisation;
use App\Entity\User;
use App\Entity\UserOrganisation;
use App\Repository\UserOrganisationRepository;
use App\Service\AuditLogService;
use App\Service\KnowledgeBaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/kb')]
class KnowledgeBaseApiController extends AbstractController
{
    public function __construct(
        private readonly UserOrganisationRepository $userOrganisationRepository,
        private readonly KnowledgeBaseService $kbService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    #[Route('/upload', name: 'api_kb_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $auth = $this->authenticateToken($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        [$userOrganisation, $user, $organisation] = $auth;

        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file provided'], Response::HTTP_BAD_REQUEST);
        }

        $documentType = $request->request->get('document_type', 'general');
        if (!in_array($documentType, ['general', 'project_knowledge'], true)) {
            return $this->json([
                'error' => 'Invalid document_type',
                'valid_values' => ['general', 'project_knowledge'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $sourceUrl = $request->request->get('source_url', '');

        $result = $this->kbService->uploadDocument(
            $organisation,
            $file->getPathname(),
            $file->getClientOriginalName(),
            (string) $user->getId(),
            $documentType,
            $sourceUrl
        );

        $this->auditLogService->logWithOrganisation(
            'api.kb.upload',
            $organisation,
            $user,
            [
                'filename' => $file->getClientOriginalName(),
                'document_type' => $documentType,
            ]
        );

        $statusCode = isset($result['error']) ? Response::HTTP_BAD_REQUEST : Response::HTTP_ACCEPTED;

        return $this->json($result, $statusCode);
    }

    #[Route('/snippet', name: 'api_kb_snippet', methods: ['POST'])]
    public function snippet(Request $request): JsonResponse
    {
        $auth = $this->authenticateToken($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        [$userOrganisation, $user, $organisation] = $auth;

        $data = json_decode($request->getContent(), true) ?? [];
        $title = $data['title'] ?? '';
        $text = $data['text'] ?? '';
        $sourceUrl = $data['source_url'] ?? '';

        if ($title === '' || $text === '') {
            return $this->json([
                'error' => 'title and text are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->kbService->addSnippet(
            $organisation,
            $title,
            $text,
            (string) $user->getId(),
            $sourceUrl
        );

        $this->auditLogService->logWithOrganisation(
            'api.kb.snippet',
            $organisation,
            $user,
            ['title' => $title]
        );

        $statusCode = isset($result['error']) ? Response::HTTP_BAD_REQUEST : Response::HTTP_CREATED;

        return $this->json($result, $statusCode);
    }

    #[Route('/documents/{docId}', name: 'api_kb_document_delete', methods: ['DELETE'])]
    public function deleteDocument(Request $request, string $docId): JsonResponse
    {
        $auth = $this->authenticateToken($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        [$userOrganisation, $user, $organisation] = $auth;

        $result = $this->kbService->deleteDocument($organisation, $docId);

        $this->auditLogService->logWithOrganisation(
            'api.kb.document.delete',
            $organisation,
            $user,
            ['doc_id' => $docId]
        );

        $statusCode = isset($result['error']) ? Response::HTTP_BAD_REQUEST : Response::HTTP_OK;

        return $this->json($result, $statusCode);
    }

    #[Route('/documents', name: 'api_kb_documents', methods: ['GET'])]
    public function documents(Request $request): JsonResponse
    {
        $auth = $this->authenticateToken($request);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        [$userOrganisation, $user, $organisation] = $auth;

        $page = $request->query->getInt('page', 1);
        $perPage = $request->query->getInt('per_page', 25);

        $result = $this->kbService->listDocuments($organisation, $page, $perPage);

        $this->auditLogService->logWithOrganisation(
            'api.kb.documents',
            $organisation,
            $user,
            ['page' => $page, 'per_page' => $perPage]
        );

        return $this->json($result);
    }

    /**
     * @return array{0: UserOrganisation, 1: User, 2: Organisation}|JsonResponse
     */
    private function authenticateToken(Request $request): array|JsonResponse
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return $this->json([
                'error' => 'No API token provided',
                'message' => 'Please generate a token at /profile/',
                'hint' => 'Include token via X-Prompt-Token header or Authorization: Bearer <token>',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userOrganisation = $this->userOrganisationRepository->findOneBy([
            'personalAccessToken' => $token,
        ]);

        if ($userOrganisation === null) {
            return $this->json([
                'error' => 'Invalid or expired token',
                'message' => 'Please generate a new token at /profile/',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $userOrganisation->getUser();
        $organisation = $userOrganisation->getOrganisation();

        if ($user === null || $organisation === null) {
            return $this->json([
                'error' => 'Invalid token configuration',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return [$userOrganisation, $user, $organisation];
    }

    private function extractToken(Request $request): ?string
    {
        $token = $request->headers->get('X-Prompt-Token');

        if ($token !== null && $token !== '') {
            return $token;
        }

        $authHeader = $request->headers->get('Authorization', '');

        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            if ($token !== '') {
                return $token;
            }
        }

        return null;
    }
}
