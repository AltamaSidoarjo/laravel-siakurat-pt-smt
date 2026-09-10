<?php

namespace Tests\Feature;

use App\Exceptions\BillingApiException;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\SimrsImportPendapatan;
use App\Models\User;
use App\Services\Bridging\BillingPendapatanDetailService;
use App\Services\Bridging\BillingPendapatanInvoiceImportService;
use App\Services\Bridging\BridgingPendapatanService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class BridgingPendapatanApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'services.billing_api.base_url' => 'http://billing.test/api',
            'services.billing_api.username' => 'api-user',
            'services.billing_api.password' => 'api-password',
        ]);

        Cache::flush();
        $this->prepareImportedTable();
    }

    public function test_rawat_jalan_endpoint_receives_its_specific_filters_and_normalizes_rows(): void
    {
        Http::fake([
            'http://billing.test/api/get-token' => Http::response($this->tokenPayload()),
            'http://billing.test/api/rawat-jalan*' => Http::response([
                'status' => true,
                'data' => [[
                    'ID' => '1761891',
                    'RegNum' => 'RJ-001',
                    'Tanggal' => '2026-08-15 09:30:45',
                    'Nama' => 'Pasien API',
                    'Dokter' => 'Dokter API',
                    'SubLayanan' => 'Spesialis API',
                    'PxRS' => 'BPJS',
                ]],
            ]),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-simrs', $this->dataTableRequest([
                'startDate' => '2026-08-15',
                'endDate' => '2026-08-15',
                'jenisLayanan' => 'rawat_jalan',
                'spesialisId' => '7',
                'dokterId' => '380',
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.external_id', '1761891')
            ->assertJsonPath('data.0.no_rawat', 'RJ-001')
            ->assertJsonPath('data.0.tanggal_registrasi', '2026-08-15')
            ->assertJsonPath('data.0.nama_pasien', 'Pasien API')
            ->assertJsonPath('data.0.nama_dokter', 'Dokter API')
            ->assertJsonPath('data.0.nama_poli', 'Spesialis API')
            ->assertJsonPath('data.0.status_lanjut', 'Rawat Jalan')
            ->assertJsonPath('data.0.penjamin', 'BPJS');

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/rawat-jalan')) {
                return false;
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['id_spesialis'] ?? null) === '7'
                && ($query['dokter_id'] ?? null) === '380';
        });
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/igd'));
    }

    public function test_igd_endpoint_only_receives_date_filters_and_handles_optional_fields(): void
    {
        Http::fake([
            'http://billing.test/api/get-token' => Http::response($this->tokenPayload()),
            'http://billing.test/api/igd*' => Http::response([
                'status' => true,
                'data' => [[
                    'ID' => '200',
                    'RegNum' => 'IGD-001',
                    'Tanggal' => '2026-08-18',
                    'Nama' => 'Pasien IGD',
                    'Dokter' => null,
                    'SubLayanan' => null,
                ]],
            ]),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-simrs', $this->dataTableRequest([
                'startDate' => '2026-08-18',
                'endDate' => '2026-08-18',
                'jenisLayanan' => 'igd',
                'spesialisId' => '7',
                'dokterId' => '380',
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.nama_dokter', '')
            ->assertJsonPath('data.0.nama_poli', 'IGD')
            ->assertJsonPath('data.0.status_lanjut', 'IGD')
            ->assertJsonPath('data.0.penjamin', '');

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/igd')) {
                return false;
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query === [
                'tgl_awal' => '2026-08-18',
                'tgl_akhir' => '2026-08-18',
            ];
        });
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/rawat-jalan'));
    }

    public function test_billing_account_detail_endpoint_returns_normalized_rows_and_total(): void
    {
        Http::fake([
            'http://billing.test/api/get-token' => Http::response($this->tokenPayload()),
            'http://billing.test/api/akun-all*' => Http::response([
                'status' => true,
                'data' => [
                    ['akun' => '410.01', 'biaya' => 150000, 'jml' => 2, 'job' => 'DR-01'],
                    ['akun' => '410.02', 'biaya' => 50000, 'jml' => 1, 'job' => null],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-account-detail', [
                'externalId' => '1761891',
            ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.akun', '410.01')
            ->assertJsonPath('data.0.biaya', 150000)
            ->assertJsonPath('data.0.jumlah', 2)
            ->assertJsonPath('data.0.job', 'DR-01')
            ->assertJsonPath('data.0.subtotal', 300000)
            ->assertJsonPath('grandTotal', 350000)
            ->assertJsonPath('source', 'api');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/akun-all')
            && $request['id'] === '1761891');
    }

    public function test_billing_account_detail_requires_exactly_one_identifier(): void
    {
        Http::fake();

        $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-account-detail'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['externalId', 'importId']);

        $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-account-detail', [
                'externalId' => '1761891',
                'importId' => 1,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['externalId', 'importId']);

        Http::assertNothingSent();
    }

    public function test_empty_billing_account_detail_returns_an_empty_api_result(): void
    {
        Http::fake([
            'http://billing.test/api/get-token' => Http::response($this->tokenPayload()),
            'http://billing.test/api/akun-all*' => Http::response([
                'status' => true,
                'data' => [],
            ]),
        ]);

        $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-account-detail', [
                'externalId' => 'billing-empty',
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('grandTotal', 0)
            ->assertJsonPath('source', 'api');
    }

    public function test_billing_account_detail_accepts_import_context(): void
    {
        $detailService = Mockery::mock(BillingPendapatanDetailService::class);
        $detailService->shouldReceive('getDetail')
            ->once()
            ->with(null, 25)
            ->andReturn([
                'data' => [[
                    'akun' => '410.03',
                    'job' => null,
                    'biaya' => 75000.0,
                    'jumlah' => 2.0,
                    'subtotal' => 150000.0,
                ]],
                'grandTotal' => 150000.0,
                'source' => 'local_invoice',
            ]);
        $this->app->instance(BillingPendapatanDetailService::class, $detailService);

        $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-account-detail', [
                'importId' => 25,
            ]))
            ->assertOk()
            ->assertJsonPath('source', 'local_invoice')
            ->assertJsonPath('data.0.akun', '410.03')
            ->assertJsonPath('grandTotal', 150000);
    }

    public function test_unavailable_historical_detail_returns_controlled_error(): void
    {
        $detailService = Mockery::mock(BillingPendapatanDetailService::class);
        $detailService->shouldReceive('getDetail')
            ->once()
            ->with(null, 26)
            ->andThrow(new DomainException('Rincian billing tidak tersedia untuk data Jurnal Umum lama.'));
        $this->app->instance(BillingPendapatanDetailService::class, $detailService);

        $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-account-detail', [
                'importId' => 26,
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Rincian billing tidak tersedia untuk data Jurnal Umum lama.');
    }

    public function test_billing_api_failure_returns_controlled_error(): void
    {
        $detailService = Mockery::mock(BillingPendapatanDetailService::class);
        $detailService->shouldReceive('getDetail')
            ->once()
            ->with('api-failed', null)
            ->andThrow(new BillingApiException('Billing API sedang tidak tersedia. Silakan coba kembali.'));
        $this->app->instance(BillingPendapatanDetailService::class, $detailService);

        $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-account-detail', [
                'externalId' => 'api-failed',
            ]))
            ->assertStatus(502)
            ->assertJsonPath('message', 'Billing API sedang tidak tersedia. Silakan coba kembali.');
    }

    public function test_candidate_table_applies_search_pagination_and_excludes_imported_number(): void
    {
        SimrsImportPendapatan::query()->create([
            'nomer_billing' => 'RJ-IMPORTED',
            'tanggal_reg' => '2026-08-15',
        ]);

        Http::fake([
            'http://billing.test/api/get-token' => Http::response($this->tokenPayload()),
            'http://billing.test/api/rawat-jalan*' => Http::response([
                'status' => true,
                'data' => [
                    ['ID' => '1', 'RegNum' => 'RJ-IMPORTED', 'Nama' => 'Sudah Diimpor'],
                    ['ID' => '2', 'RegNum' => 'RJ-AVAILABLE-1', 'Nama' => 'Budi'],
                    ['ID' => '3', 'RegNum' => 'RJ-AVAILABLE-2', 'Nama' => 'Siti'],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-simrs', $this->dataTableRequest([
                'startDate' => '2026-08-15',
                'endDate' => '2026-08-15',
                'jenisLayanan' => 'rawat_jalan',
                'length' => 1,
                'search' => ['value' => 'Budi', 'regex' => 'false'],
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.no_rawat', 'RJ-AVAILABLE-1');
    }

    public function test_rawat_inap_cannot_be_requested(): void
    {
        Http::fake();

        $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-simrs', [
                'startDate' => '2026-08-18',
                'endDate' => '2026-08-18',
                'jenisLayanan' => 'rawat_inap',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jenisLayanan');

        Http::assertNothingSent();
    }

    public function test_pull_page_only_offers_invoice_import_and_displays_penjamin(): void
    {
        Http::fake([
            'http://billing.test/api/get-token' => Http::response($this->tokenPayload()),
            'http://billing.test/api/spesialis' => Http::response(['status' => true, 'data' => []]),
            'http://billing.test/api/dokter' => Http::response(['status' => true, 'data' => []]),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('bridging.pendapatan.tarik-billing-simrs'));

        $response
            ->assertOk()
            ->assertSee('Billing Pasien API')
            ->assertSee('Rawat Inap — Endpoint belum tersedia')
            ->assertSee('id="spesialisId" class="form-select select2"', false)
            ->assertSee('id="dokterId" class="form-select select2"', false)
            ->assertSee('Total data terpilih')
            ->assertSee('Invoice Pendapatan dengan tanggal pengakuan sesuai tanggal registrasi')
            ->assertSee('<th>Penjamin</th>', false)
            ->assertSee('<th class="text-center">Aksi</th>', false)
            ->assertSee('Detail Data Billing')
            ->assertSee('Rincian Akun Billing')
            ->assertSee('load-billing-account-detail')
            ->assertSee('billing-detail-button')
            ->assertDontSee('ID Billing API')
            ->assertSee('Buat Invoice Pendapatan')
            ->assertDontSee('name="jenisProses"', false)
            ->assertDontSee('name="basisTanggalPengakuan"', false)
            ->assertSee('id="importButton" disabled', false)
            ->assertSee('selectedExternalIds[]');
    }

    public function test_import_post_calls_api_invoice_service_and_not_legacy_import(): void
    {
        $legacyService = Mockery::mock(BridgingPendapatanService::class);
        $legacyService->shouldNotReceive('imporBanyak');
        $this->app->instance(BridgingPendapatanService::class, $legacyService);

        $invoiceService = Mockery::mock(BillingPendapatanInvoiceImportService::class);
        $invoiceService->shouldReceive('imporBanyak')
            ->once()
            ->with(
                ['1761891'],
                'rawat_jalan',
                '2026-08-15',
                '2026-08-15',
                '7',
                '380',
                'Tester',
            )
            ->andReturn([[
                'no_rawat' => 'RJ-001',
                'berhasil' => true,
                'alasan_gagal' => null,
            ]]);
        $this->app->instance(BillingPendapatanInvoiceImportService::class, $invoiceService);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->post(route('bridging.pendapatan.process-import'), [
                'selectedExternalIds' => ['1761891'],
                'startDate' => '2026-08-15',
                'endDate' => '2026-08-15',
                'jenisLayanan' => 'rawat_jalan',
                'spesialisId' => '7',
                'dokterId' => '380',
            ]);

        $response
            ->assertRedirect(route('bridging.pendapatan.index'))
            ->assertSessionHas('bridging_pendapatan_message', 'Proses import Invoice Pendapatan selesai.')
            ->assertSessionHas('bridging_pendapatan_results.0.no_rawat', 'RJ-001');
    }

    private function dataTableRequest(array $overrides): array
    {
        return array_replace_recursive([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'columns' => collect([
                'no_rawat',
                'tanggal_registrasi',
                'nama_pasien',
                'nama_dokter',
                'nama_poli',
                'status_lanjut',
                'penjamin',
            ])->map(fn (string $column) => [
                'data' => $column,
                'name' => $column,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ])->all(),
        ], $overrides);
    }

    private function tokenPayload(): array
    {
        return [
            'status' => true,
            'token' => 'test-token',
            'expires_in' => 3600,
        ];
    }

    private function makeUser(): User
    {
        return User::factory()->make([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
    }

    private function prepareImportedTable(): void
    {
        if (! Schema::hasTable('simrs_import_pendapatan')) {
            Schema::create('simrs_import_pendapatan', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('nomer_billing');
                $table->date('tanggal_reg')->nullable();
            });
        }

        SimrsImportPendapatan::query()->delete();
    }
}
