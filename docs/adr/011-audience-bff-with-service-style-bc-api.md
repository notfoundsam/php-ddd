# ADR-011: Audience as BFF; Bounded-Context Public API is Service-Style

**Status:** Accepted
**Date:** 2026-05-16

## Context

ADR-006 introduced a CQRS message bus with decorator chain (Throttle → Security → Logger → Transaction → Handler). ADR-008 established that **audiences** (`Audience/Admin/`, `Audience/Partner/`, public `Audience/Site/`) own their commands, queries, and roles, while **bounded contexts** (`Catalog/`, future `Crm/`, `Marketing/`) own only the domain layer.

The first feature exercising the full chain on a public, anonymous request path (shop home page) raised three questions those ADRs left implicit:

1. What is the public API of a bounded context — CQRS message classes, service methods, or both?
2. Where do read-side queries live when there is no audience yet? The catalog initially placed them in `Catalog/Application/Query/…` for lack of an `Audience/Site/`.
3. When a controller needs two pieces of data for one page, dispatch two queries (two decorator chain passes) or compose them into one?

A read-side query handler invoked directly as a function (`($handler)($query)`) is indistinguishable from a method call on a service, but the surrounding ceremony (query class, response class, handler class, registry entry, security config entry, tests) is substantial. When the bus is not actually used, the ceremony is overhead. And two dispatches per request run throttle/security/logger/transaction twice — one can fail while the other passes, leaving half-rendered pages and split log lines.

## Decision

### Bounded-Context Public API is a Service, Not Messages

Each bounded context exposes its application-layer functionality through one or more **application services** with typed methods:

```
backend/src/Catalog/
  Domain/                                  (aggregates, value objects, repository interfaces)
  Application/
    Service/CatalogReadService.php         ← the BC public API
    ReadModel/                             ← DTO shapes the service returns
      SearchFilters.php
      ProductListItem.php
      ProductSearchResult.php
      FilterFacets.php
  Infrastructure/                          (repository implementations, framework adapters)
```

Service methods are typed (`searchProducts(SearchFilters, int, int): ProductSearchResult`), synchronous, and called directly by their consumers. They are **not** dispatched through the CQRS bus and **do not** have associated query/handler/response classes inside the BC.

`Application/ReadModel/` holds the data shapes the service hands back. These are flat DTOs designed to be safe to pass to a view layer (no domain objects leak through). When a result is `implements QueryResponseInterface`, that is a convenience for the audience-side handler that wraps it; the BC itself does not depend on the CQRS interfaces.

A version of this with `Catalog\Application\Query\…` classes was tried first — each read operation having `*Query` + `*Handler` + `*Response` + bus registration. It was working but never used through the bus: the Site audience always invoked handlers as `($handler)($query)` because no decorators were wanted at the BC boundary. We removed the bus apparatus from the BC entirely; the service was already the contract.

### Audiences are the BFF; CQRS Messages Exist Because the Bus Exists

Audiences (`Audience/Site/`, `Audience/Admin/`, `Audience/Partner/`) own:

- **CQRS messages** — `*Query`, `*Command`, `*Response` classes that go through the bus. They exist *because* the audience wants the bus decorators (throttle, security, logger, transaction). Messages take typed VOs and primitives through their constructor; they have no knowledge of raw HTTP shapes.
- **Permission registry** — `*SecurityConfig` lives in the audience, listing the audience's queries/commands with their permissions (per ADR-008's fail-closed model).
- **Composition** — when a page needs more than one piece of data, the audience defines a **composite query** that returns one response wrapping the parts (`ViewCatalogHomePageResponse` = `ProductSearchResult` + `FilterFacets`).

HTTP-input parsing is **not** an audience concern — it is delegated to per-action Form classes (ADR-015) that build the typed audience message from `$_GET`/`$_POST`. Form classes are framework-specific (FuelPHP today, Laravel `FormRequest` tomorrow); the audience messages they produce are framework-agnostic.

The audience handler delegates to BC services by direct method call:

```php
final class ViewCatalogHomePageQueryHandler implements QueryHandlerInterface
{
    public function __construct(private CatalogReadService $catalog) {}

    public function __invoke(ViewCatalogHomePageQuery $query): ViewCatalogHomePageResponse
    {
        $products = $this->catalog->searchProducts(
            $query->getFilters(), $query->getPage(), $query->getPerPage()
        );
        $facets = $this->catalog->getFilterFacets();
        return new ViewCatalogHomePageResponse($products, $facets);
    }
}
```

One `$bus->dispatch($compositeQuery)` per page-render: one throttle check, one security check, one log line, one transaction boundary, one atomic decision. The composite-query pattern is the canonical answer to "controller needs multiple BC reads".

### Audience Always Returns Its Own `*Response` Type

Audience query handlers never return BC read models directly, even when the audience response is a near-passthrough:

```php
public function __invoke(SearchCatalogProductsQuery $query): SearchCatalogProductsResponse
{
    $result = $this->catalog->searchProducts(...);   // ProductSearchResult (BC)
    return new SearchCatalogProductsResponse($result);   // audience-owned wrapper
}
```

The audience contract stays stable when the BC evolves. If `ProductSearchResult` gets renamed, splits, or gains/loses fields, callers of `SearchCatalogProductsResponse` see only the audience-shape, and the wrapper's adapter glue absorbs the change. This is a thin Anti-Corruption Layer in the Vernon sense — one-to-one passthrough today, room for audience-specific concerns (cache hints, pagination flags, presentation metadata) tomorrow without churning the BC.

Returning the BC read model directly was tried, scoped to "passthrough cases only". It produced an inconsistency — composite handlers obviously had to wrap, simple-delegate handlers did not — that was easy to learn wrong. Always-wrap removes the rule from the daily decision space.

### BC Queries Are Not Reachable Through the Bus (Fail-Closed)

`Catalog\Application\Service\CatalogReadService` is the only way for an audience to read from the catalog. There is no `Catalog\Application\Query\*` directory, no `CatalogQueryHandlerRegistry` (it was deleted), no entries in any `SecurityConfig` for catalog operations. If something tries `$bus->dispatch(new <hypothetical Catalog query>)`, the security decorator throws `SecurityConfigurationException` — the fail-closed property from ADR-008 keeps the boundary honest.

Inside the BC, the service has whatever signature it likes. Inside an audience, the contract is the audience query/response. The bus only sees audience messages.

## Components

Reference implementation lives under `backend/src/Catalog/` (service + read models, no bus apparatus inside the BC) and `backend/src/Audience/Site/` (composite + single-purpose queries, a `QueryHandlerRegistry`, a `SecurityConfig` mapping both queries to `null` for public access per ADR-008). HTTP-input parsing is owned by per-action Form classes per ADR-015.

Controller is two lines of meaningful work — build the typed query through the form, dispatch:

```php
$query = Form_Site_Catalog_Home::fromHttpInput(Input::get())->toQuery();
$page = $this->queryBus->dispatch($query);
```

Rendering belongs to ADR-012.

## Consequences

**Positive:**

- BC public API is a method-call surface, not a message catalogue. New contributors discover the BC by reading one service class, not by spelunking through query directories.
- BC tests are method-level: `$service->searchProducts(...)` returns a result; no bus/decorator scaffolding in unit tests.
- Audience CQRS overhead is justified by what it gives — one bus dispatch produces one decorator pass, one log, one throttle check. Multiple BC operations per page run inside one composite handler at near method-call cost.
- Audience contract stability decoupled from BC evolution. The catalog can rename `ProductSearchResult` to `Catalog\Application\ReadModel\ProductPage` without touching `Audience\Site\Application\Query\SearchCatalogProducts\SearchCatalogProductsResponse`.
- Fail-closed property from ADR-008 actively enforces the boundary: any accidental attempt to dispatch a BC query through the bus throws at dispatch time, not at deploy time.
- Migration target Laravel maps cleanly: BC services become Laravel singletons, audience queries become Laravel-side commands/queries handled by a similar bus or direct `Bus::dispatch`. The structural distinction survives the framework switch.

**Negative:**

- Doubled DTO surface for audiences whose contract is identical to the BC's. `SearchCatalogProductsResponse { private ProductSearchResult $result; }` is mostly ceremony today. The cost is small (≤30 lines per audience-query) and pays back the first time a BC shape changes; the discipline of always-wrap is worth keeping.
- Audience handlers couple to BC service implementations (constructor takes the service class). If a service is later split or renamed, all dependent audience handlers update their wiring. This is acceptable — service surface is the BC's stable contract; if it changes, dependents need to know.
- Composite handlers can grow when a page needs many small reads. The intent is to keep composites at the audience level; if multiple audiences need the same composition, that's a signal to add a method on the BC service rather than duplicate the composition in two audiences.

**Migrations and follow-ups:**

- When `Audience/Partner/` gets write-side commands (e.g., `RegisterProductCommand`), they live there, dispatched through the same bus with throttle/security/transaction, and they call into `Catalog\Application\Service\CatalogWriteService` (to be created when there is one). The pattern is symmetric for the write side.
- When Marketing or CRM contexts are populated, they follow the same shape: `Application/Service/*Service.php`, `Application/ReadModel/*`, no bus apparatus inside the BC.
- ADR-008's existing rule "audiences own commands, queries, and roles" is reinforced by this ADR. Treat them as one decision set.
