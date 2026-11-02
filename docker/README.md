# Local infrastructure (Hetzner Webhosting L)

`compose.yaml` reproduces, as closely as shared hosting allows, the stack that
serves the app at Hetzner Webhosting L.

| Layer | Webhosting L | Local replica |
| --- | --- | --- |
| Edge | nginx (HTTP/2 + HTTP/3, TLS) | `nginx:stable-alpine` on `:8080`, plain HTTP |
| HTTP server | Apache 2.4.69 | `httpd:2.4.69` |
| PHP | 8.5 via FPM (selectable in konsoleH) | `php:8.5-fpm-trixie` |
| PHP processes | 10 | `pm.max_children = 10` |
| Memory per process | 384 MB | `memory_limit = 384M` |
| Database | MariaDB 11.8 | `mariadb:11.8` |
| Cache | Redis (included in L) | `redis:7-alpine` |
| Cron | 10 jobs | run `bin/console` from the host/container |

## Usage

```bash
docker compose up -d --build            # http://localhost:8080
docker compose exec -u www-data php bin/console about
docker compose exec -u www-data php composer require symfony/twig-bundle
docker compose run --rm -u www-data php bin/console cache:clear
docker compose logs -f apache php
docker compose down                     # add -v to drop the database
```

`-u www-data` matters: the image remaps `www-data` to your host UID/GID, so
files written through the container stay editable on the host and vice versa.

The database is reachable from the host only inside the compose network. To
inspect it, use `docker compose exec database mariadb -uapp -papp app`.

## Differences from production (on purpose)

- **No Docker in production.** Webhosting L is shared hosting: the code is
  deployed over SSH/FTP and served by Hetzner's nginx/Apache/PHP-FPM.
- **Varnish is not included.** The plan can enable it in front of Apache, but
  for a Symfony app with sessions and CSRF tokens it must be configured with
  pass rules; adding it later is a deliberate decision, not a default.
- **TLS/HTTP/3 are not reproduced.** HTTPS is terminated by Hetzner; local
  traffic is plain HTTP on port 8080.
- **The document root differs.** On the account, the web root is `public_html`.
  Either symlink it to `public/` or rename the directory (Symfony documents
  both approaches). Do not copy `public/` elsewhere: the front controller and
  asset paths assume the project layout.
- **No cron scheduler.** `bin/console` and the CLI PHP version on the account
  are the way to run commands; wire scheduled commands to the 10 cron jobs.
- **Redis 7 vs 8.** The exact Redis minor behind Webhosting L is not published;
  7.x is used here because it is the version the current Symfony recipes and
  clients are validated against.
