<?php

declare(strict_types=1);

namespace Audience\Site\Application\Query\SearchCatalogProducts;

use Catalog\Application\ReadModel\SearchFilters;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryInterface;

/**
 * Customer-facing query: paginate products for the public catalog.
 *
 * Audience boundary: this is the *public* contract a non-authenticated visitor is allowed
 * to issue. It delegates internally to `Catalog\Application\Service\CatalogReadService` for
 * the actual read; the audience layer owns the permission, the HTTP-input parsing, and any
 * audience-specific shaping.
 *
 * @implements QueryInterface<SearchCatalogProductsResponse>
 */
final class SearchCatalogProductsQuery implements QueryInterface
{
    public const DEFAULT_PER_PAGE = 12;

    private SearchFilters $filters;
    private int $page;
    private int $perPage;

    public function __construct(SearchFilters $filters, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE)
    {
        $this->filters = $filters;
        $this->page = $page < 1 ? 1 : $page;
        $this->perPage = $perPage < 1 ? self::DEFAULT_PER_PAGE : $perPage;
    }

    /**
     * Builds the query from an untrusted HTTP query-string array (e.g. $_GET).
     *
     * @param array<string, mixed> $raw
     */
    public static function fromHttpInput(array $raw): self
    {
        $filters = SearchFilters::fromHttpInput($raw);
        $page = $raw['page'] ?? null;
        if (is_int($page)) {
            $intPage = $page;
        } elseif (is_string($page) && preg_match('/^\d+$/', $page) === 1) {
            $intPage = (int) $page;
        } else {
            $intPage = 1;
        }
        return new self($filters, $intPage);
    }

    public function getFilters(): SearchFilters
    {
        return $this->filters;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }
}
