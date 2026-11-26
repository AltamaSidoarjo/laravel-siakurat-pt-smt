<?php

namespace App\Support;

use App\Models\Coa;
use Illuminate\Support\Collection;

class CoaTreeHelper
{
    /**
     * Partition a collection of COAs into parent IDs map, parent collection, and leaf collection.
     * Retains all accounts (including Kasbank) in leafCoas so callers may filter as needed.
     *
     * @param  Collection<int, Coa>  $allCoas
     * @return array{
     *     parentIds: array<int, bool>,
     *     parentCoas: Collection<int, Coa>,
     *     leafCoas: Collection<int, Coa>
     * }
     */
    public static function partitionParentAndLeafIds(Collection $allCoas): array
    {
        $parentIds = [];
        foreach ($allCoas as $coa) {
            if ($coa->parent_coa !== null && (int) $coa->parent_coa > 0) {
                $parentIds[(int) $coa->parent_coa] = true;
            }
        }

        $parentCoas = $allCoas
            ->filter(fn (Coa $coa): bool => isset($parentIds[(int) $coa->id]))
            ->values();

        $leafCoas = $allCoas
            ->filter(fn (Coa $coa): bool => ! isset($parentIds[(int) $coa->id]))
            ->values();

        return [
            'parentIds' => $parentIds,
            'parentCoas' => $parentCoas,
            'leafCoas' => $leafCoas,
        ];
    }

    /**
     * Collect all descendant leaf COAs under the given parent COA with cycle protection,
     * sorted by (kode, id).
     *
     * @param  Collection<int, Coa>  $allCoas
     * @return array<int, Coa>
     */
    public static function collectLeafDescendants(int $parentId, Collection $allCoas): array
    {
        if ($parentId <= 0) {
            return [];
        }

        $childrenByParent = [];
        foreach ($allCoas as $coa) {
            if ($coa->parent_coa !== null && (int) $coa->parent_coa > 0) {
                $childrenByParent[(int) $coa->parent_coa][] = $coa;
            }
        }

        if (! isset($childrenByParent[$parentId])) {
            return [];
        }

        $visited = [$parentId => true];
        $leafNodes = [];
        self::traverseLeaves($parentId, $childrenByParent, $visited, $leafNodes);

        if ($leafNodes === []) {
            return [];
        }

        usort(
            $leafNodes,
            fn (Coa $a, Coa $b): int => strcmp((string) $a->kode, (string) $b->kode) ?: ((int) $a->id <=> (int) $b->id)
        );

        return $leafNodes;
    }

    /**
     * @param  array<int, array<int, Coa>>  $childrenByParent
     * @param  array<int, bool>  $visited
     * @param  array<int, Coa>  $leafNodes
     */
    private static function traverseLeaves(
        int $currentParentId,
        array $childrenByParent,
        array &$visited,
        array &$leafNodes,
    ): void {
        foreach ($childrenByParent[$currentParentId] ?? [] as $child) {
            $childId = (int) $child->id;
            if (isset($visited[$childId])) {
                continue;
            }

            $visited[$childId] = true;

            if (! isset($childrenByParent[$childId]) || $childrenByParent[$childId] === []) {
                $leafNodes[] = $child;

                continue;
            }

            self::traverseLeaves($childId, $childrenByParent, $visited, $leafNodes);
        }
    }
}
