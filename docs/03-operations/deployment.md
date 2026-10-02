# Production deployment runbook

Status: planned runbook. This document is not an execution script and has not
been used to deploy production.

## Scope

This runbook describes the planned Docker-image deployment for jvmeta when the
database, search, and observability services are external to the application
hosts.

Production topology and host addresses are intentionally kept outside this
repository in the private workspace deployment inventory.

## Runtime model

The application uses two images:

| Image | Containers | Purpose |
| --- | --- | --- |
| `jvmeta:<release>` | `api`, `worker`, `scheduler`, `mcp` | PHP/Laravel application runtime |
| `jvmeta-embedder:<release>` | `embedder` | Separate embedding runtime |

The PHP image is shared by several containers. The container command defines
the role; the image does not start every role by itself.

Production roles are:

- Control: `api`, `scheduler`, `mcp`, and `embedder`.
- Crawler: `worker` and `flaresolverr`.
- External dependencies: PostgreSQL, MongoDB, Elasticsearch, and
  OpenObserve. These services are not rebuilt or managed as application
  containers by this runbook.

Only one control instance runs the scheduler. Multiple schedulers would
duplicate scheduled jobs and alerts.

## Current-state prerequisite

The current development Compose setup is not yet a pure image deployment:

- The application services bind-mount the repository and sibling package
  clones.
- The current `Dockerfile` provides the PHP/Node runtime and helper scripts,
  but does not yet copy the application source or install its Composer
  dependencies into the image.

The production image build and Compose configuration are now prepared so that
release images contain their source and dependencies and do not depend on host
source mounts. The preparation files are:

- `Dockerfile.production` for the PHP application image.
- `embedder/Dockerfile.production` and
  `embedder/requirements.production.txt` for the CPU-only embedder image.
- `compose.production.yml` for explicit control, crawler, and migration
  profiles.
- `deploy/production.env.example` for a placeholder-only runtime
  configuration template.

The existing `Dockerfile` and `docker-compose.yml` remain the local
development setup.

## Image contract

1. Build both application images once from the approved release tag for the
   target host architecture.
2. Run Composer installation while building `jvmeta:<release>`; do not run
   `composer install` on production hosts.
3. Publish the images to the approved private or internal image distribution
   mechanism. The registry is intentionally not fixed in this document.
4. Record the immutable digest for every image.
5. Deploy the recorded digests, not mutable tags such as `latest` or
   `php85`.

The production Compose configuration must not bind-mount the source tree or
local package clones. Otherwise host files can override the code and
dependencies that were built into the release image.

The production Compose file also separates the readiness contract: crawler
containers require FlareSolverr, while control-plane containers set
`CRAWLERX_FLARESOLVERR_REQUIRED=false` because they do not run the crawler
sidecar.

The control Compose profile publishes the embedder through
`JVMETA_EMBEDDER_BIND`. Keep it on loopback for control-only operation. Before
external crawler nodes use this embedder, bind it to the control host's
private/LAN address and restrict access with the host or network firewall.

## Deployment sequence

### 1. Build and publish the release images

Build and verify:

- `jvmeta:<release>`.
- `jvmeta-embedder:<release>`.

Build the PHP image with `Dockerfile.production` and the embedder image with
`embedder/Dockerfile.production`:

```bash
docker build --platform linux/amd64 \
  -f Dockerfile.production \
  -t jvmeta:<release> .

docker build --platform linux/amd64 \
  -f embedder/Dockerfile.production \
  -t jvmeta-embedder:<release> embedder
```

The embedder uses CPU-only PyTorch; CUDA is not required for vector storage or
normal embedding on the control instance. Record the internal deployment
version, source commit, image digests, and intended service configuration
before touching production. This is an internal/private deployment artifact,
not a public release.

### 2. Run read-only preflight checks

Check every application host for:

- Docker and Compose availability.
- Available disk and memory.
- Access to the private image distribution mechanism.
- Current container state and image digests.
- Reachability of all configured external dependencies.
- Current application health, queue state, and worker heartbeats.

The deployment must target explicit services. It must not start every Compose
profile or enable bundled data services accidentally.

### 3. Stop the scheduler

Stop only the control-plane scheduler. Keep the API and MCP services available
until the control deployment begins.

This prevents new scheduled crawl work from being created before the old
control stack is replaced.

### 4. Cut over the current control VM

For the current control-only cutover, do not wait for the worker that is
incorrectly running on the old control Compose stack. Stop the scheduler first,
then bring down the old stack without removing volumes. The queue is external,
so queued work remains available, but any active worker job may be interrupted
and become retryable after the queue visibility timeout. Verify queue
processing after the new control stack starts.

### 5. Deploy the control plane

On the control instance:

1. Pull the recorded `jvmeta` and `jvmeta-embedder` image digests.
2. If the release contains a migration, run `php artisan migrate --force`
   exactly once from the release image.
3. Start only `api`, `mcp`, and `embedder` with the new image digests; keep
   `scheduler` stopped until verification passes.
4. Verify the API, MCP, embedder, logs, and dependency connectivity.
5. Start `scheduler` and verify that its scheduled loop is running.

The image-based flow does not run these commands on the production host:

- `composer install`.
- `php artisan down`.
- A broad or shared cache flush.

Composer dependencies belong in the image. Container replacement and health
checks provide the deployment boundary. Cache handling must be part of the
image/startup design and must not clear shared application data as a side
effect.

The prepared `migrate` service is intended to run explicitly with the
production Compose file and private runtime environment. It must not be
started as part of every application service.

### 6. Deploy crawler instances one at a time

Apply the same procedure to each crawler instance, one at a time:

1. Pull the recorded `jvmeta` image digest.
2. Gracefully stop the idle worker.
3. Recreate only the `worker` service.
4. Recreate `flaresolverr` only when its image or configuration is part of
   the release.
5. Verify the worker before moving to the next crawler instance.

The crawler does not use `php artisan down` because it does not serve the
Laravel HTTP API.

### 7. Start the scheduler

For a control-only deployment, start the scheduler after the control services
pass verification as described in step 5. If crawler rollout is part of the
same release, wait until every crawler instance passes verification before
starting the single scheduler.

Do not manually run crawl commands as a deployment health check. The scheduler
should resume normal operation through its configured schedule.

### 8. Verify the complete topology

Verify that:

- The control instance runs `api`, `scheduler`, `mcp`, and `embedder`.
- Each crawler instance runs only `worker` and `flaresolverr`.
- All application containers use the recorded image digests.
- API and MCP health checks pass.
- Embedder connectivity passes.
- Worker heartbeats are current.
- Queue counts and failure rates are stable.
- External dependencies remain reachable.
- No unexpected containers or profiles are running.

## Explicitly out of scope

The following are intentionally not automated by this runbook yet:

- External backup or snapshot verification.
- Rollback automation.
- Registry selection and credentials.
- Production execution.
- The deployment Bash script.

Those concerns require separate decisions and implementation before this
runbook becomes an executable deployment tool.
