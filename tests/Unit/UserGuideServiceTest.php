<?php

namespace Tests\Unit;

use App\Services\Panduan\UserGuideService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UserGuideServiceTest extends TestCase
{
    private string $docsPath;

    private UserGuideService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->docsPath = storage_path('framework/testing/docs/panduan');
        File::deleteDirectory($this->docsPath);
        File::ensureDirectoryExists($this->docsPath);
        $this->service = new UserGuideService($this->docsPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->docsPath);
        parent::tearDown();
    }

    public function test_get_topics_returns_sorted_list_of_topics(): void
    {
        File::put($this->docsPath.'/01-ringkasan-umum.md', "# Ringkasan Umum Sistem\n\nSelamat datang di SiAkurat.");
        File::put($this->docsPath.'/02-kasbank.md', "# Kasbank Penerimaan\n\nPanduan kasbank.");

        $topics = $this->service->getTopics();

        $this->assertCount(2, $topics);
        $this->assertEquals('01-ringkasan-umum', $topics->first()['slug']);
        $this->assertEquals('Ringkasan Umum Sistem', $topics->first()['title']);
    }

    public function test_get_topic_returns_parsed_html_for_valid_slug(): void
    {
        File::put($this->docsPath.'/01-ringkasan-umum.md', "# Panduan Pengguna\n\nLangkah **pertama**.");

        $topic = $this->service->getTopic('01-ringkasan-umum');

        $this->assertNotNull($topic);
        $this->assertEquals('Panduan Pengguna', $topic['title']);
        $this->assertStringContainsString('<strong>pertama</strong>', $topic['html']);
    }

    public function test_get_topic_prevents_path_traversal(): void
    {
        $topic = $this->service->getTopic('../../config/database');
        $this->assertNull($topic);
    }

    public function test_get_topic_returns_null_when_file_not_found(): void
    {
        $topic = $this->service->getTopic('non-existent-topic');
        $this->assertNull($topic);
    }

    public function test_get_topics_returns_empty_collection_when_directory_does_not_exist(): void
    {
        $nonExistentPath = storage_path('framework/testing/non_existent_path_'.uniqid());
        $service = new UserGuideService($nonExistentPath);

        $topics = $service->getTopics();

        $this->assertEmpty($topics);
    }

    public function test_extract_title_falls_back_to_headline_filename_if_no_heading_found(): void
    {
        File::put($this->docsPath.'/05-laporan-keuangan.md', 'Tidak ada heading H1 di sini.');

        $topic = $this->service->getTopic('05-laporan-keuangan');

        $this->assertNotNull($topic);
        $this->assertEquals('Laporan Keuangan', $topic['title']);
    }

    public function test_default_constructor_uses_resource_path(): void
    {
        $defaultService = new UserGuideService;
        $this->assertNotNull($defaultService);

        $topics = $defaultService->getTopics();
        $this->assertGreaterThanOrEqual(3, $topics->count());
        $this->assertTrue($topics->contains('slug', '01-ringkasan-umum'));
        $this->assertTrue($topics->contains('slug', '02-kasbank-penerimaan'));
        $this->assertTrue($topics->contains('slug', '03-bukubesar-jurnal-umum'));

        $topic = $defaultService->getTopic('01-ringkasan-umum');
        $this->assertNotNull($topic);
        $this->assertStringContainsString('Ringkasan Umum Sistem SiAkurat', $topic['title']);
        $this->assertNotEmpty($topic['html']);
    }
}
