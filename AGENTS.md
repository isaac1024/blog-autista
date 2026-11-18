# AGENTS.md

This is a Symfony project. Check `composer.json` for the exact Symfony/PHP version
in use, and read `symfony.lock` to see which recipes ran. Symfony is followed minor by minor
(8.1, 8.2, 8.3…) as each comes out, within its support window, not held to
an LTS: move the `8.1.*` constraints to the next minor once it is released,
with `composer check` and the full mutation runs green.

## Stack

Persistence is Doctrine ORM with migrations, on MariaDB. The interface is
server-rendered Twig with Symfony UX Twig Components. Auth is SecurityBundle with
`form_login`. Lock and Messenger are installed. For anything outside this stack,
ask rather than guess.

## Adding features: Flex, not hand-wiring

Install new capabilities with `composer require <package>` (e.g. `symfony/lock`,
`symfony/messenger`, `orm-pack`) and let the Flex recipe register the bundle and
generate its config. Don't hand-edit `config/bundles.php` or hand-write a bundle's
base config; that's what the recipe is for. Every new package, including making a
transitive dependency direct, is proposed to the user first and installed only
after they agree. When a Symfony component fits, propose it rather than
hand-writing the infrastructure.

## Conventions

Follow https://symfony.com/doc/current/best_practices.html to write idiomatic
Symfony:

- Use PHP attributes for framework metadata, and not only on controllers:
  `#[Route]`, `#[MapRequestPayload]`, `#[IsGranted]` on actions, `#[Assert\...]`
  on properties, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, and
  `#[AsAlias]` / `#[AsTaggedItem]` / `#[Autoconfigure]` on services. No YAML or
  XML routing.
- Rely on autowiring and autoconfiguration. Type-hint constructor arguments and
  let the container resolve them. Where a type-hint can't express it, stay in the
  class with `#[Autowire]` (parameters, env vars, expressions) or `#[Target]` (one
  of several implementations of an interface). A YAML service definition is the
  last resort, not the first.
- Controllers extend `AbstractController`, stay thin, and delegate to services.
- Use the framework for what it already does: Form for server-rendered forms,
  Validator for validation, Serializer for JSON, Messenger for async work,
  Security (voters, authenticators) for access control, Twig `path()`/`url()`
  instead of hardcoded URLs.
- Before hand-writing infrastructure (locks, queues, caches, HTTP clients,
  mailers, schedulers) or reaching for a third-party library, check whether a
  Symfony component covers it. It usually does.
- All Twig components live in `Shared\Infrastructure\Twig\Components`, with their templates
  under `templates/components/shared/` and the `Shared:` prefix. Layouts live in
  `Shared\Infrastructure\Ui` and `templates/shared/`. Contexts (`Cms`, `Web`) must not declare
  their own components or layouts; they compose the shared ones.
- `Cms` and `Web` never read each other's tables; the site learns what the CMS
  publishes from its events. The exceptions are the tables named `shared_*`,
  written by one context and read, read-only, by the other:
  - `shared_redirect`: the CMS writes it and `Web\Redirect` reads it, so a
    redirect applies the moment it is saved instead of after the queue runs
    (cron, every minute on Hetzner);
  - `shared_visit` and `shared_visit_total`: the site counts its visits in
    them and `Cms\Analytics` reads them for its report, today's included.

  A contract test on the side that reads (`tests/Web/Redirect`,
  `tests/Cms/Analytics`) fails when the columns it reads change. No other
  table is read across contexts.
- Within a context, a module reaches another through its Application layer
  (its finders, use cases and `*Response`), never through its Domain, whose
  rules are its own: an Infrastructure class may name a Domain class of
  another module only if it is a port (an interface, such as a repository,
  which that module's use cases take) or one of the contracts below. A use
  case takes primitives and builds its own value objects, as
  `LatestPostsFinder(?string $sectionId, ?string $authorId, array $tagIds,
  int $limit)` builds its `PostSelection`; a response tells what a caller
  needs to know of its domain, as `TermResponse::isSection()` or
  `PageResponse::isPublished()`. The contracts, the public API of a module's
  domain: `Cms\Redirect\Domain\{SiteResources, Resource, ResourceType,
  RedirectId, InvalidRedirect, IncomingDecision}`, which every module that
  publishes implements or uses to retire its contents, and
  `Cms\Relation\Domain\ResourceType`. PHPat holds this
  (`tests/Shared/Architecture/ModuleIndependenceRules.php`); a new contract is
  added there and here.

Three specifics worth spelling out, because they are easy to get wrong:

- Bind request data with `#[MapRequestPayload]` / `#[MapQueryString]` on action
  arguments, which wires up Serializer and Validator for you, instead of calling
  `json_decode()` or `SerializerInterface` by hand. If neither package is
  installed yet, propose installing them rather than falling back to manual
  parsing.
- Use constructor property promotion, and `readonly` for DTOs and value objects.
- Use `symfony/lock` (`LockFactory`) for mutual exclusion. A hand-built flag or
  lock file looks fine in review and is usually wrong under concurrency.

## Everyday workflow

- The app runs in Docker Compose, a local copy of the Hetzner hosting stack:
  `docker compose up -d --build`, then commands with
  `docker compose exec -u www-data php bin/console ...` (Composer:
  `docker compose run --rm -u www-data php composer ...`).
- When something fails, read the logs under `var/log/` before changing code.
- If `maker-bundle` is installed, prefer `bin/console make:*` with every argument
  passed up front and `--no-interaction` where supported: makers prompt on a
  terminal by default, which hangs a non-interactive shell. If a maker still
  needs interactive input, hand-write the code instead.
- Schema changes go through migrations (`bin/console make:migration`, then
  `doctrine:migrations:migrate`), never `doctrine:schema:update` or hand-written SQL.
- `.env` is committed and holds defaults only. Real secrets belong in `.env.local`
  (git-ignored) or the secrets vault (`bin/console secrets:set`), read via
  `%env(...)%`.

## Testing

Functional/HTTP tests extend `WebTestCase`; service-level tests extend
`KernelTestCase`. Run `composer test` (paratest), or `composer test:serial` for
plain PHPUnit. A task isn't done until
it has tests that exercise it the way a caller would, an HTTP request for a
controller or a service call for a service, not just "it didn't throw." A task
done step by step can leave its testing for the final step.

Don't test the interface with PHPUnit. No `assertSelector*` and no assertions on
markup, CSS classes or UI copy. PHPUnit covers HTTP behavior (status codes,
redirects, authentication, routes) and services; HTML, accessibility and visual
checks belong to the `ui-check` harness (`npm run ui:check`). The harness runs
Lighthouse at both desktop and mobile widths, SEO included at 100; it leaves
out `is-crawlable`, which the `X-Robots-Tag: noindex` of `dev` always fails.
Tailwind's build is the CSS syntax gate; the W3C CSS3 validator cannot parse
its modern output.

The JavaScript tests are a pyramid. At its base, `node:test` under `tests/Unit/`
covers the pure modules (`assets/external/`, `assets/form/`, `assets/image/`,
`assets/reading/`, `assets/rich_text/`, `assets/shared/`). Above them, the Stimulus controllers and `assets/web.js` run
under `node:test` too, on happy-dom: `tests/Unit/support/dom.js` mounts a real
Stimulus application on the markup their templates write, kept in
`tests/Markup/`, and the tests act through its events, as a person would, never
by calling a controller's methods or reading its state. That helper fails a test
whose controller threw, and `assertFocused` checks the focus by identity: never
pass a DOM node to an assertion, not even against `null`. A failing one prints
the whole window behind it, gigabytes per process, which under Stryker took the
machine down; compare a boolean or what the node says (ESLint forbids the usual
forms). At the top,
Playwright under `tests/Browser/` loads the same markup and the real controllers
and AssetMapper vendor modules in Chromium, without Symfony or a database, for
the wiring only a real browser checks. Run `npm run test:js` for both suites, or
`npm run test:browser` for the browser suite. Install its local Chromium first
with `npm run test:browser:install`. Browser failures are written under
`var/browser-tests/`.

`npm run mutation:js` runs Stryker over every module under `assets/` but the
vendored ones, as Infection does for PHP: it ignores nothing and fails below
80 %; the report goes to `var/mutation/js.html`. Logic that can live outside a
Stimulus controller goes to a module of its own with a `node:test` suite. A
mutant that survives is fixed by a test or by rewriting the code that makes it
equivalent, not ignored. The whole run stays under five minutes.
`npm run check:js:full` is `check:js` plus the mutation run.

Run `npm run check:js` for the frontend gate: ESLint, strict TypeScript
`checkJs` over every module under `assets/` (JSDoc, no build step) and both test
suites. The browser loads packages through the import map; npm installs the same
versions only for their types, and `tests/Unit/type_packages.test.js` fails when
the two drift, so bump both together. A Stimulus controller declares its targets
and values in a `Properties` typedef and extends
`ControllerWith<Properties>` from `assets/controllers/controller_types.d.ts`.
Endpoint responses are `unknown` until cast to the shape the endpoint documents.

A Content-Security-Policy (`config/packages/nelmio_security.yaml`) lets only the
site's own files run and style the pages. No inline `<script>`, `<style>` or
`style="…"` (the one exception is the JSON-LD block of `page.html.twig`, a
`<script type="application/ld+json">` the browser never runs, which search
engines only read inside the page): a dynamic size is a class (see the `@source inline()` ranges in
`app.css`), and a stylesheet is linked from a layout, never imported from
JavaScript, which AssetMapper would turn into a `data:` module. The CMS import
map is the one inline script, with `csp_nonce('script')`; the public site uses
no nonce, so Varnish can cache its pages. Without an import map there, a
module of the site imports the ones beside it by relative paths, which
`Shared\Infrastructure\AssetMapper\VersionedImports` rewrites to their
versioned addresses; the test servers serve plain paths, so only a page of the
running app shows a broken import.

## Code style

Symfony's coding standard, the `@Symfony` php-cs-fixer ruleset (a PSR-12-derived
superset). `composer cs` fixes PHP style and `composer twig:cs` fixes Twig style;
`composer check` runs the whole gate (security audit of Composer and of the import map, style, phpmd, phpstan, schema validation, tests).

## Discover, don't guess

Framework APIs change between versions and your training data may be stale. Look
things up in the project instead of relying on memory:

- `bin/console about`: versions, environment, paths.
- `bin/console debug:router`, `debug:container`, `debug:autowiring <name>`,
  `debug:config <bundle>`, `config:dump-reference <bundle>`: what exists and how
  it is configured.
- `bin/console lint:container`, plus `lint:twig templates/` and
  `lint:yaml config/` where those packages are installed: validate before running.
- Read the installed source and docblocks under `vendor/`.
- Docs: https://symfony.com/doc/current/ (switch to the version matching
  `composer.json` if it differs).
