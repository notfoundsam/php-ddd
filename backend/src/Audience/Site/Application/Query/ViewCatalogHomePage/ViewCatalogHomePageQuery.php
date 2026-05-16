<?php

declare(strict_types=1);

namespace Audience\Site\Application\Query\ViewCatalogHomePage;

use Catalog\Application\ReadModel\SearchFilters;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryInterface;

/**
 * Customer-facing composite query: everything a visitor needs to render the catalog landing page.
 *
 * One dispatch = one decorator chain (throttle/security/log). Internally delegates to the
 * `Catalog` BC for products and facets. This is the canonical pattern for read-side composition:
 * the audience layer owns the page-shaped query; the BC owns the small, reusable building blocks.
 *
 * @implements QueryInterface<ViewCatalogHomePageResponse>
 */
final class ViewCatalogHomePageQuery implements QueryInterface
{
    public const DEFAULT_PER_PAGE = 12;

    private SearchFilters $filters;
    private int $page;
    private int $perPage;

    public function __construct(SearchFilters $filters, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE)
    {
        $this->filters = $filters;
        $this->page = max(1, $page);
        $this->perPage = $perPage < 1 ? self::DEFAULT_PER_PAGE : $perPage;
    }

    /**
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
