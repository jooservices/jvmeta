# CI

GitHub Actions on GitHub-hosted runners. CI only: nothing is deployed and no
image is pushed. Deploy stays manual (`deploy/*.sh`).

## Workflows

| Workflow | Trigger | Jobs |
|---|---|---|
| `ci.yml` | push / PR to `develop`, manual | **Lint and test**: composer validate, `composer lint`, `composer test:coverage`, `composer coverage:check` (≥ 85%), coverage artifact · **Secrets scan**: gitleaks with `.gitleaks.toml` · **Build app image**: `Dockerfile.production`, no push |
| `embedder-image.yml` | changes under `embedder/` | build `embedder/Dockerfile.production`, no push |
| `workflow-audit.yml` | changes under `.github/`, weekly | actionlint (pinned image), zizmor (action uploads SARIF to code scanning) |
| `commitlint.yml` | pull requests | Conventional Commits, sentence-case subject (same rule as `captainhook.json`) |

All actions are pinned by commit SHA.

## Reproduce locally (same image as CI)

The gate runs inside `docker/ci/Dockerfile` (PHP 8.5, ext-mongodb, intl,
pcntl, pdo_pgsql, pcov, Composer). The host PHP is not used, so host
extension versions do not matter.

```bash
docker/ci/run composer install --no-interaction --prefer-dist
docker/ci/run composer validate --strict
docker/ci/run composer lint
docker/ci/run composer test:coverage
docker/ci/run composer coverage:check
docker build --file Dockerfile.production .
```

Workflow checks:

```bash
docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:latest
docker run --rm -v "$PWD:/repo" -w /repo ghcr.io/zizmorcore/zizmor:latest --offline .github/workflows
```

Tests use sqlite in-memory and an array cache (`phpunit.xml`), so no Postgres,
Redis or Mongo service is needed.
