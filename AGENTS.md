# AGENTS.md

This is a Symfony project. Check `composer.json` for the exact Symfony/PHP version
in use, and read `symfony.lock` to see which recipes ran.

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
Lighthouse at both desktop and mobile widths. Tailwind's build is the CSS syntax
gate; the W3C CSS3 validator cannot parse its modern output.

Pure JavaScript logic uses `node:test` under `tests/Unit/`. Browser interactions
use Playwright under `tests/Browser/`, with isolated fixtures that load the real
controllers and AssetMapper vendor modules without Symfony or a database. Run
`npm run test:js` for both suites, or `npm run test:browser` for the browser
suite. Install its local Chromium first with `npm run test:browser:install`.
Browser failures are written under `var/browser-tests/`.

Run `npm run check:js` for the frontend gate: ESLint, the strict TypeScript
`checkJs` pilot and both test suites. The pilot is deliberately limited by
`jsconfig.json` to the pure rich-text calculation and shared helpers; expand it
module by module rather than enabling it wholesale over untyped Tiptap code.

## Code style

Symfony's coding standard, the `@Symfony` php-cs-fixer ruleset (a PSR-12-derived
superset). `composer cs` fixes PHP style and `composer twig:cs` fixes Twig style;
`composer check` runs the whole gate (style, phpmd, phpstan, schema validation, tests).

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
