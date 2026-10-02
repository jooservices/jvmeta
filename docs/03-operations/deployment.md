# Internal deployment runbook

Status: planned source-driven deployment. This runbook has not been used to
complete a production cutover.

## Deployment model

The current private deployment uses GitHub as the source of truth. The target
VM pulls the requested branch, builds the local Docker runtime, installs the
locked dependencies, runs the migration, and starts the selected Compose
services.

This flow does not transfer application images with `scp`, publish images to a
registry, or store image archives in GitHub. Docker still creates local images
from the repository Dockerfiles and may pull their pinned base images.

The existing `docker-compose.yml` remains the deployment Compose file for this
internal flow. `Dockerfile.production`, `compose.production.yml`, and the
production image workflow are preparation for a future immutable-image flow;
they are not used by the scripts below.

## Service roles

The control VM runs:

- `api`
- `scheduler`
- `mcp`
- `embedder`

The control VM must not run `worker` or `flaresolverr`. Those belong to crawler
VMs. PostgreSQL, MongoDB, Elasticsearch, and OpenObserve remain external and
are configured through the private `.env` file.

Only one control VM runs `scheduler`; starting multiple schedulers duplicates
scheduled work and alerts.

## Preconditions

The target VM must have:

- Git with authenticated access to the private GitHub repository.
- Docker Engine enabled at boot.
- Docker Compose v2.
- The `joos` user allowed to run Docker.
- Network access to GitHub, the external dependencies, and Python package/model
  sources required by the embedder build.
- A private runtime `.env` file containing the external service configuration.

The current Compose file bind-mounts these sibling package repositories:

```text
../client
../dto
../exceptions
../laravel-controller
../laravel-repository
```

They must exist beside the application checkout on the target VM. Pull or
clone them from their GitHub repositories before starting the Laravel
services; do not allow Compose to create empty replacement directories.

## Deployment scripts

Both scripts clone the application when `JVMETA_APP_DIR` does not exist, or
perform a fast-forward-only pull when it is already a clean checkout. They
fail on a dirty checkout, a branch mismatch, a missing `.env`, a failed build,
or a failed command. They never print the `.env` contents.

### Laravel control deployment

`deploy/laravel.sh` performs:

1. Clone or pull the configured GitHub branch.
2. Validate that control mode disables the FlareSolverr requirement.
3. Build the PHP Docker runtime locally.
4. Run `composer install --no-dev` inside the PHP container.
5. Run `php artisan migrate --force` once.
6. Run `php artisan optimize:clear`.
7. Recreate `api` and `mcp`.

The script intentionally leaves `scheduler` stopped until the control services
have been verified.

### Python embedder deployment

`deploy/python.sh` performs:

1. Clone or pull the configured GitHub branch.
2. Build the embedder Docker runtime locally. The Docker build runs `pip
   install` from `embedder/requirements.txt`.
3. Recreate `embedder`.
4. Wait for `/healthz`.
5. Send a real `/v1/embeddings` request to download/warm the model and verify
   vector generation.

Python dependencies are installed during the Docker build, not on every
container start.

### Script variables

The defaults are suitable for the current VM layout, but all deployment paths
can be overridden without changing the scripts:

```bash
export JVMETA_APP_DIR=/home/joos/jvmeta
export JVMETA_REPO_URL=https://github.com/jooservices/jvmeta.git
export JVMETA_BRANCH=develop
export JVMETA_ENV_SOURCE=/home/joos/jvmeta-backups/.env.<timestamp>
```

`JVMETA_ENV_SOURCE` is required when cloning a new checkout. When the checkout
already contains `.env`, the scripts preserve it and only enforce mode `600`.

## Control-only cutover

### 1. Read-only preflight

Record the Docker/Compose versions, disk and memory, current Git revision,
container state, external dependency health, and the location of `.env`.

### 2. Backup runtime configuration

Back up `.env` to a private path with mode `600` and verify its checksum. Do
not commit or print the backup. Keep the backup until the new control stack
passes verification.

### 3. Stop the old stack

Stop `scheduler` first. For the current control-only cutover, do not wait for
the worker currently running on the old control stack to drain. Then bring
down the old Compose stack without removing volumes:

```bash
docker compose --profile app --profile control --profile embed down
```

This can interrupt an active worker job. Queue data is external, so queued work
remains available, but an active job may become retryable after the queue
visibility timeout. Never use `down -v`, `docker volume prune`, or
`docker system prune`.

### 4. Preserve the old checkout

Rename the old checkout instead of deleting it:

```text
/home/joos/jvmeta
→ /home/joos/jvmeta.previous.<timestamp>
```

Clone the requested GitHub branch into the original path. Keep the previous
checkout and the `.env` backup until verification completes.

### 5. Build and prepare dependencies

Run the Python script first so the embedder is available:

```bash
JVMETA_ENV_SOURCE=/home/joos/jvmeta-backups/.env.<timestamp> \
  /home/joos/jvmeta/deploy/python.sh
```

Then run the Laravel script:

```bash
JVMETA_ENV_SOURCE=/home/joos/jvmeta-backups/.env.<timestamp> \
  /home/joos/jvmeta/deploy/laravel.sh
```

The scripts must be run as the deployment user, not by copying secrets into
the repository.

### 6. Verify before starting the scheduler

Confirm:

- `api`, `mcp`, and `embedder` are running.
- API health reports external database and search connectivity.
- The embedder health endpoint passes.
- A real embedding request succeeds.
- MCP starts without errors and its smoke request succeeds.
- No `worker` or `flaresolverr` container exists on the control VM.
- Logs contain no startup errors.

### 7. Start and verify the scheduler

Only after the previous checks pass:

```bash
docker compose --profile app --profile control --profile embed up -d scheduler
```

Verify the scheduler process is running, observe at least one scheduled loop,
and confirm queue activity through the API/status checks. Do not manually run
crawl commands as a deployment health check.

## Failure handling

The scripts stop on the first failure. Do not retry a failed migration or
recreate services blindly. Record the command and error, then decide whether
to restore the previous checkout and environment. No automatic rollback or
external database restore is performed by these scripts.

## Rollback preparation

Keep the previous checkout, the `.env` backup, and Docker's local image cache
until verification is complete. A rollback restores the previous checkout and
runtime environment, rebuilds the required local runtime, and starts the old
Compose service set. Do not delete old volumes during rollback.
