<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Pengaturan\QuickMapReferensiKelompokArusKasRequest;
use App\Http\Requests\Pengaturan\StoreReferensiKelompokArusKasRequest;
use App\Http\Requests\Pengaturan\UpdateReferensiKelompokArusKasRequest;
use App\Models\Coa;
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
        Schema::dropIfExists('coa');

        Schema::create('referensi_kelompok_arus_kas', function (Blueprint $table) {
            $table->id();
            $table->string('aktivitas', 50);
            $table->string('nama', 150);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('coa', function (Blueprint $table) {
            $table->id();
            $table->integer('status_aktif')->default(1);
            $table->unsignedBigInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('arus_kas_aktivitas', 50)->nullable();
            $table->string('arus_kas_kelompok', 150)->nullable();
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
            $route = new \Illuminate\Routing\Route('PUT', '/pengaturan/arus-kas/langsung/{referensi}', []);
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
            $route = new \Illuminate\Routing\Route('PUT', '/pengaturan/arus-kas/langsung/{referensi}', []);
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

    public function test_store_and_update_requests_normalize_empty_sentinel_and_validate_coa_ids(): void
    {
        $kasbankCoa = Coa::create([
            'kode' => '111.01',
            'nama' => 'Kas Kecil',
            'tipe_coa' => 'Kasbank',
            'status_aktif' => 1,
        ]);

        $parentCoa = Coa::create([
            'kode' => '500',
            'nama' => 'Beban Operasional',
            'tipe_coa' => 'Beban',
            'status_aktif' => 1,
        ]);

        $leafCoaFromOtherGroup = Coa::create([
            'parent_coa' => $parentCoa->id,
            'kode' => '500.01',
            'nama' => 'Beban ATK',
            'tipe_coa' => 'Beban',
            'status_aktif' => 1,
            'arus_kas_aktivitas' => 'operasi',
            'arus_kas_kelompok' => 'Kelompok Lain',
        ]);

        // 1. Store request with empty string sentinel 'coa_ids' => ''
        $storeReq = new StoreReferensiKelompokArusKasRequest;
        $storeReq->merge([
            'aktivitas' => 'operasi',
            'nama' => 'Pembayaran Beban ATK',
            'coa_ids' => '',
        ]);
        $storeReq->setContainer(app());

        $reflection = new \ReflectionMethod($storeReq, 'prepareForValidation');
        $reflection->setAccessible(true);
        $reflection->invoke($storeReq);

        $validator = Validator::make($storeReq->all(), $storeReq->rules(), $storeReq->messages());
        foreach ($storeReq->after() as $after) {
            $validator->after($after);
        }
        $this->assertFalse($validator->fails());
        $this->assertSame([], $storeReq->input('coa_ids'));

        // 2. Store request with valid leaf COA from another group (reassignment allowed)
        $storeReq2 = new StoreReferensiKelompokArusKasRequest;
        $storeReq2->merge([
            'aktivitas' => 'operasi',
            'nama' => 'Kelompok Baru',
            'coa_ids' => [$leafCoaFromOtherGroup->id],
        ]);
        $storeReq2->setContainer(app());
        $reflection->invoke($storeReq2);

        $validator2 = Validator::make($storeReq2->all(), $storeReq2->rules(), $storeReq2->messages());
        foreach ($storeReq2->after() as $after) {
            $validator2->after($after);
        }
        $this->assertFalse($validator2->fails());

        // 3. Store request with parent COA fails
        $storeReq3 = new StoreReferensiKelompokArusKasRequest;
        $storeReq3->merge([
            'aktivitas' => 'operasi',
            'nama' => 'Kelompok Parent',
            'coa_ids' => [$parentCoa->id],
        ]);
        $storeReq3->setContainer(app());
        $reflection->invoke($storeReq3);

        $validator3 = Validator::make($storeReq3->all(), $storeReq3->rules(), $storeReq3->messages());
        foreach ($storeReq3->after() as $after) {
            $validator3->after($after);
        }
        $this->assertTrue($validator3->fails());
        $this->assertTrue($validator3->errors()->has('coa_ids.0'));

        // 4. Store request with Kasbank COA fails
        $storeReq4 = new StoreReferensiKelompokArusKasRequest;
        $storeReq4->merge([
            'aktivitas' => 'operasi',
            'nama' => 'Kelompok Kasbank',
            'coa_ids' => [$kasbankCoa->id],
        ]);
        $storeReq4->setContainer(app());
        $reflection->invoke($storeReq4);

        $validator4 = Validator::make($storeReq4->all(), $storeReq4->rules(), $storeReq4->messages());
        foreach ($storeReq4->after() as $after) {
            $validator4->after($after);
        }
        $this->assertTrue($validator4->fails());
        $this->assertTrue($validator4->errors()->has('coa_ids.0'));

        // 5. Update request omitting coa_ids passes without forcing coa_ids key
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Kelompok Edit',
            'status_aktif' => true,
        ]);

        $updateReq = new UpdateReferensiKelompokArusKasRequest;
        $updateReq->merge([
            'nama' => 'Kelompok Edit Baru',
            'status_aktif' => true,
        ]);
        $updateReq->setContainer(app());

        $updateReq->setRouteResolver(function () use ($ref) {
            $route = new \Illuminate\Routing\Route('PUT', '/pengaturan/arus-kas/langsung/{referensi}', []);
            $route->parameters = ['referensi' => $ref];

            return $route;
        });

        $reflectionUpdate = new \ReflectionMethod($updateReq, 'prepareForValidation');
        $reflectionUpdate->setAccessible(true);
        $reflectionUpdate->invoke($updateReq);

        $validator5 = Validator::make($updateReq->all(), $updateReq->rules(), $updateReq->messages());
        foreach ($updateReq->after() as $after) {
            $validator5->after($after);
        }
        $this->assertFalse($validator5->fails());
        $this->assertFalse($updateReq->has('coa_ids'));

        // 6. Update request with empty sentinel 'coa_ids' => '' normalizes to []
        $updateReq2 = new UpdateReferensiKelompokArusKasRequest;
        $updateReq2->merge([
            'nama' => 'Kelompok Edit Kosong',
            'status_aktif' => true,
            'coa_ids' => '',
        ]);
        $updateReq2->setContainer(app());
        $updateReq2->setRouteResolver(function () use ($ref) {
            $route = new \Illuminate\Routing\Route('PUT', '/pengaturan/arus-kas/langsung/{referensi}', []);
            $route->parameters = ['referensi' => $ref];

            return $route;
        });
        $reflectionUpdate->invoke($updateReq2);

        $validator6 = Validator::make($updateReq2->all(), $updateReq2->rules(), $updateReq2->messages());
        foreach ($updateReq2->after() as $after) {
            $validator6->after($after);
        }
        $this->assertFalse($validator6->fails());
        $this->assertSame([], $updateReq2->input('coa_ids'));
    }

    public function test_quick_map_request_validates_referensi_and_leaf_non_kasbank_coa(): void
    {
        $ref = ReferensiKelompokArusKas::create([
            'aktivitas' => 'operasi',
            'nama' => 'Kelompok Target',
            'status_aktif' => true,
        ]);

        $parentCoa = Coa::create([
            'kode' => '600',
            'nama' => 'Beban Lainnya',
            'tipe_coa' => 'Beban',
            'status_aktif' => 1,
        ]);

        $leafCoa = Coa::create([
            'parent_coa' => $parentCoa->id,
            'kode' => '600.01',
            'nama' => 'Beban Bunga',
            'tipe_coa' => 'Beban',
            'status_aktif' => 1,
        ]);

        $kasbankCoa = Coa::create([
            'kode' => '111.02',
            'nama' => 'Bank Mandiri',
            'tipe_coa' => 'Kasbank',
            'status_aktif' => 1,
        ]);

        // 1. Valid leaf and referensi passes
        $req1 = new QuickMapReferensiKelompokArusKasRequest;
        $this->assertTrue($req1->authorize());
        $req1->merge([
            'coa_id' => $leafCoa->id,
            'referensi_id' => $ref->id,
        ]);
        $req1->setContainer(app());

        $validator1 = Validator::make($req1->all(), $req1->rules(), $req1->messages());
        foreach ($req1->after() as $after) {
            $validator1->after($after);
        }
        $this->assertFalse($validator1->fails());

        // 2. Parent COA fails on coa_id
        $req2 = new QuickMapReferensiKelompokArusKasRequest;
        $req2->merge([
            'coa_id' => $parentCoa->id,
            'referensi_id' => $ref->id,
        ]);
        $req2->setContainer(app());

        $validator2 = Validator::make($req2->all(), $req2->rules(), $req2->messages());
        foreach ($req2->after() as $after) {
            $validator2->after($after);
        }
        $this->assertTrue($validator2->fails());
        $this->assertTrue($validator2->errors()->has('coa_id'));

        // 3. Kasbank COA fails on coa_id
        $req3 = new QuickMapReferensiKelompokArusKasRequest;
        $req3->merge([
            'coa_id' => $kasbankCoa->id,
            'referensi_id' => $ref->id,
        ]);
        $req3->setContainer(app());

        $validator3 = Validator::make($req3->all(), $req3->rules(), $req3->messages());
        foreach ($req3->after() as $after) {
            $validator3->after($after);
        }
        $this->assertTrue($validator3->fails());
        $this->assertTrue($validator3->errors()->has('coa_id'));

        // 4. Non-existent referensi_id fails on referensi_id
        $req4 = new QuickMapReferensiKelompokArusKasRequest;
        $req4->merge([
            'coa_id' => $leafCoa->id,
            'referensi_id' => 999999,
        ]);
        $req4->setContainer(app());

        $validator4 = Validator::make($req4->all(), $req4->rules(), $req4->messages());
        foreach ($req4->after() as $after) {
            $validator4->after($after);
        }
        $this->assertTrue($validator4->fails());
        $this->assertTrue($validator4->errors()->has('referensi_id'));
    }
}
