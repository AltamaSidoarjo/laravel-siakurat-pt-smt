<?php

namespace Tests\Unit\Models;

use App\Models\Coa;
use App\Models\SettingArusKas;
use App\Models\SettingArusKasCoa;
use App\Models\SettingArusKasPengecualian;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingArusKasModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['setting_arus_kas_pengecualian', 'setting_arus_kas_coa', 'setting_arus_kas', 'coa'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('coa', function (Blueprint $table) {
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

    public function test_setting_arus_kas_relationships_casts_and_scopes(): void
    {
        $posB = SettingArusKas::create([
            'kode_pos' => 'MK_B',
            'nama_pos' => 'Pos Kedua',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => 1,
            'urutan' => 2,
            'is_active' => 1,
        ]);
        $posA = SettingArusKas::create([
            'kode_pos' => 'MK_A',
            'nama_pos' => 'Pos Pertama',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'pakai_prefix_perubahan' => 0,
            'urutan' => 1,
            'is_active' => 1,
        ]);
        SettingArusKas::create([
            'kode_pos' => 'MK_OFF',
            'nama_pos' => 'Pos Nonaktif',
            'kelompok_bagian' => 'modal_kerja',
            'sifat_pos' => 'aset',
            'urutan' => 3,
            'is_active' => 0,
        ]);

        $coa = Coa::create(['kode' => '112.01', 'nama' => 'Piutang', 'tipe_coa' => 'Piutang Usaha', 'status_aktif' => 1]);
        $pivot = SettingArusKasCoa::create(['setting_arus_kas_id' => $posA->id, 'coa_id' => $coa->id]);

        $this->assertTrue($posB->pakai_prefix_perubahan);
        $this->assertFalse($posA->pakai_prefix_perubahan);
        $this->assertTrue($posB->is_active);
        $this->assertSame(2, $posB->urutan);
        $this->assertSame(['MK_A', 'MK_B'], SettingArusKas::aktif()->urut()->pluck('kode_pos')->all());
        $this->assertSame([$coa->id], $posA->coas->pluck('id')->all());
        $this->assertSame([$pivot->id], $posA->mappingCoa->pluck('id')->all());
        $this->assertSame($posA->id, $pivot->settingArusKas->id);
        $this->assertSame($coa->id, $pivot->coa->id);

        $coaKontra = Coa::create(['kode' => '142.01', 'nama' => 'Akum Peny', 'tipe_coa' => 'Aset Tetap', 'status_aktif' => 1]);
        $exc = SettingArusKasPengecualian::create(['coa_id' => $coaKontra->id, 'alasan' => 'Akun kontra']);
        $this->assertSame($coaKontra->id, $exc->coa->id);

        $this->assertSame('(Kenaikan) Pos Kedua', $posB->formatLabelPos(1000.0));
        $this->assertSame('Penurunan Pos Kedua', $posB->formatLabelPos(-1000.0));
        $this->assertSame('Penurunan (kenaikan) Pos Kedua', $posB->formatLabelPos(0.0));
        $this->assertSame('Pos Pertama', $posA->formatLabelPos(1000.0));
    }

    public function test_format_label_pos_for_liabilitas_ekuitas_and_laba_rugi(): void
    {
        $liabilitas = new SettingArusKas([
            'nama_pos' => 'Utang Usaha',
            'sifat_pos' => 'liabilitas',
            'pakai_prefix_perubahan' => true,
        ]);
        $this->assertSame('Kenaikan Utang Usaha', $liabilitas->formatLabelPos(100.0));
        $this->assertSame('(Penurunan) Utang Usaha', $liabilitas->formatLabelPos(-100.0));
        $this->assertSame('Kenaikan (penurunan) Utang Usaha', $liabilitas->formatLabelPos(0.0));
        $this->assertSame('Kenaikan (penurunan) Utang Usaha', $liabilitas->formatLabelPos(0.005));
        $this->assertSame('Kenaikan (penurunan) Utang Usaha', $liabilitas->formatLabelPos(-0.005));

        $ekuitas = new SettingArusKas([
            'nama_pos' => 'Modal Disetor',
            'sifat_pos' => 'ekuitas',
            'pakai_prefix_perubahan' => true,
        ]);
        $this->assertSame('Kenaikan Modal Disetor', $ekuitas->formatLabelPos(500.0));
        $this->assertSame('(Penurunan) Modal Disetor', $ekuitas->formatLabelPos(-500.0));
        $this->assertSame('Kenaikan (penurunan) Modal Disetor', $ekuitas->formatLabelPos(0.0));

        $penambahLaba = new SettingArusKas([
            'nama_pos' => 'Beban Penyusutan',
            'sifat_pos' => 'penambah_laba',
            'pakai_prefix_perubahan' => true,
        ]);
        $this->assertSame('Beban Penyusutan', $penambahLaba->formatLabelPos(250.0));

        $pengurangLaba = new SettingArusKas([
            'nama_pos' => 'Keuntungan Pelepasan Aset',
            'sifat_pos' => 'pengurang_laba',
            'pakai_prefix_perubahan' => true,
        ]);
        $this->assertSame('Keuntungan Pelepasan Aset', $pengurangLaba->formatLabelPos(-250.0));
    }

    public function test_setting_arus_kas_constants_and_tiebreaker_ordering(): void
    {
        $this->assertSame(
            ['penyesuaian_laba', 'modal_kerja', 'investasi', 'pendanaan'],
            SettingArusKas::KELOMPOK_BAGIAN
        );
        $this->assertSame([
            'penyesuaian_laba' => 'Penyesuaian Laba',
            'modal_kerja' => 'Modal Kerja Operasi',
            'investasi' => 'Aktivitas Investasi',
            'pendanaan' => 'Aktivitas Pendanaan',
        ], SettingArusKas::KELOMPOK_BAGIAN_LABELS);
        $this->assertSame(
            ['aset', 'liabilitas', 'ekuitas', 'penambah_laba', 'pengurang_laba'],
            SettingArusKas::SIFAT_POS
        );
        $this->assertSame(['penambah_laba', 'pengurang_laba'], SettingArusKas::SIFAT_LABA_RUGI);
        $this->assertSame(['aset', 'liabilitas', 'ekuitas'], SettingArusKas::SIFAT_NERACA);
        $this->assertSame(
            ['Pendapatan', 'Pendapatan lain', 'Beban', 'Beban lain', 'Beban Pokok Penjualan'],
            SettingArusKas::TIPE_LABA_RUGI
        );

        $first = SettingArusKas::create([
            'kode_pos' => 'TIE_1',
            'nama_pos' => 'Tie 1',
            'kelompok_bagian' => 'investasi',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
        ]);
        $second = SettingArusKas::create([
            'kode_pos' => 'TIE_2',
            'nama_pos' => 'Tie 2',
            'kelompok_bagian' => 'investasi',
            'sifat_pos' => 'aset',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $this->assertSame(
            [$first->id, $second->id],
            SettingArusKas::query()->where('kelompok_bagian', 'investasi')->urut()->pluck('id')->all()
        );
    }
}
