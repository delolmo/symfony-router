# Changelog

All notable changes to this project are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [4.1.0] - 2026-10-27

### Security

- Require PHP 8.4.27 or later on 8.4 and 8.5.12 or later on 8.5, the releases fixing CVE-2026-109618 and CVE-2026-109619.
- Require `symfony/routing` 8.0.13 or later; earlier 8.0 releases have published security advisories.
- Error responses carry the standard reason phrase instead of the routing exception's message, which could echo the requested path or reveal the routing configuration.

### Changed

- The constructor accepts any `RequestMatcherInterface&RequestContextAwareInterface`, such as a `UrlMatcher`, not only a `Router`. Passing a `Router` works as before.
- `symfony/config` is no longer required. Applications that load routes through `Router` and a file loader must require it themselves.
- `psr/http-factory` and `psr/http-server-handler`, whose interfaces the middleware uses, are required directly; `psr/http-message` 1.1 is the lowest version allowed.

## [4.0] - 2025-12-08

- Last release before this changelog.

[Unreleased]: https://github.com/delolmo/symfony-router/compare/4.1.0...HEAD
[4.1.0]: https://github.com/delolmo/symfony-router/compare/4.0...4.1.0
[4.0]: https://github.com/delolmo/symfony-router/releases/tag/4.0
