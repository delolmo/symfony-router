# AGENTS.md

## Environment and tools

- PHP 8.4.27+ or 8.5.12+ on `PATH`, the releases fixing CVE-2026-109618 and CVE-2026-109619 (CI runs both), Composer v2, Git, `gh` for issues and PRs.
- Prefer the dedicated tools: Glob, Grep, Read, Edit, Write over `find`, `grep`, `cat`, `sed`, `echo`.
- One command per Bash call: no `|`, `&&` or `;`, except piping a composer script or `bin/` pipeline to `grep`, `head`, `tail` or `wc -l`.
- No self-credit in commits, PRs or any artifact; `bin/hook-block-self-credit` rejects it. In assessments and reviews, no praise: lead with gaps, blockers and next steps.

## Architecture

One public class, `SymfonyRouterMiddleware`, a PSR-15 middleware: it converts the PSR-7 request with `symfony/psr-http-message-bridge`, updates the router's context from it, matches it with `symfony/routing`, and stores the route parameters as request attributes; routing failures become 404, 405 or 500 responses from the injected PSR-17 factory. Test doubles live in `tests/Mock/`, named by behaviour (`Spy*` records calls).

### Dependency Philosophy

A public MIT library: every dependency is a cost every consumer pays.

1. Fewer is better; weigh transitive deps too. No production dependency for convenience alone.
2. PSR interfaces over concrete vendors wherever only the interface is needed.
3. Every lower bound in `require` is one the lowest-deps CI job proves: raise it rather than claim a version nothing tests.
4. Released: public API changes follow semver, and `CHANGELOG.md` records them.

## Commands

A composer script runs one tool and passes arguments through (`composer phpstan -- src`): `phpstan`, `psalm`, `phpunit`, `phpcs`, `phpcbf`, `rector` (dry run), `fix` (rector then phpcbf), `lint`, `deps`, `coverage`, `infection` (whole tree) and `infection-diff` (files changed against the default branch). Also `composer audit`, `composer validate --strict`, `composer normalize --dry-run`.

| Pipeline | Runs | Use |
|---|---|---|
| `bin/setup` | `composer install`, then `bin/check` | Fresh checkout or worktree |
| `bin/check` | Every tool but Infection, in parallel; prints only failed logs and a summary line (`CHECK_VERBOSE=1` for all) | An answer without a stamp |
| `bin/pre-commit` | `bin/check`, skipped when the tree is unchanged since its last pass | Once before committing; the commit hook trusts its stamp |
| `bin/pre-push` | `bin/ci`, same stamp rule | Before pushing |
| `bin/ci` | Every tool serially, then full Infection | CI |

### Validation Loop

Every step must pass with 0 errors.

1. `composer fix`
2. `composer phpstan` and `composer psalm`; on errors, back to 1.
3. `composer phpunit`
4. `composer infection`: 100% MSI, kill every surviving mutant with a test.
5. Once: `bin/pre-commit`.

## Coding Style

`composer fix` corrects formatting (Doctrine coding standard); these need judgment.

- Classes `final` (and `readonly` where state allows), properties private, methods private unless a caller needs more. Immutable designs, constructor injection with promotion.
- The narrowest type: no `mixed` where the domain is narrower, no `iterable` where an `array` is returned.
- Named arguments when calling a user-defined constructor with more than one argument.
- Git tags are bare semver: `1.2.0`.

### Type narrowing and annotations

- A type earlier logic established but the analyser cannot see → `assert()`, never `/** @var */` on a local.
- Docblocks only for what PHP types cannot express (generics, shapes, `list`, `class-string`, templates).
- No `@phpstan-ignore`, no baselines, and no edits to `phpstan.neon.dist`, `psalm.xml` or `infection.json.dist` to make a failure pass: fix the code or kill the mutant with a test.

### Comments

A comment states what the code and types cannot: a contract the signature hides goes in the method's docblock, a non-obvious reason in a short inline comment, history in the commit message and `CHANGELOG.md`, never the code. No summary that restates a name.

## Testing

- One test class per production class, `<Class>Test` under `tests/` in the `\Tests\` namespace, with `#[CoversClass]`; `#[UsesClass]` for production collaborators it runs.
- Names fuse subject, state and outcome, with a `#[TestDox]`. Arrange-Act-Assert; data providers for scenarios of one behaviour; static `Assert::assert*()` calls.
- Real objects by default: value objects, the real PSR-7 and Symfony objects. Stub (`createStub()`) only what has no cheap real implementation; `createMock()` only to assert a call pattern at a boundary.
- Fixtures live in `tests/Fixtures/`.

## Agents and Hooks

Agents, skills and the Bash hooks come from the `bluenostrum` plugin marketplace (`core` is the hooks, `php` the agents and skills), enabled in `.claude/settings.json`. They read this file for the conventions.
