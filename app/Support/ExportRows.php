<?php

namespace App\Support;

class ExportRows
{
    /**
     * Blank hierarchical parent cells when they match the previous row.
     *
     * @param  list<list<mixed>>  $rows
     * @param  list<int>  $parentIndexes
     * @return list<list<mixed>>
     */
    public static function blankRepeatedParents(array $rows, array $parentIndexes): array
    {
        $previous = null;

        foreach ($rows as $index => $row) {
            $original = array_values($row);
            if ($previous !== null) {
                foreach ($parentIndexes as $column) {
                    $current = (string) ($original[$column] ?? '');
                    $prior = (string) ($previous[$column] ?? '');
                    if ($current !== '' && $current === $prior) {
                        $row[$column] = '';
                    } else {
                        break;
                    }
                }
            }
            $previous = $original;
            $rows[$index] = array_values($row);
        }

        return $rows;
    }
}
