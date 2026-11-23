# Local infrastructure (Hetzner Webhosting L)

`compose.yaml` reproduces, as closely as shared hosting allows, the stack that
serves the app at Hetzner Webhosting L.

| Layer | Webhosting L | Local replica |
| --- | --- | --- |
| Edge | nginx (HTTP/2 + HTTP/3, TLS) | `nginx:stable-alpine` on `:80`, plain HTTP |
| HTTP server | Apache 2.4.69 | `httpd:2.4.69` |
| PHP | 8.5 via FPM (selectable in konsoleH) | `php:8.5-fpm-trixie` |
| PHP processes | 10 | `pm.max_children = 10` |
| Memory per process | 384 MB | `memory_limit = 384M` |
| Database | MariaDB 11.8 | `mariadb:11.8` |
| Cache | Redis (included in L) | `redis:7-alpine` |
| Cron | 10 jobs | run `bin/console` from the host/container |

## Usage

```bash
npm ci                                  # the packages the CMS runs in the browser
docker compose up -d --build            # http://www.blogautista.localhost
docker compose exec -u www-data php bin/console about
docker compose exec -u www-data php php bin/phpunit
docker compose exec -u www-data php bin/console doctrine:migrations:migrate
docker compose exec -u www-data php bin/console tailwind:build --watch
docker compose exec -u www-data php bin/console messenger:consume scheduler_default -vv
docker compose exec -u www-data php composer require symfony/twig-bundle
docker compose run --rm -u www-data php bin/console cache:clear
docker compose logs -f apache php
docker compose down                     # add -v to drop the database
```

`-u www-data` matters: the image remaps `www-data` to your host UID/GID, so
files written through the container stay editable on the host and vice versa.

The database is only reachable inside the compose network, so run Doctrine and
Messenger commands through `docker compose exec`. To inspect it, use
`docker compose exec database mariadb -uapp -papp app`.

Compose reads `DEFAULT_URI` and `DATABASE_URL` from `.env`; values exported in
the shell take precedence. `REDIS_URL`, `MARIADB_PASSWORD` and
`MARIADB_ROOT_PASSWORD` can also be overridden, with local-only defaults when
they are unset. When changing the local database password, update both
`MARIADB_PASSWORD` and `DATABASE_URL` so the database and application agree.
The database and user names stay `app`, as the test database initialization
grants privileges to that user.

## Application components

| Need | Package | Notes |
| --- | --- | --- |
| Database | `symfony/orm-pack` | Doctrine ORM + migrations, MariaDB 11.8 |
| Events | `symfony/messenger` + `symfony/doctrine-messenger` | Domain events go through `Shared\Domain\Event\EventBus` to the Doctrine `async` transport; the `worker` service consumes it in development (`docker compose logs -f worker`), cron in production |
| Cronjobs | `symfony/scheduler` | `#[AsSchedule]` in `src/Shared/Infrastructure/Scheduler/Schedule.php`, plus tasks of a context such as `#[AsPeriodicTask]` on `Web\Analytics\Infrastructure\Scheduler\NightlyVisitCount`; consume with `messenger:consume scheduler_default` |
| Visit counts | `symfony/rate-limiter` + Redis | The site counts its visits itself, with no third party: see [Visit counts](#visit-counts) |
| Security | `symfony/security-bundle` | The CMS has one administrator, read from `CMS_USER_IDENTIFIER` and `CMS_USER_PASSWORD_HASH` (the hash, kept in the `.env.local` of the server in production) by `Cms\Authentication\Infrastructure\Security\AdministratorUserProvider`; `form_login` in `config/packages/security.yaml` |
| Templates | `symfony/twig-bundle` + `symfony/ux-twig-component` | Every component lives in `Shared\Infrastructure\Twig\Components` (`Shared:` prefix, templates in `templates/components/shared/`); the contexts compose them |

Doctrine mappings are declared per module (`src/Cms/Page/Domain`) and
migrations live in
`src/Shared/Infrastructure/Persistence/Doctrine/Migrations`. The
`app_test` database is created by `docker/mariadb/init/01-test-database.sql`.

On Webhosting L, two of the 10 cron jobs are short-lived consumers, every
minute: `bin/cron/scheduler`, which also counts the visits of the day just
over, every night, and `bin/cron/async`, for domain events, such as
publishing a page (see "Deploying").

## Visit counts

Each page on the site sends its visit, once its reader sees it, from
`assets/web.js` to `POST /v`, on the site's own host, with no cookie. The visit
waits in `shared_visit` until its day (in Madrid) is over; every night
`NightlyVisitCount` counts it into `shared_visit_total` and deletes it, so what is
kept tells no reader from another. Readers of a day are told apart by a keyed
hash of their address and browser, keyed with a salt of the day kept in Redis
(`web.visit_salt.cache`), never in the database. A limiter, also in Redis,
takes 60 visits a minute per reader.

- `REDIS_URL` names Redis: the `redis` service locally,
  `redis:///run/redis_<login>/redis.sock` on the account (enable Redis in
  konsoleH first). Symfony needs `ext-redis` for it, which `composer.json`
  declares: `composer check-platform-reqs` tells whether the account has it.
- `SYMFONY_TRUSTED_PROXIES=private_ranges` makes Symfony read the reader's
  address from the proxies in front of PHP. If the site logs "A visit came from
  a private or loopback address", the proxies aren't trusted, and every reader
  counts as one.
- Check how Redis behaves when it reaches the memory konsoleH gives it: if it
  evicts the salt, the readers of that day count twice.

## Code quality (DDD / SOLID / clean code)

| Gate | Tool | Configuration |
| --- | --- | --- |
| Style | PHP-CS-Fixer | `@Symfony` + `@PHP85Migration`, strict types, strict comparisons, strict `in_array`, `void` returns, ordered class elements |
| Twig style | Twig CS Fixer | Symfony standard over `templates/` |
| Complexity and size | PHPMD | cyclomatic ≤ 10, NPath ≤ 200, methods ≤ 60 lines, parameters ≤ 6, classes ≤ 500 lines, WMC ≤ 50, coupling ≤ 20, inheritance depth ≤ 4, naming/clean-code guardrails |
| Types and SOLID | PHPStan | level **max**, strict rules, Symfony + Doctrine + PHPUnit extensions |
| Dead code | `shipmonk/dead-code-detector` | methods, constants, enum cases and properties; understands Symfony DI and PHPUnit |
| Architecture | PHPat | `Web` ⇏ `Cms`, `Cms` ⇏ `Web`, `Shared` ⇏ both; Infrastructure → Application → Domain in every module; a module's infrastructure never uses another module's |
| Tests | PHPUnit + ParaTest | in parallel, one database per process; random order, strict output, fail on risky/deprecation/notice/warning |
| JavaScript static analysis | ESLint + TypeScript `checkJs` | recommended correctness rules and strict JSDoc checking on every module under `assets/` (`jsconfig.json`); no emitted build |
| JavaScript tests | node:test + happy-dom + Playwright | pure modules, then the Stimulus controllers on happy-dom with the markup of `tests/Markup/`, then the wiring in Chromium; neither Symfony nor a database |
| JavaScript mutation | Stryker | every module under `assets/`; fails below 80 %, report in `var/mutation/js.html` |
| Mutation | Infection | in parallel, one database per thread, the whole suite against each mutant; MSI ≥ 70, covered MSI ≥ 80, excludes Kernel and generated migrations |

```bash
composer check        # fast gate: style, Twig, PHPMD, PHPStan, schema, PHPUnit
composer check:full   # check + mutation testing
composer cs           # apply PHP coding style
composer twig:cs      # apply Twig coding style
composer complexity   # PHPMD only
composer stan         # PHPStan (types + dead code + architecture)
composer test         # PHPUnit in parallel (ParaTest)
composer test:serial  # PHPUnit in a single process, handy to debug a failure
npm run check:js      # ESLint + JSDoc types + JavaScript and browser tests
npm run test:js       # JavaScript units + Playwright browser interactions
npm run test:browser  # Playwright browser suite only
npm run test:browser:install  # first time only: Chromium inside node_modules
npm run mutation:js   # Stryker over assets/
npm run check:js:full # check:js + mutation:js
npm run ui:check      # HTML, contrast and Lighthouse of the running app
composer infection    # mutation testing of the whole src/
composer infection:diff  # mutation testing of the lines changed against master
```

Tests run in parallel: ParaTest workers and Infection threads each get a
`TEST_TOKEN`, and `doctrine.yaml` appends it to the database name, so every
process owns `app_test<token>`. `tests/bootstrap.php` creates and migrates that
database the first time and leaves a marker in `var/test-databases/`, keyed by
token and migration set, so later runs skip the work and a new migration
triggers it again. If you recreate the database volume, delete that directory.
The MariaDB init script grants the `app` user every `app_test%` database.

`composer stan` warms the dev cache first because the dead-code detector and the
Symfony extension need `var/cache/dev/Shared_KernelDevDebugContainer.xml`.
Infection requires a coverage driver: the PHP image ships PCOV (development
only), so run it inside the container, where the database is reachable:
`docker compose exec -u www-data php composer infection`.

Architecture rules live in `tests/Shared/Architecture/` (`ContextIndependenceRules`,
`HexagonalArchitectureRules`, `ModuleIndependenceRules`) and are registered in PHPStan with the `phpat.test` tag. Since selectors include
subnamespaces, the boundaries also apply to `Web\Tests` and `Cms\Tests`.
`src/Shared/Infrastructure/Persistence/Doctrine/Migrations` is generated code and is excluded from PHPStan, PHPMD,
PHP-CS-Fixer and Infection.

These are guardrails, not dogma: adjust the thresholds in `phpmd.xml`, the level
in `phpstan.dist.neon`, the MSI values in `infection.json5`, and the fixer rules
in `.php-cs-fixer.dist.php` as the domain grows.

## Development hosts

| Context | URL | Env var |
| --- | --- | --- |
| Web | http://www.blogautista.localhost | `WEB_HOST` |
| CMS | http://cms.blogautista.localhost | `CMS_HOST` |

`.env` contains these development defaults. Routing is host based: each context
only answers on its own domain (see `config/routes.php`), and routes are only
discovered inside `src/Web/` and `src/Cms/`. Production must set `WEB_HOST` and
`CMS_HOST` to the real domains and clear the cache, because routes are compiled
and cached. On shared hosting, where arbitrary environment variables are not
always available, set them in `.env.local` on the account instead.

The local stack publishes port 80, so the development URLs mirror production
minus TLS.

Most browsers resolve `*.localhost` to 127.0.0.1 automatically. If yours does
not, add these entries to `/etc/hosts`:

```
127.0.0.1 www.blogautista.localhost cms.blogautista.localhost
```

## Assets

Stylesheets and scripts go through AssetMapper, which serves them under
versioned URLs, so browsers never keep a stale copy. In development it
compiles them on each request; only the stylesheet needs a build, which the
Tailwind bundle does from `assets/styles/app.css`. Keep the watcher above
running while changing templates or styles: in the dev environment the
bundle fails on purpose when there is no built stylesheet.

Shared hosting can't run the Tailwind binary, so `bin/deploy` builds the
stylesheet and compiles the assets here, and uploads `public/assets/`
without deleting the files already there: Varnish keeps pages that name the
versioned files of the release before, and they must still be found until it
lets them go. It builds under `var/deploy/build`, never in the project:
while `public/assets/` exists here, development serves those compiled copies
instead of the current files.

`error:dump` writes the error page as static HTML to `public/error/`, which
Apache serves (`ErrorDocument` in `public/.htaccess`) when PHP doesn't answer
at all. `bin/deploy` runs it on the account, after the assets are there.

## Images

The files uploaded to the CMS live out of `public/`, in `media/`, and are no
public files: the CMS serves each original through
`/images/{id}/file`, behind its login, so neither the web server nor another
host maps that directory. The site serves only its own copies of the published
images, from `public/media/`, under `/media/`.

## Deploying

`bin/deploy` deploys the commit checked out to the Hetzner account, in
place, once it has passed every gate: unless it already did, it runs
`bin/verify` first. That runs `ui:check` alone, after Tailwind's build, as
Lighthouse needs a quiet machine, then `composer check:full` and
`npm run check:js:full` side by side, mutations included, the first to fail
stopping the other, all on 8 cores; each writes its output under
`var/verify/`, and a commit that passed is noted there. It needs the app
running in Docker. Then it builds the release here (the dependencies without the development
ones, the stylesheet and the compiled assets), uploads it to a staging
folder of the account, checks the account before touching the site (PHP's
extensions, GD's WebP and JPEG, an empty queue of events, the files only the
account keeps), copies it over the site, migrates the database, clears the
cache and writes the static error pages. `bin/deploy --build-only` stops
after the build, under `var/deploy/build`. `bin/smoke <https://site>
<https://cms>` then checks what only the hosting shows.

On the account the project lives in `public_html/blog/` and the domain's
document root is `public_html/blog/public`: the folder above the web root
can't be ours there, so the project sits inside it, and the `.htaccess` of
`public_html/` (written by the first deploy) and of the project deny it all
but `public/`, which allows itself again.

The first time:

1. On this machine, `.deploy.env` (git-ignored):

   ```bash
   DEPLOY_SSH=<login>@<server>
   DEPLOY_PORT=<SSH port of the account>
   DEPLOY_PATH=/usr/home/<login>/public_html/blog
   DEPLOY_PHP=/usr/bin/php85
   SMOKE_SITE=https://www.<domain>   # optional, once the domains answer:
   SMOKE_CMS=https://cms.<domain>    # bin/smoke after every deploy
   ```

2. On the account, before anything else, the web root answers nothing, so
   no file of the project is ever served while it lands there:

   ```bash
   printf 'Require all denied\n' > ~/public_html/.htaccess
   ```

3. On the account, `public_html/blog/.env.local`: every setting and secret
   of production lives there, and nowhere else; nothing of it is in the
   repository. Create it readable by its owner alone, before writing
   anything in it:

   ```bash
   mkdir -p ~/public_html/blog
   install -m 600 /dev/null ~/public_html/blog/.env.local
   php85 -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'   # APP_SECRET
   nano ~/public_html/blog/.env.local
   ```

   The app connects as the user of the database that only reads and writes
   (`_w`); its password, as every password in a URL here, of letters,
   digits, `-` and `_` only (`DATABASE_URL` is read with `resolve:`, which
   takes a `%` for a parameter). The hash of the administrator's password
   comes after the first deploy (step 6):

   ```bash
   APP_ENV=prod
   APP_DEBUG=0
   APP_SECRET=<the line above>
   DEFAULT_URI=https://www.<domain>
   WEB_HOST=www.<domain>
   CMS_HOST=cms.<domain>
   CMS_USER_IDENTIFIER=<the administrator>
   CMS_USER_PASSWORD_HASH=
   CMS_GATE_USER=<the user of the HTTP password>
   CMS_GATE_PASSWORD_HASH=
   DATABASE_URL="mysql://<user>_w:<password>@<database server>:3306/<database>?serverVersion=11.8.6-MariaDB&charset=utf8mb4"
   REDIS_URL=redis:///run/redis_<login>/redis.sock
   VARNISH_PURGE=1
   ```

4. On the account, `~/.blog-migrate.env`, out of the web root and as
   private (`install -m 600 /dev/null ~/.blog-migrate.env`): the
   `DATABASE_URL` of the owner of the database, which may change tables,
   for the migrations alone.

5. `bin/deploy`. Then, in konsoleH, the document root of the domain and of
   the CMS's subdomain to `blog/public`. Every deploy keeps both files
   readable by their owner alone, and writes `.env.local.php` from
   `.env.local`.

6. On the account, the hashes of the administrator's password and of the
   HTTP password the CMS asks before anything else (another one), which
   the passwords themselves never leave:

   ```bash
   cd ~/public_html/blog
   php85 bin/console security:hash-password   # once for each
   nano .env.local      # CMS_USER_PASSWORD_HASH='<the hash>' and CMS_GATE_PASSWORD_HASH='<the other>', between single quotes
   php85 "$(command -v composer)" dump-env prod && chmod 600 .env.local.php
   ```

   Then open the CMS once: it checks the administrator on its first
   request.

   The settings of the site and its unique pages exist once and are made
   from the console, as drafts the CMS then fills and publishes. The slug
   of each page comes from its title, the home page aside; `contact` and
   `legal_notice` only if the blog has them:

   ```bash
   php85 bin/console cms:settings:create "<name of the blog>"
   php85 bin/console cms:page:create-unique home "<title>"
   php85 bin/console cms:page:create-unique search "<title>"
   php85 bin/console cms:page:create-unique privacy_policy "<title>"
   php85 bin/console cms:page:create-unique cookie_policy "<title>"
   ```

7. The cron jobs, in konsoleH's Cron job Manager, with Bash as the
   interpreter and the mail notification on:
   `/usr/home/<login>/public_html/blog/bin/cron/async` and
   `.../bin/cron/scheduler` every minute, with "exclusive execution", so a
   run a slow message stretches past the minute makes the next one skip;
   `.../bin/cron/optimize` monthly, at night; `.../bin/cron/health` daily,
   in the morning, which writes, and so mails, only what went wrong
   (`app:health`: events failed for good or waiting for long, errors and
   warnings in the log of yesterday, visits left uncounted).

8. `bin/smoke https://www.<domain> https://cms.<domain>` once the domains
   answer over https.

After that, `bin/deploy` is all. It refuses to deploy while the queue of
events holds messages, since those of one release can't be read by the
next: wait for the cron to consume them, a minute at most.

## Production checklist

- `APP_ENV=prod` and `APP_DEBUG=0`. Left in `dev`, the app answers every
  host but this machine's with a bare 503, so the mistake shows at once
  instead of opening the CMS with the administrator of `.env.dev`.
- In konsoleH (PHP configuration), enable OPcache, so PHP doesn't compile the
  app again on every request, and APCu, which Symfony's system cache (Doctrine
  metadata, validation, serialization) uses on its own when it finds it.
- Run `composer check-platform-reqs --no-dev` on the account over SSH. It
  fails when PHP lacks an extension the app needs at runtime: `gd` (with
  WebP and JPEG), `intl`, `redis`, `pdo_mysql`, `dom` and `fileinfo`, all
  declared in `composer.json`. It can't tell whether GD reads WebP and JPEG:
  check `php -r 'var_dump(gd_info());'` too.
- `APP_SECRET`: random and non-empty, for example `php -r 'echo bin2hex(random_bytes(32));'`.
  `.env` leaves it empty on purpose, and the CMS refuses to authenticate with
  an empty one.
- `CMS_GATE_USER` and `CMS_GATE_PASSWORD_HASH` in `.env.local` on the
  account: the HTTP password every request to the CMS host asks before the
  app, so bots find no login form to try (`Cms\Authentication\Infrastructure\Security\CmsGate`).
  Previews and `robots.txt` stay open. Past ten wrong passwords in a quarter
  of an hour, an address is refused without checking them. Production
  refuses the CMS without them; development and the tests go without.
- `CMS_USER_IDENTIFIER` and `CMS_USER_PASSWORD_HASH` in `.env.local` on the
  account: the hash of a strong password, never the password, which
  `bin/console security:hash-password` makes on the account. Put the hash
  between single quotes: it holds `$`, which a `.env` file otherwise reads
  as a variable. To change the password, hash a new one, replace it and run
  `composer dump-env prod` there, or deploy again. The CMS refuses the hash of the
  development password, `admin`, in production. Both checks run when the
  CMS first needs the administrator, on the first request to its host, not
  when the app boots, so open the CMS right after deploying.
- `WEB_HOST`, `CMS_HOST`, `DEFAULT_URI`, `DATABASE_URL`,
  `MESSENGER_TRANSPORT_DSN`, `MESSENGER_FAILURE_TRANSPORT_DSN` and
  `REDIS_URL` with the real values, in `.env.local` on the account. Clear the
  cache after changing the hosts, since routes are compiled with them.
- `bin/deploy` refuses to deploy while the queue of `async` holds messages:
  they are kept with PHP's `serialize()`, so one queued by a release whose
  message had other fields can't be read by the next. Inspect what lands in
  the `failed` transport with `messenger:failed:show` and, rather than
  retrying it, publish again from the CMS whatever it concerned.
- A page published at an address another page had until just before waits
  for the event that frees it: the site tries again a minute later, up to the
  three retries of `async`. If it still lands in `failed`, the event that
  frees the address failed first: retry that one, then the page.
- Changing the password or `CMS_USER_IDENTIFIER` signs out every CMS
  session. Changing `APP_SECRET` does too, and also invalidates every preview
  link already handed out: rotate it only when needed, and say so to whoever
  holds a preview link.
- The cron jobs never overlap themselves: konsoleH runs them with
  "exclusive execution", and `bin/cron/async` stops after 50 seconds, well
  under the minute between runs. Consumers side by side would be safe
  (projections run in read committed and lock only the rows they change,
  and a deadlock is retried by Messenger), but each one is a PHP process of
  the 10 the account has.
- Search: once a month, at night, a cron job runs `OPTIMIZE TABLE web_post`
  (`bin/console dbal:run-sql 'OPTIMIZE TABLE web_post'`), which compacts the
  full-text index after many posts are published and republished. A search
  that would take longer than `web.post.search_time_limit` (1.5 s) asks for
  another word, and an address may search 20 times a minute (`web_search` in
  Redis), so a sweep of words can't take the 10 PHP processes.
- The sessions of the CMS live in `var/sessions/prod`, in the account, not
  where the php.ini of the hosting keeps those of every account: keep
  `var/sessions` between deploys (upload over it, never replace `var/`), or
  every deploy signs the editor out. How long an idle session lasts is the
  app's too (`gc_maxlifetime`, 8 hours, in `config/packages/framework.yaml`),
  whatever the php.ini of the hosting says: only the app sweeps that folder.
- The logs are files in `var/log`, since the stderr of PHP and the output of
  the cron reach no file of the account: `prod-<date>.log` holds each
  request or command that failed, with what led to it, and the warnings,
  such as untrusted proxies; `messenger-<date>.log` holds the messages the
  cron's consumers failed, retried or sent to `failed`. Two weeks of each are
  kept (`config/packages/monolog.yaml`). A request the client asked wrong
  (4xx) is no error there. Keep `var/log` between deploys, as `var/sessions`.
- Compression and the cache of the assets, which `public/.htaccess` asks of
  Apache: `curl -sI -H 'Accept-Encoding: gzip' https://<site>/` carries
  `Content-Encoding: gzip` (or `br`, if Hetzner's nginx compresses first),
  and `curl -sI https://<site>/assets/<a compiled file>` carries
  `Cache-Control: public, max-age=31536000, immutable`. If the HTML comes
  uncompressed, the PHP handler of the account skips the `.htaccess` filter:
  ask Hetzner whether its nginx compresses.
- HSTS: `curl -sI https://<site>/` and `curl -sI https://<cms>/login` each
  carry `Strict-Transport-Security: max-age=31536000` **once**. Nelmio sends it
  in `prod`; if Hetzner's nginx adds its own too, turn one of them off. And
  `curl -sI http://<site>/` answers with a redirect to `https://`, not a loop.
- Varnish keeps an address of the site with nothing at it a minute
  (`s-maxage=60`), so bots sweeping for `/wp-login.php` or `/.env` don't take
  the 10 PHP processes: `curl -sI https://<site>/no-existe.html` twice in a
  row, and the second answer carries an `Age` above 0. The CMS host never
  does, and a failure (5xx) is never kept.

## Differences from production (on purpose)

- **No Docker in production.** Webhosting L is shared hosting: the code is
  deployed over SSH/FTP and served by Hetzner's nginx/Apache/PHP-FPM.
- **Varnish is not included locally.** On the account it runs in front of
  Apache for the site's host only, set in konsoleH (Einstellungen →
  Varnish-Konfiguration): "Nocache URLs" `^/v$`, so every visit reaches PHP,
  and "Strip URL Parameter" `utm_[a-z]+`, `fbclid` and `gclid`, so links shared
  with a campaign don't split the cache (the page still reads them). Any
  other parameter still reaches PHP, a copy per address: if the field takes
  a negation, strip every parameter but `pagina` and `q` instead, which are
  the only ones the site reads. Leave it
  off for the CMS host: Varnish strips the cookies it isn't told to keep, the
  CMS session among them; in konsoleH, the CMS's subdomain has its own
  settings with the Pipe URL `.*`, so nothing of it is cached, and the
  static extensions leave out `xml` and `txt`, so the sitemap, the feed and
  robots.txt keep the lifetime the app tells. Varnish takes a PURGE (and a
  BAN) from the account's server alone, where the cron applies what the CMS
  publishes: with `VARNISH_PURGE=1`, a post, page, author, section, tag or
  image taken off the site, and a post or page corrected, is forgotten at
  once (`Web\Shared\Infrastructure\Cache\SiteCachePurge`); a purge that
  fails is a warning of the log, which `app:health` tells. Nothing else
  purges it: each page tells how long to keep
  it with `s-maxage`, 5 minutes for every page (so a new post soon shows in
  the lists and mosaics, and one corrected or taken off soon shows so), and
  a day for an image file (browsers keep that one a year). Check
  once contracted that Varnish honours `s-maxage` rather than its own TTL for
  dynamic content (`curl -sI` a section twice, five minutes apart, and read
  `Age`); if it doesn't, set its dynamic TTL to 5 minutes, for the whole site.
- **TLS/HTTP/3 are not reproduced.** HTTPS is terminated by Hetzner; local
  traffic is plain HTTP on port 80.
- **The document root differs.** On the account, the project lives in
  `public_html/blog/` and the document root of each domain is
  `blog/public` (see "Deploying"); here it is `public/` of the project.
- **No cron scheduler.** `bin/console` and the CLI PHP version on the account
  are the way to run commands; wire scheduled commands to the 10 cron jobs.
- **Redis 7 vs 8.** The exact Redis minor behind Webhosting L is not published;
  7.x is used here because it is the version the current Symfony recipes and
  clients are validated against.
