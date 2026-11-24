<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Pengaturan\StoreReferensiKelompokArusKasRequest;
use App\Http\Requests\Pengaturan\UpdateReferensiKelompokArusKasRequest;
use App\Models\ReferensiKelompokArusKas;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ReferensiKelompokArusKasRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('referensi_kelompok_arus_kas');

        Schema::create('referensi_kelompok_arus_kas', function (Blueprint $table) {
            $table->id();
            $table->string('aktivitas', 50);
            $table->string('nama', 150);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function test_store_request_passes_valid_data_and_sanitizes_nama(): void
    {
        $request = new StoreReferensiKelompokArusKasRequest;
        $this->assertTrue($request->authorize());

        $data = [
            'aktivitas' => 'operasi',
            'nama' => '  Pembayaran  Pajak  ',
        ];

        $request->merge($data);
        $request->setContainer(app());

        // Test prepareForValidation via reflection or direct simulation
        $reflection = new \ReflectionMethod($request, 'prepareForValidation');
        $reflection->setAccessible(true);
        $reflection->invoke($request);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        foreach ($request->after() as $after) {
            $validator->after($after);
        }

        $this->assertFalse($validator->fails());
        $this->assertEquals('Pembayaran Pajak', $request->input('nama'));
    }

    public function test_store_request_fails_on_duplicate_case_insensitive_name_same_aktivitas(): void
    {
        ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Pembayaran Pajak',
            'status_aktif' => true,
        ]);

        $request = new StoreReferensiKelompokArusKasRequest;
        $data = [
            'aktivitas' => 'operasi',
            'nama' => '  pembayaran   pajak  ',
        ];
        $request->merge($data);
        $request->setContainer(app());

        $reflection = new \ReflectionMethod($request, 'prepareForValidation');
        $reflection->setAccessible(true);
        $reflection->invoke($request);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        foreach ($request->after() as $after) {
            $validator->after($after);
        }

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('nama'));
    }

    public function test_store_request_allows_same_name_in_different_aktivitas(): void
    {
        ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Pembayaran Lainnya',
            'status_aktif' => true,
        ]);

        $request = new StoreReferensiKelompokArusKasRequest;
        $data = [
            'aktivitas' => 'pendanaan',
            'nama' => 'Pembayaran Lainnya',
        ];
        $request->merge($data);
        $request->setContainer(app());

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        foreach ($request->after() as $after) {
            $validator->after($after);
        }

        $this->assertFalse($validator->fails());
    }

    public function test_store_request_fails_when_aktivitas_invalid_or_nama_missing(): void
    {
        $request = new StoreReferensiKelompokArusKasRequest;
        $data = [
            'aktivitas' => 'non_existent',
            'nama' => '',
        ];
        $request->merge($data);
        $request->setContainer(app());

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('aktivitas'));
        $this->assertTrue($validator->errors()->has('nama'));
    }

    public function test_update_request_allows_keeping_same_name_for_current_model(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Beban Sewa',
            'status_aktif' => true,
        ]);

        $request = new UpdateReferensiKelompokArusKasRequest;
        $this->assertTrue($request->authorize());

        $data = [
            'nama' => 'Beban Sewa',
            'status_aktif' => false,
        ];
        $request->merge($data);
        $request->setContainer(app());

        // Bind route parameter
        $route = Route::get('/dummy/{referensi}', fn () => null)->bind(request());
        $request->setRouteResolver(function () use ($ref) {
            $route = new \Illuminate\Routing\Route('PUT', '/pengaturan/referensi-kelompok-arus-kas/{referensi}', []);
            $route->parameters = ['referensi' => $ref];

            return $route;
        });

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        foreach ($request->after() as $after) {
            $validator->after($after);
        }

        $this->assertFalse($validator->fails());
    }

    public function test_update_request_fails_when_duplicate_with_another_record(): void
    {
        $ref1 = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Beban Listrik',
            'status_aktif' => true,
        ]);

        $ref2 = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Beban Air',
            'status_aktif' => true,
        ]);

        $request = new UpdateReferensiKelompokArusKasRequest;
        $data = [
            'nama' => 'beban listrik',
            'status_aktif' => true,
        ];
        $request->merge($data);
        $request->setContainer(app());

        $request->setRouteResolver(function () use ($ref2) {
            $route = new \Illuminate\Routing\Route('PUT', '/pengaturan/referensi-kelompok-arus-kas/{referensi}', []);
            $route->parameters = ['referensi' => $ref2];

            return $route;
        });

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        foreach ($request->after() as $after) {
            $validator->after($after);
        }

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('nama'));
    }
}
