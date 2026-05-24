# ADR-015: HTTP Input Validation via Per-Action Form Classes

**Status:** Accepted
**Date:** 2026-05-24

## Context

HTTP input validation today lives inline in FuelPHP controllers: each action calls `Validation::forge`, declares rules, runs them, and on failure re-renders the form. The same controller then constructs a Command/Query, passing values through Value Objects (`EmailAddress`, `PlaintextPassword`) whose constructors re-validate the same rules. This produces three problems:

1. **Duplication.** Format rules (`valid_email`, `max_length[72]`) live in both the controller's `Validation` config and the VO constructor. Divergence between the two is silent — the form accepts input the VO then rejects with a 500.
2. **Controller bloat.** Every form action carries 8-15 lines of validator boilerplate plus error-rendering branches. Adding cross-field rules or repopulating user input on error multiplies it further.
3. **No clear seam for localisation.** `Lang::get` calls scattered across controllers, no per-action place to own the form's user-visible messages.

## Decision

### Form class per controller action

Every controller action that accepts user input has a dedicated form class under `fuelphp/fuel/app/classes/form/<audience>/<action>.php`. FuelPHP underscore-naming, global namespace, mirrors the `controller/` tree. Each form is `final`, instantiated only via a static factory, and exposes:

```php
final class Form_Site_Login
{
    public static function fromHttpInput(array $raw): self;
    public function isValid(): bool;
    public function errors(): array;          // field => localized message
    public function toCommand(): LogInCommand; // or toQuery() for read-side
}
```

The form wraps FuelPHP `Validation` and declares rules in the pipe DSL via `add_field('email', __('site/login.email'), 'required|valid_email')`. Cross-field rules live inside the same factory as plain PHP after `Validation::run`.

**Exception — coercion-only read endpoints.** Public read-side endpoints whose semantics are "coerce missing/garbage to defaults, never reject" (e.g. catalog listing) do not need a Form wrapper. The controller calls a shared parser helper (e.g. `Form_Site_Catalog_Filters::parse($raw)`) and constructs the Query directly. A Form is introduced only once such an endpoint grows endpoint-specific policy (max page, custom defaults, rejection rules with localized messages).

### VO is the single source of truth for domain invariants

Form rules are a **subset** of VO rules — they exist to produce localised user messages for inputs the VO would otherwise reject. If a VO constructor throws `InvalidArgumentException` from inside a form's `toCommand()`, that is a programmer bug (form rules drifted from VO rules), not a user error. Such exceptions surface as 500 (no form re-render). Code review enforces: never weaken a VO check by relying on the form to catch it; never tighten a form check beyond what the VO allows.

### Commands and Queries are immutable DTOs

`static fromHttpInput` on Command/Query is removed. Command/Query constructors take typed VOs and primitives; they have no knowledge of `$_POST` or locale.

### No abstract `Form_*` base class

Each form is a standalone class. The boilerplate per form (~10 lines of `Validation::forge` + error collection) is small enough not to justify a base; the most valuable method on the form (`toCommand`/`toQuery`) is form-specific and gains no type safety from a parent. The three current login forms (`Form_Site_Login`, `Form_Admin_Login`, `Form_Partner_Login`) are byte-identical modulo namespace, lang path, and forge key — and this is **still not** a base-class trigger. Reason: each form's Laravel-migration parent will be `FormRequest`, not a project base. Introducing a project base here means *removing* it during the migration, doubling churn for no lasting structural benefit.

## Components

Lang files split by content type, not by feature:

- `lang/<locale>/errors.php` — cross-cutting operational messages (`csrf_expired`, `throttled`); loaded once in `Controller_Audience::before()` so any controller can use `__('errors.*')`.
- `lang/<locale>/auth.php` — cross-audience auth messages (`invalid_credentials`); loaded by auth controllers.
- `lang/<locale>/<audience>/<form>.php` — form-specific labels (e.g. `site/login.php` with `email`, `password`); loaded by the corresponding Form class. Audience-scoped subdirectories prevent the flat lang directory from growing unmanageably.

Form classes call `__('site/login.email')`; the FuelPHP `__()` helper aliases `Lang::get`. `Lang::load` remains the loading mechanism — `__()` does not load.

## Consequences

**Positive:**

- Format rules live in one HTTP-layer place per action; VO catches only programmer error.
- Controllers shrink to "build form → branch on validity → dispatch → handle domain exceptions → render".
- Localisation has an obvious home: form classes own field labels, `errors.php` owns operational messages, `auth.php` owns auth messages. No message is duplicated across audiences.
- Laravel migration replaces each `Form_*` with a `FormRequest` carrying the same `toCommand()`/`toQuery()` body and equivalent rules (`max_length[N]` → `max:N`). Commands, Queries, VOs, handlers move untouched.

**Negative:**

- Form rules and VO rules can drift. Drift surfaces immediately as a 500 in development; "form ⊆ VO" is enforced in code review.
- One extra file per controller action; for 1-2-field forms this is overhead.

**Alternatives considered:**

- *`fromHttpInput` on Command/Query* (the prior ad-hoc convention, e.g. `ViewCatalogHomePageQuery::fromHttpInput`). Rejected: couples framework-agnostic messages to HTTP and locale, and forces the message class to grow per-endpoint policy (max page, default per-page, locale of error messages) it should not own.
- *Abstract `Form_Abstract` base class* (Template Method with `defineRules`/`afterValidate` hooks). Rejected: the shared surface is purely clerical, the value-bearing method (`toCommand`/`toQuery`) stays form-specific, and the Laravel-migration parent is `FormRequest`, not a project base — introducing one here only to remove it during migration.
- *Continue using FuelPHP `Validation` inline in controllers.* Rejected: the duplication-with-VO and controller-bloat problems are exactly what this ADR addresses.
