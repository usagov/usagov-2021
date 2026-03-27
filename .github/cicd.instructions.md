---
applyTo: "**/.github/workflows/*.yml,**/.github/workflows/*.yaml,**/Jenkinsfile,**/.circleci/config.yml,**/.travis.yml"
---

# Cloud.gov CI/CD Instructions

This document provides guidance for setting up continuous integration and continuous deployment (CI/CD) pipelines for cloud.gov applications.

## Overview

Cloud.gov integrates with any CI/CD service. The core requirements are:
1. A service account with deployment credentials
2. The CF CLI installed in your CI/CD environment
3. Secure storage for credentials

## Prerequisites

Before setting up CI/CD:

1. **Production-ready application** - Follow the [production-ready guide](https://docs.cloud.gov/platform/deployment/production-ready/)
2. **Version control** - Code in Git with a `manifest.yml`
3. **Continuous integration** - Tests passing before deployment

## Service Account Setup

### Create a Service Account

Service accounts provide restricted credentials for automated deployments.

```bash
# Create a deployer service account (can push apps)
cf create-service cloud-gov-service-account space-deployer my-deployer

# Or create an auditor account (read-only)
cf create-service cloud-gov-service-account space-auditor my-auditor
```

### Obtain Credentials

```bash
# Create a service key
cf create-service-key my-deployer deploy-key

# View the credentials
cf service-key my-deployer deploy-key
```

Output:

```json
{
  "username": "deadbeef-aabb-1234-feha-0987654321000",
  "password": "oYasdfliaweinasfdliecV"
}
```

### Important Notes

- **Password expiration**: Service account passwords expire every 90 days
- **Rotate credentials**: Delete and recreate service keys to rotate
- **Least privilege**: Service accounts are limited to the space where created
- **Not for humans**: Never use service accounts for interactive login

### Rotate Expired Credentials

```bash
cf delete-service-key my-deployer deploy-key
cf create-service-key my-deployer deploy-key
cf service-key my-deployer deploy-key
```

## GitHub Actions

### Setup

1. Create a service account (see above)
2. Store credentials as GitHub Secrets:
   - `CG_USERNAME` - Service account username
   - `CG_PASSWORD` - Service account password

### Basic Workflow

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Cloud.gov

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Set up Python
        uses: actions/setup-python@v5
        with:
          python-version: '3.11'
      
      - name: Install dependencies
        run: pip install -r requirements.txt
      
      - name: Run tests
        run: pytest

  deploy:
    runs-on: ubuntu-latest
    needs: test
    if: github.ref == 'refs/heads/main' && github.event_name == 'push'
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Deploy to Cloud.gov
        uses: cloud-gov/cg-cli-tools@main
        with:
          cf_api: https://api.fr.cloud.gov
          cf_username: ${{ secrets.CG_USERNAME }}
          cf_password: ${{ secrets.CG_PASSWORD }}
          cf_org: your-org-name
          cf_space: your-space-name
```

### Multi-Environment Workflow

Deploy to different environments based on branch:

```yaml
name: Deploy to Cloud.gov

on:
  push:
    branches: [main, develop]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run tests
        run: |
          pip install -r requirements.txt
          pytest

  deploy-dev:
    runs-on: ubuntu-latest
    needs: test
    if: github.ref == 'refs/heads/develop'
    environment: development
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Deploy to Dev
        uses: cloud-gov/cg-cli-tools@main
        with:
          cf_api: https://api.fr.cloud.gov
          cf_username: ${{ secrets.CG_USERNAME_DEV }}
          cf_password: ${{ secrets.CG_PASSWORD_DEV }}
          cf_org: your-org
          cf_space: dev

      - name: Push application
        run: cf push -f manifest-dev.yml

  deploy-prod:
    runs-on: ubuntu-latest
    needs: test
    if: github.ref == 'refs/heads/main'
    environment: production
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Deploy to Production
        uses: cloud-gov/cg-cli-tools@main
        with:
          cf_api: https://api.fr.cloud.gov
          cf_username: ${{ secrets.CG_USERNAME_PROD }}
          cf_password: ${{ secrets.CG_PASSWORD_PROD }}
          cf_org: your-org
          cf_space: prod

      - name: Push application (rolling)
        run: cf push -f manifest-prod.yml --strategy rolling
```

### Blue-Green Deployment Workflow

```yaml
name: Blue-Green Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    environment: production
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Install CF CLI
        uses: cloud-gov/cg-cli-tools@main
        with:
          cf_api: https://api.fr.cloud.gov
          cf_username: ${{ secrets.CG_USERNAME }}
          cf_password: ${{ secrets.CG_PASSWORD }}
          cf_org: your-org
          cf_space: prod

      - name: Blue-Green Deploy
        run: |
          # Deploy new version with temporary name
          cf push my-app-venerable -f manifest.yml
          
          # Map production route to new version
          cf map-route my-app-venerable app.cloud.gov --hostname my-app
          
          # Verify new version is healthy
          sleep 30
          curl -f https://my-app.app.cloud.gov/health || exit 1
          
          # Unmap route from old version
          cf unmap-route my-app app.cloud.gov --hostname my-app
          
          # Delete old version
          cf delete my-app -f
          
          # Rename new version
          cf rename my-app-venerable my-app
```

### Manual Approval for Production

```yaml
deploy-prod:
  runs-on: ubuntu-latest
  needs: deploy-staging
  environment: 
    name: production
    url: https://my-app.app.cloud.gov
  
  steps:
    - uses: actions/checkout@v4
    # ... deployment steps
```

Configure the `production` environment in GitHub Settings to require manual approval.

## CircleCI

### Configuration

Create `.circleci/config.yml`:

```yaml
version: 2.1

jobs:
  test:
    docker:
      - image: cimg/python:3.11
    steps:
      - checkout
      - run:
          name: Install dependencies
          command: pip install -r requirements.txt
      - run:
          name: Run tests
          command: pytest

  deploy:
    docker:
      - image: cimg/base:stable
    steps:
      - checkout
      - run:
          name: Install CF CLI
          command: |
            wget 'https://packages.cloudfoundry.org/stable?release=linux64-binary&version=8.8.0' -O /tmp/cf.tar.gz
            sudo tar xzf /tmp/cf.tar.gz -C /usr/local/bin
            cf --version
      - run:
          name: Deploy to Cloud.gov
          command: |
            cf login -a https://api.fr.cloud.gov \
              -u $CF_USERNAME \
              -p $CF_PASSWORD \
              -o $CF_ORG \
              -s $CF_SPACE
            cf push

workflows:
  build-and-deploy:
    jobs:
      - test
      - deploy:
          requires:
            - test
          filters:
            branches:
              only: main
```

Set environment variables `CF_USERNAME`, `CF_PASSWORD`, `CF_ORG`, and `CF_SPACE` in CircleCI project settings.

## Best Practices

### Security

1. **Never commit credentials** - Use CI/CD secrets management
2. **Use separate service accounts** - One per environment (dev, staging, prod)
3. **Rotate credentials regularly** - At least every 90 days (they expire anyway)
4. **Limit permissions** - Use `space-auditor` when deployment not needed

### Reliability

1. **Run tests before deploy** - Gate deployments on passing tests
2. **Use rolling deployments** - `cf push --strategy rolling` for zero downtime
3. **Verify after deploy** - Check health endpoint after deployment
4. **Enable rollback** - Use blue-green for easy rollback capability

### Environment Management

1. **Separate manifests per environment** - `manifest-dev.yml`, `manifest-prod.yml`
2. **Use variables files** - Keep environment-specific values in `vars-*.yml`
3. **Protect production** - Require manual approval for production deploys
4. **Match environments** - Keep dev/staging similar to production

### Workflow Organization

```
.github/
  workflows/
    ci.yml           # Tests on all PRs
    deploy-dev.yml   # Auto-deploy to dev
    deploy-prod.yml  # Manual deploy to production
```

## Troubleshooting

### Authentication Failures

```
Error: Your current password has expired
```

**Solution**: Rotate credentials (delete and recreate service key)

### Deployment Timeouts

```
Error: Timed out waiting for app to start
```

**Solutions**:
- Increase `timeout` in manifest.yml
- Check app logs: `cf logs <app> --recent`
- Verify health check endpoint responds quickly

### Space/Org Not Found

```
Error: Space 'prod' not found
```

**Solutions**:
- Verify org and space names are correct
- Ensure service account has access to the space
- Check `cf target` output

## References

- [cloud.gov Continuous Deployment](https://docs.cloud.gov/platform/management/continuous-deployment/)
- [cloud.gov Service Account](https://docs.cloud.gov/platform/services/cloud-gov-service-account/)
- [GitHub Actions cg-cli-tools](https://github.com/cloud-gov/cg-cli-tools)
- [Cloud Foundry CLI Documentation](https://cli.cloudfoundry.org/)
