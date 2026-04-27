<?php

namespace App\Tests\Unit\Service;

use App\Entity\Organisation;
use App\Service\KnowledgeBaseService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class KnowledgeBaseServiceTest extends TestCase
{
    private const ORG_UUID = 'test-org-uuid';
    private const BASE_URL = 'https://orchestrator.example.com';
    private const AUTH_USER = 'api_user';
    private const AUTH_PASS = 'api_pass';

    private function createOrganisation(?string $orchestratorUrl = self::BASE_URL): Organisation
    {
        $org = $this->createStub(Organisation::class);
        $org->method('getUuid')->willReturn(self::ORG_UUID);
        $org->method('getOrchestratorApiUrl')->willReturn($orchestratorUrl);
        return $org;
    }

    private function createService(MockHttpClient $httpClient): KnowledgeBaseService
    {
        return new KnowledgeBaseService($httpClient, new NullLogger(), self::AUTH_USER, self::AUTH_PASS);
    }

    /** Builds a capturing MockHttpClient; captured options available after the request. */
    private function capturingClient(?array &$capturedOptions, string $responseBody = '{}'): MockHttpClient
    {
        return new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedOptions, $responseBody): MockResponse {
                $capturedOptions = ['method' => $method, 'url' => $url] + $options;
                return new MockResponse($responseBody, ['http_code' => 200]);
            }
        );
    }

    // --- listDocuments ---

    public function testListDocumentsReturnsErrorWhenNoOrchestratorUrl(): void
    {
        $service = $this->createService(new MockHttpClient());

        $result = $service->listDocuments($this->createOrganisation(null));

        $this->assertSame(['error' => 'Orchestrator URL not configured'], $result);
    }

    public function testListDocumentsSendsGetToCorrectUrl(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->listDocuments($this->createOrganisation());

        $this->assertSame('GET', $opts['method']);
        $this->assertStringContainsString('/api/kb/documents', $opts['url']);
    }

    public function testListDocumentsPassesPaginationAndOrgUuidAsQueryParams(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->listDocuments($this->createOrganisation(), 3, 50);

        $this->assertSame(self::ORG_UUID, $opts['query']['org_uuid']);
        $this->assertSame(3, $opts['query']['page']);
        $this->assertSame(50, $opts['query']['per_page']);
    }

    public function testListDocumentsReturnsErrorOnHttpException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['error' => 'Connection refused']));
        $service = $this->createService($httpClient);

        $result = $service->listDocuments($this->createOrganisation());

        $this->assertArrayHasKey('error', $result);
    }

    // --- getDocument ---

    public function testGetDocumentReturnsErrorWhenNoOrchestratorUrl(): void
    {
        $service = $this->createService(new MockHttpClient());

        $result = $service->getDocument($this->createOrganisation(null), 'doc-1');

        $this->assertSame(['error' => 'Orchestrator URL not configured'], $result);
    }

    public function testGetDocumentSendsGetWithDocIdInPathAndOrgUuid(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->getDocument($this->createOrganisation(), 'doc-abc');

        $this->assertSame('GET', $opts['method']);
        $this->assertStringContainsString('/api/kb/documents/doc-abc', $opts['url']);
        $this->assertSame(self::ORG_UUID, $opts['query']['org_uuid']);
    }

    // --- deleteDocument ---

    public function testDeleteDocumentReturnsErrorWhenNoOrchestratorUrl(): void
    {
        $service = $this->createService(new MockHttpClient());

        $result = $service->deleteDocument($this->createOrganisation(null), 'doc-1');

        $this->assertSame(['error' => 'Orchestrator URL not configured'], $result);
    }

    public function testDeleteDocumentSendsDeleteWithDocIdInPathAndOrgUuid(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->deleteDocument($this->createOrganisation(), 'doc-xyz');

        $this->assertSame('DELETE', $opts['method']);
        $this->assertStringContainsString('/api/kb/documents/doc-xyz', $opts['url']);
        $this->assertSame(self::ORG_UUID, $opts['query']['org_uuid']);
    }

    public function testDeleteDocumentReturnsResponsePayload(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse('{"status":"deleted"}', ['http_code' => 200])
        );
        $service = $this->createService($httpClient);

        $result = $service->deleteDocument($this->createOrganisation(), 'doc-1');

        $this->assertSame('deleted', $result['status']);
    }

    public function testDeleteDocumentReturnsErrorOnHttpException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['error' => 'Server error']));
        $service = $this->createService($httpClient);

        $result = $service->deleteDocument($this->createOrganisation(), 'doc-1');

        $this->assertArrayHasKey('error', $result);
    }

    // --- addSnippet ---

    public function testAddSnippetReturnsErrorWhenNoOrchestratorUrl(): void
    {
        $service = $this->createService(new MockHttpClient());

        $result = $service->addSnippet($this->createOrganisation(null), 'Title', 'Body', 'user-1');

        $this->assertSame(['error' => 'Orchestrator URL not configured'], $result);
    }

    public function testAddSnippetSendsPostWithRequiredFields(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->addSnippet($this->createOrganisation(), 'My Title', 'My Text', 'user-42');

        $this->assertSame('POST', $opts['method']);
        $this->assertStringContainsString('/api/kb/snippet', $opts['url']);

        $body = json_decode($opts['body'], true);
        $this->assertSame(self::ORG_UUID, $body['org_uuid']);
        $this->assertSame('My Title', $body['title']);
        $this->assertSame('My Text', $body['text']);
        $this->assertSame('user-42', $body['uploaded_by']);
    }

    public function testAddSnippetOmitsSourceUrlWhenEmpty(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->addSnippet($this->createOrganisation(), 'Title', 'Text', 'user-1', '');

        $body = json_decode($opts['body'], true);
        $this->assertArrayNotHasKey('source_url', $body);
    }

    public function testAddSnippetIncludesSourceUrlWhenProvided(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->addSnippet($this->createOrganisation(), 'Title', 'Text', 'user-1', 'https://example.com/page');

        $body = json_decode($opts['body'], true);
        $this->assertSame('https://example.com/page', $body['source_url']);
    }

    // --- getSnippetContent ---

    public function testGetSnippetContentSendsGetWithDocIdInPath(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->getSnippetContent($this->createOrganisation(), 'snip-1');

        $this->assertSame('GET', $opts['method']);
        $this->assertStringContainsString('/api/kb/snippet/snip-1/content', $opts['url']);
    }

    // --- updateSnippet ---

    public function testUpdateSnippetSendsPutWithCorrectFields(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->updateSnippet($this->createOrganisation(), 'snip-5', 'New Title', 'New Text', 'user-7');

        $this->assertSame('PUT', $opts['method']);
        $this->assertStringContainsString('/api/kb/snippet/snip-5', $opts['url']);

        $body = json_decode($opts['body'], true);
        $this->assertSame('New Title', $body['title']);
        $this->assertSame('New Text', $body['text']);
        $this->assertSame('user-7', $body['uploaded_by']);
        $this->assertSame(self::ORG_UUID, $opts['query']['org_uuid']);
    }

    // --- listSources ---

    public function testListSourcesSendsGetWithOrgUuid(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->listSources($this->createOrganisation());

        $this->assertSame('GET', $opts['method']);
        $this->assertStringContainsString('/api/kb/sources', $opts['url']);
        $this->assertSame(self::ORG_UUID, $opts['query']['org_uuid']);
    }

    // --- startCrawl ---

    public function testStartCrawlSendsPostWithSitemapAndCreatedBy(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->startCrawl($this->createOrganisation(), 'https://example.com/sitemap.xml', 'user-3');

        $this->assertSame('POST', $opts['method']);
        $this->assertStringContainsString('/api/kb/crawl', $opts['url']);

        $body = json_decode($opts['body'], true);
        $this->assertSame(self::ORG_UUID, $body['org_uuid']);
        $this->assertSame('https://example.com/sitemap.xml', $body['sitemap_url']);
        $this->assertSame('user-3', $body['created_by']);
    }

    // --- getCrawlJob ---

    public function testGetCrawlJobSendsGetWithJobIdInPath(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->getCrawlJob($this->createOrganisation(), 'job-99');

        $this->assertSame('GET', $opts['method']);
        $this->assertStringContainsString('/api/kb/crawl/job-99', $opts['url']);
    }

    // --- listCrawlJobs ---

    public function testListCrawlJobsSendsGetWithOrgUuid(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->listCrawlJobs($this->createOrganisation());

        $this->assertSame('GET', $opts['method']);
        $this->assertStringContainsString('/api/kb/crawls', $opts['url']);
        $this->assertSame(self::ORG_UUID, $opts['query']['org_uuid']);
    }

    // --- deleteCrawlJob ---

    public function testDeleteCrawlJobSendsDeleteWithJobIdInPath(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->deleteCrawlJob($this->createOrganisation(), 'job-77');

        $this->assertSame('DELETE', $opts['method']);
        $this->assertStringContainsString('/api/kb/crawl/job-77', $opts['url']);
    }

    // --- listDomains ---

    public function testListDomainsSendsGetWithOrgUuid(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->listDomains($this->createOrganisation());

        $this->assertSame('GET', $opts['method']);
        $this->assertStringContainsString('/api/kb/domains', $opts['url']);
        $this->assertSame(self::ORG_UUID, $opts['query']['org_uuid']);
    }

    // --- deleteDomain ---

    public function testDeleteDomainSendsDeleteWithDomainInPathAndOrgUuid(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->deleteDomain($this->createOrganisation(), 'example.com');

        $this->assertSame('DELETE', $opts['method']);
        $this->assertStringContainsString('/api/kb/domains/example.com', $opts['url']);
        $this->assertSame(self::ORG_UUID, $opts['query']['org_uuid']);
    }

    // --- uploadDocument ---

    public function testUploadDocumentReturnsErrorWhenNoOrchestratorUrl(): void
    {
        $service = $this->createService(new MockHttpClient());

        $result = $service->uploadDocument($this->createOrganisation(null), '/tmp/file.pdf', 'file.pdf', 'user-1');

        $this->assertSame(['error' => 'Orchestrator URL not configured'], $result);
    }

    public function testUploadDocumentSendsMultipartPostAndReturnsResponse(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'kb_test_');
        file_put_contents($tmpFile, 'file content');

        try {
            $opts = null;
            $service = $this->createService($this->capturingClient($opts, '{"id":"doc-new"}'));

            $result = $service->uploadDocument($this->createOrganisation(), $tmpFile, 'report.pdf', 'user-5', 'general');

            $this->assertSame('POST', $opts['method']);
            $this->assertStringContainsString('/api/kb/upload', $opts['url']);
            $this->assertSame('doc-new', $result['id']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testUploadDocumentWithSourceUrlSucceeds(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'kb_test_');
        file_put_contents($tmpFile, 'file content');

        try {
            $service = $this->createService(
                new MockHttpClient(new MockResponse('{"id":"doc-src"}', ['http_code' => 200]))
            );

            $result = $service->uploadDocument(
                $this->createOrganisation(),
                $tmpFile,
                'doc.pdf',
                'user-1',
                'general',
                'https://source.example.com'
            );

            $this->assertArrayNotHasKey('error', $result);
            $this->assertSame('doc-src', $result['id']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testUploadDocumentReturnsErrorOnHttpException(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'kb_test_');
        file_put_contents($tmpFile, 'content');

        try {
            $httpClient = new MockHttpClient(new MockResponse('', ['error' => 'Timeout']));
            $service = $this->createService($httpClient);

            $result = $service->uploadDocument($this->createOrganisation(), $tmpFile, 'file.pdf', 'user-1');

            $this->assertArrayHasKey('error', $result);
        } finally {
            unlink($tmpFile);
        }
    }

    // --- downloadDocument ---

    public function testDownloadDocumentReturnsErrorWhenNoOrchestratorUrl(): void
    {
        $service = $this->createService(new MockHttpClient());

        $result = $service->downloadDocument($this->createOrganisation(null), 'doc-1');

        $this->assertSame(['error' => 'Orchestrator URL not configured'], $result);
    }

    public function testDownloadDocumentExtractsQuotedFilenameFromContentDisposition(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('PDF bytes', [
            'http_code' => 200,
            'response_headers' => [
                'content-disposition' => 'attachment; filename="my-report.pdf"',
                'content-type' => 'application/pdf',
            ],
        ]));
        $service = $this->createService($httpClient);

        $result = $service->downloadDocument($this->createOrganisation(), 'doc-1');

        $this->assertSame('my-report.pdf', $result['filename']);
        $this->assertSame('application/pdf', $result['content_type']);
        $this->assertSame('PDF bytes', $result['content']);
    }

    public function testDownloadDocumentExtractsUnquotedFilenameFromContentDisposition(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('content', [
            'http_code' => 200,
            'response_headers' => [
                'content-disposition' => 'attachment; filename=report.pdf',
                'content-type' => 'text/plain',
            ],
        ]));
        $service = $this->createService($httpClient);

        $result = $service->downloadDocument($this->createOrganisation(), 'doc-1');

        $this->assertSame('report.pdf', $result['filename']);
    }

    public function testDownloadDocumentFallsBackToDefaultFilenameWhenNoContentDisposition(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('content', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/octet-stream'],
        ]));
        $service = $this->createService($httpClient);

        $result = $service->downloadDocument($this->createOrganisation(), 'doc-1');

        $this->assertSame('document', $result['filename']);
        $this->assertSame('application/octet-stream', $result['content_type']);
    }

    public function testDownloadDocumentReturnsErrorOnHttpException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['error' => 'Server error']));
        $service = $this->createService($httpClient);

        $result = $service->downloadDocument($this->createOrganisation(), 'doc-1');

        $this->assertArrayHasKey('error', $result);
    }

    // --- shared request behaviour ---

    public function testRequestSetsBasicAuthHeader(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->listDocuments($this->createOrganisation());

        $expectedCredentials = base64_encode(self::AUTH_USER . ':' . self::AUTH_PASS);
        $authHeader = $opts['normalized_headers']['authorization'][0] ?? '';
        $this->assertStringContainsString('Basic ' . $expectedCredentials, $authHeader);
    }

    public function testRequestSetsDefaultTimeout(): void
    {
        $opts = null;
        $service = $this->createService($this->capturingClient($opts));

        $service->listDocuments($this->createOrganisation());

        $this->assertEquals(15, $opts['timeout']);
    }
}
