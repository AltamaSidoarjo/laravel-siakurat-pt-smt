<?php

namespace Tests\Unit\Support;

use App\Models\Coa;
use App\Support\CoaTreeHelper;
use PHPUnit\Framework\TestCase;

class CoaTreeHelperTest extends TestCase
{
    public function test_partition_and_collect_leaf_descendants_handles_multi_level_and_cycles(): void
    {
        $root = (new Coa(['kode' => '5', 'nama' => 'Beban', 'tipe_coa' => 'Beban', 'status_aktif' => 1]))->forceFill(['id' => 1]);
        $sub = (new Coa(['parent_coa' => 1, 'kode' => '5.1', 'nama' => 'Beban Usaha', 'tipe_coa' => 'Beban', 'status_aktif' => 1]))->forceFill(['id' => 2]);
        $leafB = (new Coa(['parent_coa' => 2, 'kode' => '5.1.02', 'nama' => 'Air', 'tipe_coa' => 'Beban', 'status_aktif' => 1]))->forceFill(['id' => 4]);
        $leafA = (new Coa(['parent_coa' => 2, 'kode' => '5.1.01', 'nama' => 'Listrik', 'tipe_coa' => 'Beban', 'status_aktif' => 1]))->forceFill(['id' => 3]);
        $kasbankLeaf = (new Coa(['parent_coa' => 1, 'kode' => '5.9.01', 'nama' => 'Kas Salah', 'tipe_coa' => 'Kasbank', 'status_aktif' => 1]))->forceFill(['id' => 5]);
        $cycA = (new Coa(['parent_coa' => 7, 'kode' => '9.1', 'nama' => 'Cycle A', 'tipe_coa' => 'Beban', 'status_aktif' => 1]))->forceFill(['id' => 6]);
        $cycB = (new Coa(['parent_coa' => 6, 'kode' => '9.2', 'nama' => 'Cycle B', 'tipe_coa' => 'Beban', 'status_aktif' => 1]))->forceFill(['id' => 7]);
        $cycLeaf = (new Coa(['parent_coa' => 7, 'kode' => '9.2.01', 'nama' => 'Cycle Leaf', 'tipe_coa' => 'Beban', 'status_aktif' => 1]))->forceFill(['id' => 8]);

        $all = collect([$root, $sub, $leafB, $leafA, $kasbankLeaf, $cycA, $cycB, $cycLeaf]);

        $partition = CoaTreeHelper::partitionParentAndLeafIds($all);
        $this->assertArrayHasKey(1, $partition['parentIds']);
        $this->assertArrayHasKey(2, $partition['parentIds']);
        $this->assertEqualsCanonicalizing([1, 2, 6, 7], $partition['parentCoas']->pluck('id')->all());
        $this->assertEqualsCanonicalizing([3, 4, 5, 8], $partition['leafCoas']->pluck('id')->all());

        $descendants = CoaTreeHelper::collectLeafDescendants(1, $all);
        $this->assertSame([3, 4, 5], array_map(fn (Coa $c): int => (int) $c->id, $descendants));
        $this->assertSame([], CoaTreeHelper::collectLeafDescendants(3, $all));

        $cycDescendants = CoaTreeHelper::collectLeafDescendants(6, $all);
        $this->assertSame([8], array_map(fn (Coa $c): int => (int) $c->id, $cycDescendants));
    }
}
