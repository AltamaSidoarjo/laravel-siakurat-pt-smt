<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Pengaturan\ReorderSettingArusKasRequest;
use App\Http\Requests\Pengaturan\StorePengecualianArusKasRequest;
use App\Http\Requests\Pengaturan\StoreSettingArusKasRequest;
use App\Http\Requests\Pengaturan\UpdateSettingArusKasRequest;
use App\Models\Coa;
use App\Models\SettingArusKas;
use App\Models\SettingArusKasCoa;
use App\Models\SettingArusKasPengecualian;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;
use Tests\TestCase;

class PengaturanArusKasRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'setting_arus_kas_pengecualian',
            'setting_arus_kas_coa',
            'setting_arus_kas',
            'coa',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('coa', function (Blueprint $table): void {
            $table->id();
            $table->integer('status_aktif')->default(1);
            $table->unsignedBigInteger('parent_coa')->nullable();
            $table->string('tipe_coa')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->timestamps();
        });

        (require database_path('migrations/2026_11_25_000001_create_setting_arus_kas_tables.php'))->up();
    }

    public function test_store_setting_arus_kas_request_accepts_valid_payload_and_normalizes_inputs(): void
    {
        $neracaAktif = Coa::create(['kode' => '112.01', 'nama' => 'Piutang BPJS', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $neracaNonaktif = Coa::create(['kode' => '112.02', 'nama' => 'Piutang Lama', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 0]);

        $requestWithKode = new StoreSettingArusKasRequest;
        $this->assertTrue($requestWithKode->authorize());

        $validator = $this->validateFormRequest($requestWithKode, [
            'kode_pos' => '  MK_PIUTANG_CUSTOM  ',
            'nama_pos' => '  Piutang   Usaha  ',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => '1',
            'urutan' => 1,
            'is_active' => '0',
            'coa_ids' => [$neracaAktif->id, $neracaNonaktif->id],
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->all()));
        $this->assertSame('MK_PIUTANG_CUSTOM', $requestWithKode->input('kode_pos'));
        $this->assertSame('Piutang Usaha', $requestWithKode->input('nama_pos'));
        $this->assertTrue($requestWithKode->input('pakai_prefix_perubahan'));
        $this->assertFalse($requestWithKode->input('is_active'));

        // Without kode_pos (omitted or whitespace-only) should pass and normalize blank kode_pos to null
        $requestWithoutKode = new StoreSettingArusKasRequest;
        $validatorNoKode = $this->validateFormRequest($requestWithoutKode, [
            'kode_pos' => '   ',
            'nama_pos' => 'Persediaan',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 2,
        ]);

        $this->assertFalse($validatorNoKode->fails(), json_encode($validatorNoKode->errors()->all()));
        $this->assertNull($requestWithoutKode->input('kode_pos'));

        // Empty-string kode_pos on another row must not collide with null kode_pos uniqueness
        SettingArusKas::create([
            'kode_pos' => 'MK_EXISTING',
            'nama_pos' => 'Existing',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $requestOmittedKode = new StoreSettingArusKasRequest;
        $validatorOmittedKode = $this->validateFormRequest($requestOmittedKode, [
            'nama_pos' => 'Utang Usaha',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'liabilitas',
            'urutan' => 3,
        ]);

        $this->assertFalse($validatorOmittedKode->fails(), json_encode($validatorOmittedKode->errors()->all()));
    }

    public function test_store_setting_arus_kas_request_rejects_invalid_fields_and_incompatible_or_conflicting_coas(): void
    {
        $parent = Coa::create(['kode' => '112', 'nama' => 'Piutang Induk', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $child = Coa::create(['kode' => '112.01', 'nama' => 'Piutang Anak', 'tipe_coa' => 'Piutang Usaha', 'parent_coa' => $parent->id, 'status_aktif' => 1]);
        $kasbank = Coa::create(['kode' => '111.01', 'nama' => 'Kas Utama', 'tipe_coa' => 'Kasbank', 'status_aktif' => 1]);
        $bebanAktif = Coa::create(['kode' => '706.01', 'nama' => 'Beban Penyusutan', 'tipe_coa' => 'Beban', 'status_aktif' => 1]);
        $bebanNonaktif = Coa::create(['kode' => '706.02', 'nama' => 'Beban Nonaktif', 'tipe_coa' => 'Beban', 'status_aktif' => 0]);
        $excluded = Coa::create(['kode' => '142.01', 'nama' => 'Akumulasi Penyusutan', 'tipe_coa' => 'Aset Tetap', 'status_aktif' => 1]);
        $mappedAktifCoa = Coa::create(['kode' => '113.01', 'nama' => 'Persediaan Obat', 'tipe_coa' => 'Persediaan', 'status_aktif' => 1]);
        $mappedNonaktifCoa = Coa::create(['kode' => '114.01', 'nama' => 'Uang Muka Lama', 'tipe_coa' => 'Aktiva Lancar lainnya', 'status_aktif' => 1]);

        SettingArusKasPengecualian::create(['coa_id' => $excluded->id, 'alasan' => 'Akun kontra']);

        $activePos = SettingArusKas::create([
            'kode_pos' => 'MK_PERSEDIAAN',
            'nama_pos' => 'Persediaan Aktif',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $activePos->id, 'coa_id' => $mappedAktifCoa->id]);

        $inactivePos = SettingArusKas::create([
            'kode_pos' => 'MK_UANG_MUKA_OFF',
            'nama_pos' => 'Uang Muka Lama',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 2,
            'is_active' => false,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $inactivePos->id, 'coa_id' => $mappedNonaktifCoa->id]);

        // Basic field & duplicate kode_pos validation
        $invalidFieldsValidator = $this->validateFormRequest(new StoreSettingArusKasRequest, [
            'kode_pos' => 'MK_PERSEDIAAN',
            'nama_pos' => '   ',
            'kelompok_bagian' => 'bagian_ngawur',
            'sifat_pos' => 'sifat_ngawur',
            'urutan' => 0,
        ]);
        $this->assertTrue($invalidFieldsValidator->fails());
        $this->assertTrue($invalidFieldsValidator->errors()->has('kode_pos'));
        $this->assertTrue($invalidFieldsValidator->errors()->has('nama_pos'));
        $this->assertTrue($invalidFieldsValidator->errors()->has('kelompok_bagian'));
        $this->assertTrue($invalidFieldsValidator->errors()->has('sifat_pos'));
        $this->assertTrue($invalidFieldsValidator->errors()->has('urutan'));

        // COA-level incompatibility & cross-table conflict checks
        $coaCases = [
            ['kelompok' => 'modal_kerja', 'sifat' => 'aset', 'coa_id' => $parent->id, 'expected' => 'induk'],
            ['kelompok' => 'modal_kerja', 'sifat' => 'aset', 'coa_id' => $kasbank->id, 'expected' => 'Kasbank'],
            ['kelompok' => 'penyesuaian_laba', 'sifat' => 'penambah_laba', 'coa_id' => $bebanNonaktif->id, 'expected' => 'nonaktif'],
            ['kelompok' => 'penyesuaian_laba', 'sifat' => 'pengurang_laba', 'coa_id' => $bebanNonaktif->id, 'expected' => 'nonaktif'],
            ['kelompok' => 'modal_kerja', 'sifat' => 'aset', 'coa_id' => $bebanAktif->id, 'expected' => 'Laba Rugi'],
            ['kelompok' => 'penyesuaian_laba', 'sifat' => 'penambah_laba', 'coa_id' => $child->id, 'expected' => 'Laba Rugi'],
            ['kelompok' => 'modal_kerja', 'sifat' => 'aset', 'coa_id' => $mappedAktifCoa->id, 'expected' => 'Persediaan Aktif'],
            ['kelompok' => 'modal_kerja', 'sifat' => 'aset', 'coa_id' => $mappedNonaktifCoa->id, 'expected' => "'Uang Muka Lama' (Nonaktif)"],
            ['kelompok' => 'modal_kerja', 'sifat' => 'aset', 'coa_id' => $excluded->id, 'expected' => 'pengecualian'],
        ];

        foreach ($coaCases as $idx => $case) {
            $validator = $this->validateFormRequest(new StoreSettingArusKasRequest, [
                'nama_pos' => 'Pos Uji '.$idx,
                'kelompok_bagian' => $case['kelompok'],
                'sifat_pos' => $case['sifat'],
                'urutan' => 10 + $idx,
                'coa_ids' => [$case['coa_id']],
            ]);

            $this->assertTrue($validator->fails(), "Expected failure for case #{$idx}");
            $this->assertTrue($validator->errors()->has('coa_ids.0'), "Expected coa_ids.0 error for case #{$idx}");
            $this->assertStringContainsString(
                $case['expected'],
                $validator->errors()->first('coa_ids.0'),
                "Unexpected message for case #{$idx}"
            );
        }
    }

    public function test_update_setting_arus_kas_request_allows_own_kode_and_coas_but_rejects_others(): void
    {
        $ownCoa = Coa::create(['kode' => '112.01', 'nama' => 'Piutang Sendiri', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $otherCoa = Coa::create(['kode' => '113.01', 'nama' => 'Persediaan Lain', 'tipe_coa' => 'Persediaan', 'status_aktif' => 1]);

        $ownPos = SettingArusKas::create([
            'kode_pos' => 'MK_SENDIRI',
            'nama_pos' => 'Piutang Sendiri',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $ownPos->id, 'coa_id' => $ownCoa->id]);

        $otherPos = SettingArusKas::create([
            'kode_pos' => 'MK_ORANG_LAIN',
            'nama_pos' => 'Persediaan Lain',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 2,
            'is_active' => false,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $otherPos->id, 'coa_id' => $otherCoa->id]);

        $validRequest = new UpdateSettingArusKasRequest;
        $this->assertTrue($validRequest->authorize());

        $validValidator = $this->validateFormRequest($validRequest, [
            'kode_pos' => 'MK_SENDIRI',
            'nama_pos' => 'Piutang Sendiri Revisi',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
            'coa_ids' => [$ownCoa->id],
        ], $ownPos);

        $this->assertFalse($validValidator->fails(), json_encode($validValidator->errors()->all()));

        // Numeric route parameter should also resolve cleanly for unique & COA conflict checks
        $numericRouteValidator = $this->validateFormRequest(new UpdateSettingArusKasRequest, [
            'kode_pos' => 'MK_ORANG_LAIN',
            'nama_pos' => 'Piutang Sendiri Revisi',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'coa_ids' => [$otherCoa->id],
        ], $ownPos->id);

        $this->assertTrue($numericRouteValidator->fails());
        $this->assertTrue($numericRouteValidator->errors()->has('kode_pos'));
        $this->assertTrue($numericRouteValidator->errors()->has('coa_ids.0'));
        $this->assertStringContainsString("'Persediaan Lain' (Nonaktif)", $numericRouteValidator->errors()->first('coa_ids.0'));
    }

    public function test_reorder_setting_arus_kas_request_validates_same_kelompok_bagian(): void
    {
        $pos1 = SettingArusKas::create([
            'kode_pos' => 'MK_1',
            'nama_pos' => 'Pos 1',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
        ]);
        $pos2 = SettingArusKas::create([
            'kode_pos' => 'MK_2',
            'nama_pos' => 'Pos 2',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 2,
        ]);
        $otherSectionPos = SettingArusKas::create([
            'kode_pos' => 'INV_1',
            'nama_pos' => 'Pos Investasi',
            'kelompok_bagian' => 'investasi',
            'sifat_pos' => 'aset',
            'urutan' => 1,
        ]);

        $validRequest = new ReorderSettingArusKasRequest;
        $this->assertTrue($validRequest->authorize());

        $validValidator = $this->validateFormRequest($validRequest, [
            'kelompok_bagian' => 'modal_kerja',
            'ordered_ids' => [$pos2->id, $pos1->id],
        ]);
        $this->assertFalse($validValidator->fails(), json_encode($validValidator->errors()->all()));

        $crossSectionValidator = $this->validateFormRequest(new ReorderSettingArusKasRequest, [
            'kelompok_bagian' => 'modal_kerja',
            'ordered_ids' => [$pos1->id, $otherSectionPos->id],
        ]);
        $this->assertTrue($crossSectionValidator->fails());
        $this->assertTrue($crossSectionValidator->errors()->has('ordered_ids'));

        $emptyValidator = $this->validateFormRequest(new ReorderSettingArusKasRequest, [
            'kelompok_bagian' => 'invalid_bagian',
            'ordered_ids' => [],
        ]);
        $this->assertTrue($emptyValidator->fails());
        $this->assertTrue($emptyValidator->errors()->has('kelompok_bagian'));
        $this->assertTrue($emptyValidator->errors()->has('ordered_ids'));
    }

    public function test_store_pengecualian_arus_kas_request_validates_coas(): void
    {
        $parent = Coa::create(['kode' => '142', 'nama' => 'Akumulasi Induk', 'tipe_coa' => 'Aset Tetap', 'status_aktif' => 1]);
        $akumAktif = Coa::create(['kode' => '142.01', 'nama' => 'Akum Gedung', 'tipe_coa' => 'Aset Tetap', 'parent_coa' => $parent->id, 'status_aktif' => 1]);
        $akumNonaktif = Coa::create(['kode' => '142.02', 'nama' => 'Akum Lama', 'tipe_coa' => 'Aset Tetap', 'parent_coa' => $parent->id, 'status_aktif' => 0]);
        $kasbank = Coa::create(['kode' => '111.01', 'nama' => 'Kas Kecil', 'tipe_coa' => 'Kasbank', 'status_aktif' => 1]);
        $labaRugi = Coa::create(['kode' => '706.01', 'nama' => 'Beban Penyusutan', 'tipe_coa' => 'Beban', 'status_aktif' => 1]);
        $mappedCoa = Coa::create(['kode' => '112.01', 'nama' => 'Piutang', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $alreadyExcluded = Coa::create(['kode' => '143.01', 'nama' => 'Akum Kendaraan', 'tipe_coa' => 'Aset Tetap', 'status_aktif' => 1]);

        $pos = SettingArusKas::create([
            'kode_pos' => 'MK_PIUTANG',
            'nama_pos' => 'Piutang Usaha',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
        ]);
        SettingArusKasCoa::create(['setting_arus_kas_id' => $pos->id, 'coa_id' => $mappedCoa->id]);
        SettingArusKasPengecualian::create(['coa_id' => $alreadyExcluded->id, 'alasan' => 'Sudah dikecualikan']);

        $validRequest = new StorePengecualianArusKasRequest;
        $this->assertTrue($validRequest->authorize());

        $validValidator = $this->validateFormRequest($validRequest, [
            'coa_ids' => [$akumAktif->id, $akumNonaktif->id],
            'alasan' => '  Akun kontra penyusutan  ',
        ]);
        $this->assertFalse($validValidator->fails(), json_encode($validValidator->errors()->all()));
        $this->assertSame('Akun kontra penyusutan', $validRequest->input('alasan'));

        $emptyValidator = $this->validateFormRequest(new StorePengecualianArusKasRequest, [
            'coa_ids' => [],
        ]);
        $this->assertTrue($emptyValidator->fails());
        $this->assertTrue($emptyValidator->errors()->has('coa_ids'));

        $badCases = [
            [$parent->id, 'induk'],
            [$kasbank->id, 'Kasbank'],
            [$labaRugi->id, 'Laba Rugi'],
            [$mappedCoa->id, 'Piutang Usaha'],
            [$alreadyExcluded->id, 'pengecualian'],
        ];

        foreach ($badCases as $idx => [$coaId, $expected]) {
            $validator = $this->validateFormRequest(new StorePengecualianArusKasRequest, [
                'coa_ids' => [$coaId],
                'alasan' => 'Uji gagal',
            ]);
            $this->assertTrue($validator->fails(), "Expected failure for pengecualian case #{$idx}");
            $this->assertTrue($validator->errors()->has('coa_ids.0'), "Expected coa_ids.0 error for case #{$idx}");
            $this->assertStringContainsString($expected, $validator->errors()->first('coa_ids.0'));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateFormRequest(
        FormRequest $request,
        array $data,
        SettingArusKas|int|null $routePos = null,
    ): ValidationValidator {
        $request->merge($data);
        $request->setContainer(app());

        if ($routePos !== null) {
            $request->setRouteResolver(function () use ($routePos): Route {
                $route = new Route('PUT', '/pengaturan/arus-kas/tidak-langsung/{settingArusKas}', []);
                $route->parameters = ['settingArusKas' => $routePos];

                return $route;
            });
        }

        $reflection = new \ReflectionMethod($request, 'prepareForValidation');
        $reflection->setAccessible(true);
        $reflection->invoke($request);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        foreach ($request->after() as $after) {
            $validator->after($after);
        }

        return $validator;
    }
}
