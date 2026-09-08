<?php

namespace App\Services\Bridging;

use App\Exceptions\BillingApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class BillingApiClient
{
    public function getDokter(): array
    {
        return $this->getData('/dokter');
    }

    public function getSpesialis(): array
    {
        return $this->getData('/spesialis');
    }

    public function getRawatJalan(
        string $startDate,
        string $endDate,
        ?string $spesialisId = null,
        ?string $dokterId = null,
    ): array {
        return $this->getData('/rawat-jalan', array_filter([
            'tgl_awal' => $startDate,
            'tgl_akhir' => $endDate,
            'id_spesialis' => $spesialisId,
            'dokter_id' => $dokterId,
        ], fn (mixed $value) => $value !== null && $value !== ''));
    }

    public function getIgd(string $startDate, string $endDate): array
    {
        return $this->getData('/igd', [
            'tgl_awal' => $startDate,
            'tgl_akhir' => $endDate,
        ]);
    }

    public function getAkun(string $externalId): array
    {
        return $this->getData('/akun-all', [
            'id' => $externalId,
        ]);
    }

    private function getData(string $endpoint, array $query = []): array
    {
        $response = $this->authenticatedGet($endpoint, $query);

        if (! $response->successful()) {
            throw new BillingApiException('Billing API sedang tidak tersedia. Silakan coba kembali.');
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['status'] ?? false) !== true || ! is_array($payload['data'] ?? null)) {
            throw new BillingApiException('Respons Billing API tidak sesuai format yang diharapkan.');
        }

        return $payload['data'];
    }

    private function authenticatedGet(string $endpoint, array $query): Response
    {
        try {
            $response = $this->request()
                ->withToken($this->getToken())
                ->get($endpoint, $query);

            if ($response->status() !== 401) {
                return $response;
            }

            Cache::forget($this->tokenCacheKey());

            return $this->request()
                ->withToken($this->getToken())
                ->get($endpoint, $query);
        } catch (ConnectionException) {
            throw new BillingApiException('Tidak dapat terhubung ke Billing API. Silakan coba kembali.');
        }
    }

    private function getToken(): string
    {
        $cachedToken = Cache::get($this->tokenCacheKey());

        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $username = (string) config('services.billing_api.username');
        $password = (string) config('services.billing_api.password');

        if ($username === '' || $password === '') {
            throw new BillingApiException('Kredensial Billing API belum dikonfigurasi.');
        }

        try {
            $response = $this->request()->post('/get-token', [
                'username' => $username,
                'password' => $password,
            ]);
        } catch (ConnectionException) {
            throw new BillingApiException('Tidak dapat terhubung ke Billing API. Silakan coba kembali.');
        }

        $payload = $response->json();
        $token = is_array($payload) ? ($payload['token'] ?? null) : null;
        $expiresIn = is_array($payload) ? ($payload['expires_in'] ?? null) : null;

        if (! $response->successful()
            || ($payload['status'] ?? false) !== true
            || ! is_string($token)
            || $token === ''
            || ! is_numeric($expiresIn)) {
            throw new BillingApiException('Autentikasi ke Billing API gagal.');
        }

        Cache::put(
            $this->tokenCacheKey(),
            $token,
            now()->addSeconds(max(1, (int) $expiresIn - 60)),
        );

        return $token;
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

    private function tokenCacheKey(): string
    {
        return 'billing-api:token:'.sha1(implode('|', [
            (string) config('services.billing_api.base_url'),
            (string) config('services.billing_api.username'),
        ]));
    }
}
