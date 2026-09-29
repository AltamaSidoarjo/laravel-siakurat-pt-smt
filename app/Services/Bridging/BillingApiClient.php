<?php

namespace App\Services\Bridging;

use App\Exceptions\BillingApiException;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BillingApiClient
{
    public function getDokter(): array
    {
        return $this->getData('/referensi/dokter');
    }

    public function getPoli(): array
    {
        return $this->getData('/referensi/poli');
    }

    public function getPenjamin(): array
    {
        return $this->getData('/referensi/penjamin');
    }

    /** @deprecated Use getPoli(). */
    public function getSpesialis(): array
    {
        return $this->getPoli();
    }

    public function getRawatJalan(string $startDate, string $endDate): array
    {
        return $this->getVisits('/kunjungan/rawatjalan', $startDate, $endDate);
    }

    public function getIgd(string $startDate, string $endDate): array
    {
        return $this->getVisits('/kunjungan/igd', $startDate, $endDate);
    }

    public function getRawatInap(string $startDate, string $endDate): array
    {
        throw new BillingApiException('Data rawat inap belum tersedia pada Billing API baru.');
    }

    public function getAkun(string $externalId): array
    {
        throw new BillingApiException('Rincian akun belum tersedia pada Billing API baru.');
    }

    private function getData(string $endpoint): array
    {
        return $this->extractData($this->authenticatedGet($endpoint));
    }

    private function getVisits(string $endpoint, string $startDate, string $endDate): array
    {
        $this->validateDateRange($startDate, $endDate);
        $rows = [];
        $page = 1;

        do {
            $response = $this->authenticatedGet($endpoint.'/'.$startDate.'/'.$endDate, ['page' => $page]);
            $payload = $this->validatedPayload($response, true);
            $pageRows = $payload['data'];
            $rows = [...$rows, ...$pageRows];
            $totalPage = max(1, (int) ($payload['totalPage'] ?? $page));
            $page++;
        } while ($page <= $totalPage && $pageRows !== []);

        return $rows;
    }

    private function extractData(Response $response): array
    {
        return $this->validatedPayload($response)['data'];
    }

    private function validatedPayload(Response $response, bool $paginated = false): array
    {
        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After');
            throw new BillingApiException($retryAfter
                ? "Batas request Billing API tercapai. Coba lagi dalam {$retryAfter} detik."
                : 'Batas request Billing API tercapai. Silakan coba kembali.');
        }

        $payload = $response->json();
        $message = is_array($payload) && is_string($payload['message'] ?? null)
            ? $payload['message']
            : null;

        if (! $response->successful()) {
            throw new BillingApiException($message ?: 'Billing API sedang tidak tersedia. Silakan coba kembali.');
        }

        if (! is_array($payload)
            || ($payload['code'] ?? null) !== 200
            || ! is_array($payload['data'] ?? null)) {
            throw new BillingApiException($message ?: 'Respons Billing API tidak sesuai format yang diharapkan.');
        }

        if ($paginated && (! isset($payload['totalPage']) || ! is_numeric($payload['totalPage']))) {
            throw new BillingApiException('Respons pagination Billing API tidak sesuai format yang diharapkan.');
        }

        return $payload;
    }

    private function authenticatedGet(string $endpoint, array $query = []): Response
    {
        try {
            $response = $this->request()
                ->withToken($this->getAccessToken())
                ->get($endpoint, $query);

            if ($response->status() !== 401) {
                return $response;
            }

            Cache::forget($this->accessTokenCacheKey());
            $accessToken = $this->refreshAccessToken() ?? $this->login();

            return $this->request()->withToken($accessToken)->get($endpoint, $query);
        } catch (ConnectionException $exception) {
            Log::error('Billing API connection failed.', [
                'endpoint' => $endpoint,
                'message' => $exception->getMessage(),
            ]);

            throw new BillingApiException('Tidak dapat terhubung ke Billing API. Silakan coba kembali.');
        }
    }

    private function getAccessToken(): string
    {
        $token = Cache::get($this->accessTokenCacheKey());

        return is_string($token) && $token !== '' ? $token : $this->refreshAccessToken() ?? $this->login();
    }

    private function login(): string
    {
        $username = (string) config('services.billing_api.username');
        $password = (string) config('services.billing_api.password');

        if ($username === '' || $password === '') {
            throw new BillingApiException('Kredensial Billing API belum dikonfigurasi.');
        }

        try {
            $response = $this->request()->post('/api/auth/login', compact('username', 'password'));
        } catch (ConnectionException $exception) {
            Log::error('Billing API login connection failed.', ['message' => $exception->getMessage()]);
            throw new BillingApiException('Tidak dapat terhubung ke Billing API. Silakan coba kembali.');
        }

        $payload = $response->json();
        $accessToken = is_array($payload) ? ($payload['accessToken'] ?? null) : null;
        $refreshToken = is_array($payload) ? ($payload['refreshToken'] ?? null) : null;
        $expiresInMs = is_array($payload) ? ($payload['accessTokenExpiresInMs'] ?? null) : null;

        if (! $response->successful()
            || ($payload['code'] ?? null) !== 200
            || ! is_string($accessToken) || $accessToken === ''
            || ! is_string($refreshToken) || $refreshToken === ''
            || ! is_numeric($expiresInMs)) {
            throw new BillingApiException(is_array($payload) && is_string($payload['message'] ?? null)
                ? $payload['message']
                : 'Autentikasi ke Billing API gagal.');
        }

        $this->cacheTokens($accessToken, $refreshToken, (int) $expiresInMs);

        return $accessToken;
    }

    private function refreshAccessToken(): ?string
    {
        $refreshToken = Cache::get($this->refreshTokenCacheKey());

        if (! is_string($refreshToken) || $refreshToken === '') {
            return null;
        }

        try {
            $response = $this->request()->post('/api/auth/refresh', ['refreshToken' => $refreshToken]);
        } catch (ConnectionException $exception) {
            Log::error('Billing API refresh connection failed.', ['message' => $exception->getMessage()]);
            return null;
        }

        $payload = $response->json();
        $accessToken = is_array($payload) ? ($payload['accessToken'] ?? null) : null;
        $expiresInMs = is_array($payload) ? ($payload['accessTokenExpiresInMs'] ?? null) : null;

        if (! $response->successful()
            || ($payload['code'] ?? null) !== 200
            || ! is_string($accessToken) || $accessToken === ''
            || ! is_numeric($expiresInMs)) {
            Cache::forget($this->accessTokenCacheKey());
            Cache::forget($this->refreshTokenCacheKey());
            return null;
        }

        Cache::put($this->accessTokenCacheKey(), $accessToken, $this->tokenTtl((int) $expiresInMs));

        return $accessToken;
    }

    private function cacheTokens(string $accessToken, string $refreshToken, int $expiresInMs): void
    {
        Cache::put($this->accessTokenCacheKey(), $accessToken, $this->tokenTtl($expiresInMs));
        Cache::put($this->refreshTokenCacheKey(), $refreshToken, now()->addDays(7));
    }

    private function tokenTtl(int $expiresInMs): DateTimeInterface
    {
        return now()->addSeconds(max(1, intdiv($expiresInMs, 1000) - 60));
    }

    private function validateDateRange(string $startDate, string $endDate): void
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $endDate);
        $yesterday = CarbonImmutable::now('Asia/Jakarta')->startOfDay()->subDay();

        if (! $start || $start->format('Y-m-d') !== $startDate || ! $end || $end->format('Y-m-d') !== $endDate) {
            throw new BillingApiException('Tanggal awal atau tanggal akhir tidak valid.');
        }
        if ($start->gt($end)) {
            throw new BillingApiException('Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
        }
        if ($start->diffInDays($end) + 1 > 30) {
            throw new BillingApiException('Tanggal awal dan tanggal akhir tidak boleh lebih dari 30 hari.');
        }
        if ($end->gt($yesterday)) {
            throw new BillingApiException('Tanggal awal dan tanggal akhir tidak boleh lebih dari tanggal sekarang.');
        }
    }

    private function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.billing_api.base_url'), '/');

        if ($baseUrl === '') {
            throw new BillingApiException('URL Billing API belum dikonfigurasi.');
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.billing_api.connect_timeout', 5))
            ->timeout((int) config('services.billing_api.timeout', 30))
            ->retry(
                2,
                200,
                fn (Throwable $exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
                throw: false,
            );
    }

    private function accessTokenCacheKey(): string
    {
        return 'billing-api:access-token:'.sha1($this->cacheKeySeed());
    }

    private function refreshTokenCacheKey(): string
    {
        return 'billing-api:refresh-token:'.sha1($this->cacheKeySeed());
    }

    private function cacheKeySeed(): string
    {
        return implode('|', [
            (string) config('services.billing_api.base_url'),
            (string) config('services.billing_api.username'),
        ]);
    }
}
