<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Collection;

class NaturalSort
{
    /**
     * Pad every digit group so GLOBE01–GLOBE10 sort as 1, 2, 3, 10.
     */
    public static function key(mixed $value): string
    {
        $text = mb_strtolower(trim((string) $value));
        if ($text === '') {
            return '';
        }

        $padded = preg_replace_callback('/\d+/', static function (array $matches): string {
            $digits = ltrim($matches[0], '0');
            if ($digits === '') {
                $digits = '0';
            }

            return str_pad($digits, 10, '0', STR_PAD_LEFT);
        }, $text);

        return $padded ?? $text;
    }

    public static function compare(mixed $left, mixed $right): int
    {
        return strcmp(self::key($left), self::key($right));
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  Collection<TKey, TValue>  $items
     * @param  callable(TValue): mixed|string  $key
     * @return Collection<int, TValue>
     */
    public static function sortBy(Collection $items, callable|string $key): Collection
    {
        return $items->sort(function ($left, $right) use ($key) {
            $leftValue = is_string($key) ? data_get($left, $key) : $key($left);
            $rightValue = is_string($key) ? data_get($right, $key) : $key($right);

            return self::compare($leftValue, $rightValue);
        })->values();
    }

    public static function apply(EloquentBuilder|QueryBuilder $query, string $column, string $direction = 'asc'): EloquentBuilder|QueryBuilder
    {
        return self::applyRaw($query, self::grammar($query)->wrap($column), $direction);
    }

    public static function applyRelated(
        EloquentBuilder|QueryBuilder $query,
        string $relatedTable,
        string $relatedColumn,
        string $ownerKey,
        string $direction = 'asc'
    ): EloquentBuilder|QueryBuilder {
        $grammar = self::grammar($query);
        $tableSql = $grammar->wrapTable($relatedTable);
        $columnSql = $grammar->wrap($relatedTable.'.'.$relatedColumn);
        $idSql = $grammar->wrap($relatedTable.'.id');
        $ownerSql = $grammar->wrap($ownerKey);
        $expression = "(SELECT {$columnSql} FROM {$tableSql} WHERE {$idSql} = {$ownerSql})";

        return self::applyRaw($query, $expression, $direction);
    }

    public static function applyRaw(EloquentBuilder|QueryBuilder $query, string $expression, string $direction = 'asc'): EloquentBuilder|QueryBuilder
    {
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $driver = $query->getConnection()->getDriverName();
        $keySql = $driver === 'sqlite'
            ? 'natural_sort_key('.$expression.')'
            : self::mysqlKeySql($expression);

        $query->orderByRaw($keySql.' '.$direction);

        return $query;
    }

    public static function registerSqliteFunction(object $connection): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = $connection->getPdo();
        if (! is_object($pdo) || ! method_exists($pdo, 'sqliteCreateFunction')) {
            return;
        }

        $pdo->sqliteCreateFunction('natural_sort_key', [self::class, 'key'], 1);
    }

    private static function mysqlKeySql(string $expression): string
    {
        $value = 'IFNULL('.$expression.', \'\')';

        return 'CONCAT(REGEXP_REPLACE(LOWER('.$value.'), \'[0-9]+$\', \'\'), LPAD(IFNULL(NULLIF(REGEXP_SUBSTR('.$value.', \'[0-9]+$\'), \'\'), \'0\'), 10, \'0\'))';
    }

    private static function grammar(EloquentBuilder|QueryBuilder $query): Grammar
    {
        if ($query instanceof EloquentBuilder) {
            return $query->getQuery()->getGrammar();
        }

        return $query->getGrammar();
    }
}
