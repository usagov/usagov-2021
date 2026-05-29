# Cloud.gov Development Instructions

This repository contains an application deployed to [cloud.gov](https://cloud.gov/), a FedRAMP-authorized Platform as a Service (PaaS) operated by the U.S. General Services Administration (GSA). These instructions help AI coding assistants understand cloud.gov conventions and best practices.

## Platform Overview

Cloud.gov is built on Cloud Foundry and provides:

- **FedRAMP Moderate authorization** - Inherit ~60% of NIST SP 800-53 controls
- **Self-service deployment** - Deploy with `cf push`, no infrastructure tickets required
- **Managed services** - Databases, object storage, and more via the marketplace
- **Isolated environments** - Separate spaces for dev, staging, and production

## Key Concepts

### Organizations and Spaces

- **Organization (org)**: Top-level container for users, spaces, and quotas
- **Space**: Isolated environment within an org (e.g., `dev`, `staging`, `prod`)
- **Target**: Set your working org/space with `cf target -o <ORG> -s <SPACE>`

### Applications

- Applications are deployed as **containers** built from source code using **buildpacks**
- Each app instance runs in an isolated container with its own memory and disk allocation
- Apps should follow [Twelve-Factor App](https://12factor.net/) principles

## Common CF CLI Commands

### Authentication

```bash
# Target the cloud.gov API endpoint
cf api api.fr.cloud.gov

# Login with service account credentials (for CI/CD)
cf auth <USERNAME> <PASSWORD>

# Target an org and space
cf target -o <ORG> -s <SPACE>

# Login with SSO (recommended for interactive use)
cf login -a api.fr.cloud.gov --sso
```

### Deployment

```bash
# Deploy an application
cf push <APP_NAME>

# Deploy with a specific manifest file
cf push -f manifest.yml

# Deploy without starting the app
cf push <APP_NAME> --no-start

# View application status
cf app <APP_NAME>

# View recent logs
cf logs <APP_NAME> --recent

# Stream live logs
cf logs <APP_NAME>
```

### Application Management

```bash
# Scale application instances
cf scale <APP_NAME> -i <INSTANCE_COUNT>

# Scale memory
cf scale <APP_NAME> -m <MEMORY>  # e.g., 512M, 1G

# Restart an application
cf restart <APP_NAME>

# Restage an application (rebuild with current buildpack)
cf restage <APP_NAME>

# Stop an application
cf stop <APP_NAME>

# Delete an application
cf delete <APP_NAME>
```

### Services

```bash
# List available services in the marketplace
cf marketplace

# Create a service instance
cf create-service <SERVICE> <PLAN> <INSTANCE_NAME>

# Bind a service to an app
cf bind-service <APP_NAME> <SERVICE_INSTANCE>

# View service instance details
cf service <SERVICE_INSTANCE>

# View bound service credentials
cf env <APP_NAME>
```

### Environment Variables

```bash
# Set an environment variable
cf set-env <APP_NAME> <VAR_NAME> <VALUE>

# View all environment variables
cf env <APP_NAME>

# Unset an environment variable
cf unset-env <APP_NAME> <VAR_NAME>
```

## Manifest Configuration

Applications should include a `manifest.yml` file in the repository root:

```yaml
---
applications:
  - name: my-application
    memory: 512M
    instances: 2
    buildpacks:
      - python_buildpack  # or nodejs_buildpack, java_buildpack, etc.
    env:
      ENV_VAR_NAME: value
    services:
      - my-database
      - my-s3-bucket
    routes:
      - route: my-app.app.cloud.gov
```

### Manifest Best Practices

- Always specify `memory` explicitly to avoid unexpected defaults
- Use `instances: 2` or more for production apps to ensure availability during restarts
- List all bound `services` in the manifest for reproducible deployments
- Use the `routes` property to control your application's URLs

## Application Architecture Principles

### Stateless Applications

- **Never write to the local filesystem** for persistent data - use S3 or database services
- Application instances restart frequently (platform updates, scaling, crashes)
- Store session state in external services (Redis, database)

### Environment Configuration

- Use environment variables for all configuration (database URLs, API keys, feature flags)
- Never commit secrets to the repository
- Access bound service credentials via `VCAP_SERVICES` environment variable

### Logging

- Write logs to `stdout` and `stderr` - cloud.gov captures them automatically
- Use structured logging (JSON) for easier parsing and analysis
- Configure log drains for long-term retention and analysis

### Health Checks

- Implement a health check endpoint (e.g., `/health` or `/ping`)
- Configure health checks in the manifest for proper instance management

## File Organization

When working with cloud.gov deployments, expect these files:

| File | Purpose |
|------|---------|
| `manifest.yml` | Cloud Foundry deployment configuration |
| `.cfignore` | Files to exclude from deployment (similar to `.gitignore`) |
| `vars.yml` | Variable values for manifest interpolation (do not commit secrets) |
| `.profile` | Shell script run before app starts |
| `Procfile` | Process types for the application |

## Security Considerations

- cloud.gov is FedRAMP Moderate authorized - understand shared responsibility model
- Applications must handle their own code-level security (OWASP Top 10)
- Use the platform's egress restrictions appropriately
- Rotate service credentials periodically
- Never log sensitive data (PII, credentials, tokens)

## Useful Links

- [cloud.gov Documentation](https://docs.cloud.gov/)
- [cloud.gov Dashboard](https://dashboard.fr.cloud.gov/)
- [cloud.gov Status](https://cloudgov.statuspage.io/)
- [Cloud Foundry Documentation](https://docs.cloudfoundry.org/)
- [Cloud Foundry CLI Documentation](https://cli.cloudfoundry.org/en-US/v8/) (use only v8, not v7 or v6)
