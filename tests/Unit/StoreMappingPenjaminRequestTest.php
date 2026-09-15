<?php

namespace Tests\Unit;

use App\Http\Requests\Pengaturan\StoreMappingPenjaminRequest;
use App\Models\Coa;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreMappingPenjaminRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('mapping_penjamin_piutang');
        Schema::dropIfExists('coa');

        Schema::create('coa', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_coa')->nullable();
            $table->unsignedTinyInteger('status_aktif')->default(1);
            $table->string('tipe_coa')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->boolean('is_postable')->default(true);
            $table->timestamps();
        });

        Schema::create('mapping_penjamin_piutang', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('penjamin_id')->unique();
            $table->string('nama_penjamin');
            $table->unsignedInteger('coa_id');
            $table->timestamps();
        });
    }

    public function test_accepts_active_leaf_of_any_type_even_when_not_postable(): void
    {
        $coa = $this->createCoa('4101', 'Pendapatan', 'Pendapatan', true, false);

        $validator = $this->validatorFor($coa);

        $this->assertTrue($validator->passes());
        $this->assertEmpty($validator->errors()->get('coa_id'));
    }

    public function test_rejects_inactive_and_parent_accounts(): void
    {
        $inactive = $this->createCoa('5101', 'Beban Nonaktif', 'Beban', false, false);
        $parent = $this->createCoa('1000', 'Aset Lancar', 'Aset', true, false);
        $this->createCoa('1001', 'Kas', 'Kasbank', true, false, $parent->id);

        foreach ([$inactive, $parent] as $coa) {
            $validator = $this->validatorFor($coa);

            $this->assertTrue($validator->fails());
            $this->assertSame(
                ['Akun harus merupakan COA aktif yang tidak memiliki akun turunan.'],
                $validator->errors()->get('coa_id'),
            );
        }
    }

    private function validatorFor(Coa $coa): ValidatorContract
    {
        $request = StoreMappingPenjaminRequest::create('/', 'POST', [
            'penjamin_id' => '002',
            'coa_id' => $coa->id,
        ]);
        $validator = Validator::make($request->all(), $request->rules());

        foreach ($request->after() as $callback) {
            $validator->after($callback);
        }

        return $validator;
    }

    private function createCoa(
        string $kode,
        string $nama,
        string $tipe,
        bool $aktif = true,
        bool $postable = true,
        ?int $parentCoa = null,
    ): Coa {
        return Coa::query()->create([
            'status_aktif' => $aktif ? 1 : 0,
            'parent_coa' => $parentCoa,
            'tipe_coa' => $tipe,
            'kode' => $kode,
            'nama' => $nama,
            'is_postable' => $postable,
        ]);
    }
}
