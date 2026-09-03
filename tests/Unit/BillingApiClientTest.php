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
            'services.billing_api.base_url' => 'http://billing.test/api',
            'services.billing_api.username' => 'api-user',
            'services.billing_api.password' => 'api-password',
            'services.billing_api.connect_timeout' => 1,
            'services.billing_api.timeout' => 2,
        ]);

        Cache::flush();
    }

    public function test_token_is_cached_and_rawat_jalan_filters_are_forwarded(): void
    {
        Http::fake([
            'http://billing.test/api/get-token' => Http::response($this->tokenPayload()),
            'http://billing.test/api/rawat-jalan*' => Http::response([
                'status' => true,
                'data' => [],
            ]),
        ]);

        $client = app(BillingApiClient::class);

        $client->getRawatJalan('2026-08-15', '2026-08-16', '7', '380');
        $client->getRawatJalan('2026-08-15', '2026-08-16', '7', '380');

        Http::assertSentCount(3);
        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/rawat-jalan')) {
                return false;
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query === [
                'tgl_awal' => '2026-08-15',
                'tgl_akhir' => '2026-08-16',
                'id_spesialis' => '7',
                'dokter_id' => '380',
            ] && $request->hasHeader('Authorization', 'Bearer first-token');
        });
    }

    public function test_client_refreshes_token_once_after_unauthorized_response(): void
    {
        $tokenCalls = 0;

        Http::fake(function (Request $request) use (&$tokenCalls) {
            if (str_ends_with($request->url(), '/get-token')) {
                $tokenCalls++;

                return Http::response($this->tokenPayload($tokenCalls === 1 ? 'expired-token' : 'fresh-token'));
            }

            if ($request->hasHeader('Authorization', 'Bearer expired-token')) {
                return Http::response([], 401);
            }

            return Http::response(['status' => true, 'data' => []]);
        });

        app(BillingApiClient::class)->getIgd('2026-08-18', '2026-08-18');

        $this->assertSame(2, $tokenCalls);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/igd')
            && $request->hasHeader('Authorization', 'Bearer fresh-token'));
    }

    public function test_client_rejects_non_json_response_without_exposing_body(): void
    {
        Http::fake([
            'http://billing.test/api/get-token' => Http::response($this->tokenPayload()),
            'http://billing.test/api/igd*' => Http::response('private response body'),
        ]);

        try {
            app(BillingApiClient::class)->getIgd('2026-08-18', '2026-08-18');
            $this->fail('BillingApiException was not thrown.');
        } catch (BillingApiException $exception) {
            $this->assertStringNotContainsString('private response body', $exception->getMessage());
            $this->assertSame('Respons Billing API tidak sesuai format yang diharapkan.', $exception->getMessage());
        }
    }

    public function test_client_rejects_unsuccessful_api_status(): void
    {
        Http::fake([
            'http://billing.test/api/get-token' => Http::response($this->tokenPayload()),
            'http://billing.test/api/igd*' => Http::response([
                'status' => false,
                'message' => 'private upstream message',
                'data' => [],
            ]),
        ]);

        $this->expectException(BillingApiException::class);
        $this->expectExceptionMessage('Respons Billing API tidak sesuai format yang diharapkan.');

        app(BillingApiClient::class)->getIgd('2026-08-18', '2026-08-18');
    }

    public function test_http_error_returns_safe_message(): void
    {
        Http::fake([
            'http://billing.test/api/get-token' => Http::response($this->tokenPayload()),
            'http://billing.test/api/igd*' => Http::response('private server error', 500),
        ]);

        try {
            app(BillingApiClient::class)->getIgd('2026-08-18', '2026-08-18');
            $this->fail('BillingApiException was not thrown.');
        } catch (BillingApiException $exception) {
            $this->assertSame('Billing API sedang tidak tersedia. Silakan coba kembali.', $exception->getMessage());
            $this->assertStringNotContainsString('private server error', $exception->getMessage());
        }
    }

    public function test_connection_failure_returns_safe_message(): void
    {
        Http::fake([
            'http://billing.test/api/get-token' => Http::response($this->tokenPayload()),
            'http://billing.test/api/igd*' => Http::failedConnection(),
        ]);

        $this->expectException(BillingApiException::class);
        $this->expectExceptionMessage('Tidak dapat terhubung ke Billing API. Silakan coba kembali.');

        app(BillingApiClient::class)->getIgd('2026-08-18', '2026-08-18');
    }

    private function tokenPayload(string $token = 'first-token'): array
    {
        return [
            'status' => true,
            'token_type' => 'Bearer',
            'token' => $token,
            'expires_in' => 3600,
        ];
    }
}
