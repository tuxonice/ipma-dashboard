# IPMA Dashboard

[![CI](https://github.com/tuxonice/ipma-dashboard/actions/workflows/ci.yml/badge.svg)](https://github.com/tuxonice/ipma-dashboard/actions/workflows/ci.yml)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-8892BF.svg)](https://www.php.net/)
[![Symfony 7.1](https://img.shields.io/badge/Symfony-7.1-000000.svg?logo=symfony)](https://symfony.com/)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](https://phpstan.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Weather information dashboard for Portugal, powered by the
[IPMA Open API](https://api.ipma.pt/) and the
[`tuxonice/ipma-api`](https://github.com/tuxonice/ipma-api) PHP client.

> [!IMPORTANT]
> This project is unofficial and is not affiliated with, endorsed by, or maintained by IPMA, I.P.
> It only provides a visual dashboard for the public API available at https://api.ipma.pt/.

Built on a lightweight custom framework assembled from Symfony components
(`http-foundation`, `http-kernel`, `routing`, `dependency-injection`,
`event-dispatcher`, `translation`, `twig-bridge`, `cache`) with Twig templates
and Bootstrap 5 served from CDN.

## Requirements

- Docker + Docker Compose (no local PHP installation needed)

## Quick Start

```bash
# Build images and install Composer dependencies
make build && make composer-install

# Start the stack
make start

# Open the app at http://localhost:8080
```

Stop with:
```bash
make stop
```

See all available commands in the [Makefile](Makefile) or check [CLAUDE.md](CLAUDE.md)
for the complete command reference.

## Features

- **Forecast**: Weather forecasts, UV index, fire risk, sea state, and warnings
- **Observations**: Weather station data, seismic activity, climate data, bivalve status
- **Locations**: Browse districts and islands with multi-language support (PT/EN)
- **Caching**: PSR-16 filesystem cache for IPMA API responses (1-hour TTL)
- **Multi-language**: Portuguese (default) and English with locale switching
- **Progressive Web App**: Offline support and installable

## Project Structure

```
.github/workflows/    # GitHub Actions CI (phpcs, phpstan, phpunit)
docker/              # Dockerfile + Nginx config for local development
public/              # Web root with front controller (index.php)
src/
  Controller/        # Thin request handlers organized by feature area
    Forecast/        # Weather forecasts, warnings, UV, fire risk, sea state
    Observation/     # Stations, seismic, climate, bivalves
    Services/        # Location browsing
  Service/           # Data layer repositories organized by feature
    Forecast/
    Observation/
    Services/
  Framework/         # Core framework: kernel, DI, routing, locale, exceptions
  Kernel.php         # HttpKernel wiring and bootstrap
templates/           # Twig templates organized by feature
  Forecast/
  Observation/
  Services/
  error/             # Error page templates
tests/               # PHPUnit unit tests for domain helpers
translations/        # i18n catalogs (messages.pt.yaml, messages.en.yaml)
var/                 # Runtime cache and logs (gitignored)
docu/                # Project documentation and IPMA endpoint quirks
```

## Stack

- **PHP 8.4** (FPM) behind **Nginx**
- **Symfony 7.1 components** (HTTP, routing, DI, event dispatcher, translation, Twig bridge, cache)
- **Twig 3.10** for templating with auto-escaping
- **Bootstrap 5.3** + **Bootstrap Icons** via CDN (no build step)
- **`tuxonice/ipma-api` ^0.6** as the IPMA client (with caching)
- **`tuxonice/suncalc-php`** for astronomical calculations (sun/moon times and positions)
- **PHPUnit 11** for unit tests
- **PHPStan 2.1** for static analysis (level 8)
- **PHP CodeSniffer 4.0** for code style (PSR-12)

## Development

### Running Tests

```bash
# Run all tests
make test

# Run a specific test class
docker compose run --rm app vendor/bin/phpunit tests/Service/AwarenessLevelTest.php

# Filter by test name
docker compose run --rm app vendor/bin/phpunit --filter testSeverityOrdering
```

### Code Quality

The project uses automated checks via GitHub Actions CI:
- **phpcs** — PSR-12 code style validation
- **phpstan** — Static analysis (level 8)
- **phpunit** — Unit tests with strict settings (failOnWarning, failOnRisky)

### Shell Access

```bash
make shell
```

For more commands and architecture details, see [CLAUDE.md](CLAUDE.md).

## Data & Attribution

### Meteorological data
Weather, oceanographic, seismic and climate data is provided by the
[Instituto Português do Mar e da Atmosfera (IPMA)](https://www.ipma.pt/)
via the [IPMA Open API](https://api.ipma.pt/).

As required by IPMA's terms of use:
- **Source must always be cited** when reusing data from this dashboard.
- Data may be freely used for **personal or public non-commercial purposes only**.
  Commercial use requires prior authorisation from IPMA.
- For IPMA's full terms see [api.ipma.pt — legal](https://api.ipma.pt/#legal).

This dashboard is not affiliated with or endorsed by IPMA.

### Astronomical data
Sun and moon data (rise/set times, positions, phase, illumination) is calculated
locally using [tuxonice/suncalc-php](https://github.com/tuxonice/suncalc-php),
a PHP port of [Vladimir Agafonkin's suncalc](https://github.com/mourner/suncalc).
This data is **not** sourced from the IPMA API and carries no IPMA usage restrictions.

## License

MIT — see [`LICENSE`](LICENSE).