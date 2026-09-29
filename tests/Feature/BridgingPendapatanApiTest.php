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
            'services.billing_api.base_url' => 'https://billing.test',
            'services.billing_api.username' => 'api-user',
            'services.billing_api.password' => 'api-password',
        ]);

        Cache::flush();
        $this->prepareImportedTable();
    }

    public function test_rawat_jalan_endpoint_receives_its_specific_filters_and_normalizes_rows(): void
    {
        Http::fake([
            'https://billing.test/api/auth/login' => Http::response($this->tokenPayload()),
            'https://billing.test/kunjungan/rawatjalan/*' => Http::response([
                'code' => 200,
                'message' => 'OK',
                'page' => 1,
                'perPage' => 30,
                'totalPage' => 1,
                'total' => 1,
                'data' => [[
                    'id' => '1761891',
                    'pxNo' => 'RJ-001',
                    'nomorRm' => '0001***',
                    'tanggal' => '2026-08-15',
                    'nama' => 'P***** A**',
                    'dokterId' => '380',
                    'dokterNama' => 'Dokter API',
                    'poliId' => '7',
                    'poliNama' => 'Spesialis API',
                    'penjaminNama' => 'BPJS',
                    'noSep' => 'SEP-001',
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
            ->assertJsonPath('data.0.nomer_rekam_medis', '0001***')
            ->assertJsonPath('data.0.tanggal_registrasi', '2026-08-15')
            ->assertJsonPath('data.0.nama_pasien', 'P***** A**')
            ->assertJsonPath('data.0.nama_dokter', 'Dokter API')
            ->assertJsonPath('data.0.nama_poli', 'Spesialis API')
            ->assertJsonPath('data.0.status_lanjut', 'Rawat Jalan')
            ->assertJsonPath('data.0.penjamin', 'BPJS');

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/kunjungan/rawatjalan/2026-08-15/2026-08-15')) {
                return false;
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query === ['page' => '1'];
        });
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/kunjungan/igd/'));
    }

    public function test_igd_endpoint_receives_visit_filters_and_handles_optional_fields(): void
    {
        Http::fake([
            'https://billing.test/api/auth/login' => Http::response($this->tokenPayload()),
            'https://billing.test/kunjungan/igd/*' => Http::response([
                'code' => 200,
                'message' => 'OK',
                'page' => 1,
                'perPage' => 30,
                'totalPage' => 1,
                'total' => 1,
                'data' => [[
                    'id' => '200',
                    'pxNo' => 'IGD-001',
                    'nomorRm' => '0002***',
                    'tanggal' => '2026-08-18',
                    'nama' => 'P***** I**',
                    'dokterId' => '380',
                    'dokterNama' => null,
                    'poliId' => '7',
                    'poliNama' => 'IGD',
                    'penjaminNama' => null,
                    'noSEP' => null,
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
            ->assertJsonPath('data.0.external_id', '200')
            ->assertJsonPath('data.0.no_rawat', 'IGD-001')
            ->assertJsonPath('data.0.nomer_rekam_medis', '0002***')
            ->assertJsonPath('data.0.nama_dokter', '')
            ->assertJsonPath('data.0.nama_poli', 'IGD')
            ->assertJsonPath('data.0.status_lanjut', 'IGD')
            ->assertJsonPath('data.0.penjamin', '');

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/kunjungan/igd/2026-08-18/2026-08-18')) {
                return false;
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query === ['page' => '1'];
        });
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/kunjungan/rawatjalan/'));
    }

    public function test_candidate_table_filters_penjamin_exactly_and_normalizes_umum(): void
    {
        Http::fake([
            'https://billing.test/api/auth/login' => Http::response($this->tokenPayload()),
            'https://billing.test/kunjungan/rawatjalan/*' => Http::response([
                'code' => 200,
                'message' => 'OK',
                'page' => 1,
                'perPage' => 30,
                'totalPage' => 1,
                'total' => 3,
                'data' => [
                    ['id' => '1', 'pxNo' => '1', 'penjaminNama' => 'Umum'],
                    ['id' => '2', 'pxNo' => '2', 'penjaminNama' => 'BPJS'],
                    ['id' => '3', 'pxNo' => '3', 'penjaminNama' => 'BPJS-COB'],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-simrs', $this->dataTableRequest([
                'startDate' => '2026-08-15',
                'endDate' => '2026-08-15',
                'jenisLayanan' => 'rawat_jalan',
                'penjamin' => 'umum',
            ])));

        $response
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.no_rawat', '1');

        $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-simrs', $this->dataTableRequest([
                'startDate' => '2026-08-15',
                'endDate' => '2026-08-15',
                'jenisLayanan' => 'rawat_jalan',
                'penjamin' => 'bpjs',
            ])))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.no_rawat', '2');
    }

    public function test_igd_candidate_table_applies_penjamin_filter(): void
    {
        Http::fake([
            'https://billing.test/api/auth/login' => Http::response($this->tokenPayload()),
            'https://billing.test/kunjungan/igd/*' => Http::response([
                'code' => 200,
                'message' => 'OK',
                'page' => 1,
                'perPage' => 30,
                'totalPage' => 1,
                'total' => 2,
                'data' => [
                    ['id' => '1', 'pxNo' => 'IGD-BPJS', 'penjaminNama' => 'BPJS'],
                    ['id' => '2', 'pxNo' => 'IGD-UMUM', 'penjaminNama' => 'Umum'],
                ],
            ]),
        ]);

        $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-simrs', $this->dataTableRequest([
                'startDate' => '2026-08-15',
                'endDate' => '2026-08-15',
                'jenisLayanan' => 'igd',
                'penjamin' => 'BPJS',
            ])))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.no_rawat', 'IGD-BPJS');
    }

    public function test_billing_account_detail_endpoint_returns_controlled_unsupported_error(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-account-detail', [
                'externalId' => '1761891',
            ]));

        $response
            ->assertStatus(502)
            ->assertJsonPath('message', 'Rincian akun belum tersedia pada Billing API baru.');

        Http::assertNothingSent();
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

    public function test_billing_account_detail_api_source_is_unavailable(): void
    {
        $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-account-detail', [
                'externalId' => 'billing-empty',
            ]))
            ->assertStatus(502)
            ->assertJsonPath('message', 'Rincian akun belum tersedia pada Billing API baru.');
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
            'nomer_billing' => '1',
            'tanggal_reg' => '2026-08-15',
        ]);
        SimrsImportPendapatan::query()->create([
            'nomer_billing' => 'NOT-IN-API',
            'tanggal_reg' => '2026-08-15',
        ]);

        Http::fake([
            'https://billing.test/api/auth/login' => Http::response($this->tokenPayload()),
            'https://billing.test/kunjungan/rawatjalan/*' => Http::response([
                'code' => 200,
                'message' => 'OK',
                'page' => 1,
                'perPage' => 30,
                'totalPage' => 1,
                'total' => 3,
                'data' => [
                    ['id' => '1', 'pxNo' => '1', 'nama' => 'Sudah Diimpor'],
                    ['id' => '2', 'pxNo' => 'RJ-AVAILABLE-1', 'nama' => 'Budi'],
                    ['id' => '3', 'pxNo' => 'RJ-AVAILABLE-2', 'nama' => 'Siti'],
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
            ->assertJsonPath('data.0.external_id', '2')
            ->assertJsonPath('data.0.no_rawat', 'RJ-AVAILABLE-1');
    }

    public function test_rawat_inap_is_rejected_because_new_api_does_not_support_it(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->getJson(route('bridging.pendapatan.load-billing-simrs', $this->dataTableRequest([
                'startDate' => '2026-08-18',
                'endDate' => '2026-08-18',
                'jenisLayanan' => 'rawat_inap',
                'spesialisId' => '7',
                'dokterId' => '380',
            ])));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['jenisLayanan']);

        Http::assertNothingSent();
    }

    public function test_pull_page_only_offers_invoice_import_and_displays_penjamin(): void
    {
        Http::fake([
            'https://billing.test/api/auth/login' => Http::response($this->tokenPayload()),
            'https://billing.test/referensi/poli' => Http::response(['code' => 200, 'message' => 'OK', 'total' => 0, 'data' => []]),
            'https://billing.test/referensi/dokter' => Http::response(['code' => 200, 'message' => 'OK', 'total' => 0, 'data' => []]),
            'https://billing.test/referensi/penjamin' => Http::response([
                'code' => 200,
                'message' => 'OK',
                'data' => [
                    ['id' => '45', 'nama' => 'ASKES/BPJS', 'tipe' => 'ASURANSI'],
                    ['id' => 'umum', 'nama' => 'UMUM', 'tipe' => 'UMUM'],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs($this->makeUser())
            ->get(route('bridging.pendapatan.tarik-billing-simrs'));

        $response
            ->assertOk()
            ->assertSee('Billing Pasien API')
            ->assertDontSee('Endpoint belum tersedia')
            ->assertSee('id="spesialisId" class="form-select select2"', false)
            ->assertSee('id="dokterId" class="form-select select2"', false)
            ->assertSee('id="penjamin" class="form-select select2"', false)
            ->assertSee('Semua penjamin')
            ->assertSee('ASKES/BPJS')
            ->assertSee('Umum')
            ->assertSee('Total data terpilih')
            ->assertSee('Invoice Pendapatan dengan tanggal pengakuan sesuai tanggal registrasi')
            ->assertSee('<th>Penjamin</th>', false)
            ->assertSee('<th class="text-center">Aksi</th>', false)
            ->assertSee('Detail Data Billing')
            ->assertSee('Rincian Akun Billing')
            ->assertSee('id="billingAccountDetailLoading"', false)
            ->assertSee('spinner-border text-success', false)
            ->assertSee('role="status" aria-live="polite"', false)
            ->assertSee('Mohon tunggu, data sedang diproses.')
            ->assertSee("billingAccountDetailTotal').textContent = 'Rp 0'", false)
            ->assertSee('load-billing-account-detail')
            ->assertSee('billing-detail-button')
            ->assertDontSee('ID Billing API')
            ->assertSee('Buat Invoice Pendapatan')
            ->assertDontSee('name="jenisProses"', false)
            ->assertDontSee('name="basisTanggalPengakuan"', false)
            ->assertSee('id="importButton" disabled', false)
            ->assertSee('selectedExternalIds[]');
    }

    public function test_igd_pull_page_loads_and_displays_visit_filters(): void
    {
        Http::fake([
            'https://billing.test/api/auth/login' => Http::response($this->tokenPayload()),
            'https://billing.test/referensi/penjamin' => Http::response(['code' => 200, 'message' => 'OK', 'total' => 0, 'data' => []]),
            'https://billing.test/referensi/poli' => Http::response([
                'code' => 200,
                'message' => 'OK',
                'data' => [['id' => '7', 'nama' => 'Spesialis IGD']],
            ]),
            'https://billing.test/referensi/dokter' => Http::response([
                'code' => 200,
                'message' => 'OK',
                'data' => [['id' => '380', 'nama' => 'Dokter IGD']],
            ]),
        ]);

        $this
            ->actingAs($this->makeUser())
            ->get(route('bridging.pendapatan.tarik-billing-simrs', [
                'jenisLayanan' => 'igd',
                'spesialisId' => '7',
                'dokterId' => '380',
            ]))
            ->assertOk()
            ->assertSee('Spesialis IGD')
            ->assertSee('Dokter IGD')
            ->assertSee("['rawat_jalan', 'igd'].includes", false)
            ->assertDontSee('rawat-jalan-filter');
    }

    public function test_pull_page_remains_available_when_penjamin_options_fail(): void
    {
        Http::fake([
            'https://billing.test/api/auth/login' => Http::response($this->tokenPayload()),
            'https://billing.test/referensi/penjamin' => Http::response(['code' => 503, 'message' => 'Billing API sedang tidak tersedia.'], 503),
            'https://billing.test/referensi/poli' => Http::response(['code' => 200, 'message' => 'OK', 'data' => []]),
            'https://billing.test/referensi/dokter' => Http::response(['code' => 200, 'message' => 'OK', 'data' => []]),
        ]);

        $this
            ->actingAs($this->makeUser())
            ->get(route('bridging.pendapatan.tarik-billing-simrs'))
            ->assertOk()
            ->assertSee('Billing API sedang tidak tersedia.')
            ->assertSee('Semua penjamin');
    }

    public function test_failed_import_redirect_preserves_penjamin_filter(): void
    {
        $invoiceService = Mockery::mock(BillingPendapatanInvoiceImportService::class);
        $invoiceService->shouldReceive('imporBanyak')
            ->once()
            ->with(
                ['1761891'],
                'igd',
                '2026-08-15',
                '2026-08-15',
                null,
                null,
                'BPJS',
                'Tester',
            )
            ->andThrow(new BillingApiException('Billing API sedang tidak tersedia. Silakan coba kembali.'));
        $this->app->instance(BillingPendapatanInvoiceImportService::class, $invoiceService);

        $response = $this
            ->withoutMiddleware(EnsureModuleAccess::class)
            ->actingAs($this->makeUser())
            ->post(route('bridging.pendapatan.process-import'), [
                'selectedExternalIds' => ['1761891'],
                'startDate' => '2026-08-15',
                'endDate' => '2026-08-15',
                'jenisLayanan' => 'igd',
                'penjamin' => 'BPJS',
            ]);

        $response
            ->assertRedirect(route('bridging.pendapatan.tarik-billing-simrs', [
                'startDate' => '2026-08-15',
                'endDate' => '2026-08-15',
                'jenisLayanan' => 'igd',
                'penjamin' => 'BPJS',
            ]))
            ->assertSessionHas('error', 'Billing API sedang tidak tersedia. Silakan coba kembali.');
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
                'BPJS',
                'Tester',
            )
            ->andReturn([[
                'no_rawat' => '1761891',
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
                'penjamin' => 'BPJS',
            ]);

        $response
            ->assertRedirect(route('bridging.pendapatan.index'))
            ->assertSessionHas('bridging_pendapatan_message', 'Proses import Invoice Pendapatan selesai.')
            ->assertSessionHas('bridging_pendapatan_results.0.no_rawat', '1761891');
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
            'code' => 200,
            'message' => 'OK',
            'accessToken' => 'test-token',
            'refreshToken' => 'test-refresh-token',
            'tokenType' => 'Bearer',
            'username' => 'api-user',
            'accessTokenExpiresInMs' => 900000,
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
