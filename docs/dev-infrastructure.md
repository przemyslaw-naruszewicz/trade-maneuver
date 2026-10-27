# Development Infrastructure

Target setup for Trade Maneuver development and integration testing, deployed from images
published to GitHub Container Registry (GHCR). The deployment workflow is not yet implemented.

## Resources

| Resource | Target |
|---|---|
| Host | One Linux VPS running Docker Compose |
| Memory | 4 GB RAM for the operating system and containers |
| Storage | 40 GB SSD for the system, images, data, and logs |
| CPU | Shared, dynamically allocated capacity |
| LLM | Cloud API when needed; no local model or GPU |

This setup supports development and integration tests with limited concurrency.
LLM API costs and usage limits are separate from VPS resources.

## Services

| Service | Purpose |
|---|---|
| `nginx` | HTTP and static files |
| `app` | Trade Maneuver application and administrative commands |
| `postgres` | Application data and a separate test database |
| `queue` | Background jobs |
| `scheduler` | Scheduled tasks |
| RabbitMQ | Integration messages, results, and failed deliveries |

The existing Compose file supports local development. The VPS needs a deployment configuration
using published images without local builds or source-code bind mounts. RabbitMQ configuration,
an AMQP client, integration logic, and inbox/outbox processing still need implementation.
Background jobs currently use PostgreSQL.
Signal producers run separately, each with its own database and outbox.

## Deployment

- GitHub Actions runs quality checks, builds application images, and publishes them to GHCR.
- Composer dependencies and frontend assets are included in the images. Node.js runs during
  the build in GitHub Actions and is not a service on the VPS.
- Each release identifies images by commit SHA and pins their digests. Application, worker,
  scheduler, and web assets must come from the same release.
- The VPS pulls the selected release from GHCR and starts it with Docker Compose.
  Private images require registry credentials with pull access only.
- Deployment runs required migrations, restarts workers, and verifies service health.
  Keep the previous release available for rollback; database changes need a separate recovery plan.

