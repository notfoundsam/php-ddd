<?php

declare(strict_types=1);

use Catalog\Application\ReadModel\SearchFilters;

class Form_Site_Catalog_Filters
{
    /**
     * @param array<string, mixed> $raw
     */
    public static function parse(array $raw): SearchFilters
    {
        return new SearchFilters(
            self::stringValues(self::asArray($raw['category'] ?? [])),
            self::stringValues(self::asArray($raw['brand'] ?? [])),
            self::nullableInt($raw['price_min'] ?? null),
            self::nullableInt($raw['price_max'] ?? null),
            is_string($raw['q'] ?? null) ? $raw['q'] : null,
        );
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function parsePage(array $raw): int
    {
        $page = $raw['page'] ?? null;
        if (is_int($page)) {
            return max(1, $page);
        }
        if (is_string($page) && preg_match('/^\d+$/', $page) === 1) {
            return max(1, (int) $page);
        }
        return 1;
    }

    /**
     * @param mixed $value
     * @return array<int, mixed>
     */
    private static function asArray($value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param array<int, mixed> $values
     * @return string[]
     */
    private static function stringValues(array $values): array
    {
        $result = [];
        foreach ($values as $v) {
            if (!is_string($v) || $v === '') {
                continue;
            }
            $result[] = $v;
        }
        return $result;
    }

    /**
     * @param mixed $value
     */
    private static function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        return null;
    }
}
