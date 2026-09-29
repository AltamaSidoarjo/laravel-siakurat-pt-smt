<?php

namespace Tests\Unit;

use App\Exceptions\BillingApiException;
use App\Services\Bridging\BillingApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'services.billing_api.base_url' => 'https://billing.test',
            'services.billing_api.username' => 'api-user',
            'services.billing_api.password' => 'api-password',
            'services.billing_api.connect_timeout' => 1,
            'services.billing_api.timeout' => 2,
        ]);

        Cache::flush();
    }

    public function test_login_tokens_are_cached_and_reference_endpoint_uses_bearer_token(): void
    {
        Http::fake([
            'https://billing.test/api/auth/login' => Http::response($this->loginPayload()),
            'https://billing.test/referensi/dokter' => Http::response($this->dataPayload([
                ['id' => 'doctor-1', 'nama' => 'Dokter API'],
            ])),
        ]);

        $client = app(BillingApiClient::class);

        $this->assertCount(1, $client->getDokter());
        $this->assertCount(1, $client->getDokter());

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://billing.test/api/auth/login'
            && $request->method() === 'POST'
            && $request['username'] === 'api-user'
            && $request['password'] === 'api-password');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://billing.test/referensi/dokter'
            && $request->hasHeader('Authorization', 'Bearer access-token'));
    }

    public function test_client_refreshes_access_token_once_after_unauthorized_response(): void
    {
        $dataCalls = 0;

        Http::fake(function (Request $request) use (&$dataCalls) {
            if (str_ends_with($request->url(), '/api/auth/login')) {
                return Http::response($this->loginPayload('expired-token'));
            }
            if (str_ends_with($request->url(), '/api/auth/refresh')) {
                return Http::response($this->refreshPayload('fresh-token'));
            }

            $dataCalls++;

            return $request->hasHeader('Authorization', 'Bearer expired-token')
                ? Http::response(['code' => 401, 'message' => 'Token kedaluwarsa'], 401)
                : Http::response($this->visitPayload([]));
        });

        app(BillingApiClient::class)->getIgd('2026-08-18', '2026-08-18');

        $this->assertSame(2, $dataCalls);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/auth/refresh')
            && $request['refreshToken'] === 'refresh-token');
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/kunjungan/igd/')
            && $request->hasHeader('Authorization', 'Bearer fresh-token'));
    }

    public function test_client_logs_in_again_when_refresh_token_is_rejected(): void
    {
        $loginCalls = 0;

        Http::fake(function (Request $request) use (&$loginCalls) {
            if (str_ends_with($request->url(), '/api/auth/login')) {
                $loginCalls++;
                return Http::response($this->loginPayload($loginCalls === 1 ? 'expired-token' : 'new-token'));
            }
            if (str_ends_with($request->url(), '/api/auth/refresh')) {
                return Http::response(['code' => 401, 'message' => 'Refresh token tidak valid'], 401);
            }

            return $request->hasHeader('Authorization', 'Bearer expired-token')
                ? Http::response(['code' => 401, 'message' => 'Token kedaluwarsa'], 401)
                : Http::response($this->visitPayload([]));
        });

        app(BillingApiClient::class)->getRawatJalan('2026-08-18', '2026-08-18');

        $this->assertSame(2, $loginCalls);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/kunjungan/rawatjalan/')
            && $request->hasHeader('Authorization', 'Bearer new-token'));
    }

    public function test_visit_endpoint_uses_date_path_and_collects_all_pages(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/auth/login')) {
                return Http::response($this->loginPayload());
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);

            return Http::response($this->visitPayload(
                [['id' => "visit-{$page}"]],
                page: $page,
                totalPage: 2,
            ));
        });

        $rows = app(BillingApiClient::class)->getRawatJalan('2026-08-15', '2026-08-16');

        $this->assertSame([['id' => 'visit-1'], ['id' => 'visit-2']], $rows);
        Http::assertSent(fn (Request $request) => $request->url()
            === 'https://billing.test/kunjungan/rawatjalan/2026-08-15/2026-08-16?page=2');
    }

    public function test_today_is_rejected_before_request_to_external_api(): void
    {
        $today = now('Asia/Jakarta')->format('Y-m-d');

        Http::fake();

        $this->expectException(BillingApiException::class);
        $this->expectExceptionMessage('Tanggal akhir tidak boleh lebih dari kemarin');

        app(BillingApiClient::class)->getIgd($today, $today);
    }

    public function test_invalid_or_unsupported_requests_fail_before_http_call(): void
    {
        Http::fake();
        $client = app(BillingApiClient::class);

        foreach ([
            fn () => $client->getIgd('2026-08-01', '2026-09-01'),
            fn () => $client->getRawatInap('2026-08-18', '2026-08-18'),
            fn () => $client->getAkun('registration-1'),
        ] as $callback) {
            try {
                $callback();
                $this->fail('BillingApiException was not thrown.');
            } catch (BillingApiException) {
                // Expected.
            }
        }

        Http::assertNothingSent();
    }

    public function test_rate_limit_uses_retry_after_without_exposing_response_body(): void
    {
        Http::fake([
            'https://billing.test/api/auth/login' => Http::response($this->loginPayload()),
            'https://billing.test/referensi/penjamin' => Http::response(
                ['code' => 429, 'message' => 'private upstream detail'],
                429,
                ['Retry-After' => '47'],
            ),
        ]);

        $this->expectException(BillingApiException::class);
        $this->expectExceptionMessage('Batas request Billing API tercapai. Coba lagi dalam 47 detik.');

        app(BillingApiClient::class)->getPenjamin();
    }

    public function test_server_and_malformed_responses_return_controlled_errors(): void
    {
        Http::fake([
            'https://billing.test/api/auth/login' => Http::response($this->loginPayload()),
            'https://billing.test/referensi/poli' => Http::response('private response body', 500),
        ]);

        try {
            app(BillingApiClient::class)->getPoli();
            $this->fail('BillingApiException was not thrown.');
        } catch (BillingApiException $exception) {
            $this->assertSame('Billing API sedang tidak tersedia. Silakan coba kembali.', $exception->getMessage());
            $this->assertStringNotContainsString('private response body', $exception->getMessage());
        }
    }

    private function loginPayload(string $accessToken = 'access-token'): array
    {
        return [
            'code' => 200,
            'message' => 'OK',
            'accessToken' => $accessToken,
            'refreshToken' => 'refresh-token',
            'tokenType' => 'Bearer',
            'username' => 'api-user',
            'accessTokenExpiresInMs' => 900000,
        ];
    }

    private function refreshPayload(string $accessToken): array
    {
        return [
            'code' => 200,
            'message' => 'OK',
            'accessToken' => $accessToken,
            'tokenType' => 'Bearer',
            'username' => 'api-user',
            'accessTokenExpiresInMs' => 900000,
        ];
    }

    private function dataPayload(array $data): array
    {
        return ['code' => 200, 'message' => 'OK', 'total' => count($data), 'data' => $data];
    }

    private function visitPayload(array $data, int $page = 1, int $totalPage = 1): array
    {
        return [
            'code' => 200,
            'message' => 'OK',
            'tanggalAwal' => '2026-08-15',
            'tanggalAkhir' => '2026-08-16',
            'page' => $page,
            'perPage' => 30,
            'totalPage' => $totalPage,
            'total' => count($data),
            'data' => $data,
        ];
    }
}
