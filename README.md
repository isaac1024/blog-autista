# Blog autista

> El código de [blogautista.com](https://www.blogautista.com): la web pública
> y el CMS con el que la escribo. Es software libre, bajo la GPL versión 3 o
> posterior; la documentación del código está en inglés.

The code of [blogautista.com](https://www.blogautista.com), a blog written in
Spanish: the public site and the CMS its single editor writes it with.

## What it is

A Symfony application with two contexts that never read each other's tables:

- **Cms** (`cms.<domain>`): pages, posts, sections and tags, authors, images,
  external resources (videos, social posts, studies), redirects, the
  settings of the site and a first-party analytics report. One administrator,
  behind an HTTP password and a login.
- **Web** (`www.<domain>`): the site the readers see, built from the events
  the CMS publishes, served through Varnish, with search, listings, a
  sitemap and JSON-LD. It sets no cookies.

`Shared` holds the Twig components, the layouts and the wrappers of the
libraries both use.

## Stack

- PHP 8.5, Symfony 8.1, Doctrine ORM with migrations, MariaDB 11.8, Redis.
- Server-rendered Twig with Symfony UX Twig Components, Stimulus and
  AssetMapper (no build step but Tailwind's).
- Messenger and Scheduler driven by cron, as the hosting has no workers.
- Made for shared hosting (Hetzner Webhosting L); `compose.yaml` reproduces
  that stack locally.

## Running it

```bash
npm ci
docker compose up -d --build
docker compose exec -u www-data php bin/console doctrine:migrations:migrate
```

The site answers at <http://www.blogautista.localhost> and the CMS at
<http://cms.blogautista.localhost> (development password: `admin`).
[`docker/README.md`](docker/README.md) tells the rest: the hosts, the
assets, the images, the visit counts and how it is deployed.

## Checks

```bash
docker compose exec -u www-data php composer check   # audit, style, phpmd, phpstan, schema, tests
npm run check:js                                     # audit, ESLint, TypeScript, unit and browser tests
npm run ui:check                                     # HTML, contrast and Lighthouse of the running app
```

`composer check:full` and `npm run check:js:full` add the mutation runs
(Infection and Stryker). [`AGENTS.md`](AGENTS.md) holds the conventions
the code follows.

## Security

If you find a vulnerability, please report it privately through
[GitHub's security advisories](https://github.com/isaac1024/blog-autista/security/advisories/new)
rather than in a public issue.

## License

Copyright (C) 2026 Isaac Abreu

This program is free software: you can redistribute it and/or modify it
under the terms of the GNU General Public License as published by the Free
Software Foundation, either version 3 of the License, or (at your option)
any later version. See [`LICENSE`](LICENSE).

The fonts under `assets/fonts/` (Atkinson Hyperlegible Next) are under the
SIL Open Font License; see
[`assets/fonts/OFL-Atkinson-Hyperlegible-Next.txt`](assets/fonts/OFL-Atkinson-Hyperlegible-Next.txt).
The texts and images published on the blog are not part of this repository.
