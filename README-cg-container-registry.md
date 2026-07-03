# Notes for using CG container registry

This file explains some of the changes required to use the cg-workshop container registry instead of DockerHub, both in this code and in CircleCI and Cloud.gov Workshop itself. When we start storing our images in cg-workshop, we can move some of this information over to the wiki and delete this file.

## Registry location

We have set up a registry here: https://workshop.cloud.gov/usagov/containers/container_registry

Authentication, build, and push commands showing that paths to use:

```
docker login registry.workshop.cloud.gov
docker build -t registry.workshop.cloud.gov/usagov/containers .
docker push registry.workshop.cloud.gov/usagov/containers
```

We will use a developer's account name and a personal access token (PAT) for authentication. We can use PATs with different scopes for read-only (pull) and read/write (push) access, so we'll do that.

The registry is currently visible to "internal" users. There's probably no reason to make it public.

## CI ENV variables

These are the variables we're using now to interact with Docker Hub, and what we will replace them with.

In general, we'll be adding new variables to CircleCI and using them in our CI scripts, and will delete the old variables from CircleCI when we've fully converted over to storing our images in Cloud.gov workshop.

### Variables related to Docker Content Trust (phase out)

Docker Hub itself is phasing out Docker Content Trust. There are other signing options, but there's probably no value to signing our own images that we're not publishing for use outside of our own system. Cloud Foundry never supported Docker Content Trust to begin with. (Note that we will want to keep an eye on this for images that we consume from other providers, though!)

- `DCT_HASH` and `DCT_KEY` for signing. (remove when we've removed signing)
- `DOCKER_CONTENT_TRUST_REPOSITORY_PASSPHRASE` (remove when we've removed signing)
- `DOCKER_CONTENT_TRUST` (keep for now. this is just a "true" flag)

### Authentication

- `DOCKERHUB_USERNAME`: use `CG_REGISTRY_USERNAME` for the new registry
- `DOCKERHUB_ACCESS_TOKEN`: use `CG_REGISTRY_PAT_PUSH` for pushing images, and `CG_REGISTRY_PAT_READ` for pulling them.
- (nothing): use `CG_REGISTRY` for the registry address registry.workshop.cloud.gov

### Image naming

- `DOCKERUSER`: this has been our Docker Hub org name. We probably won't still need this.
- `DOCKERREPO`: this is "usagov-2021." TODO: maybe an opportunity to make more sensible names, with, say "cms" or "waf" front-and-center?

Try 1: using:

- `CG_REGISTRY_PATH`, set to `usagov/containers` in place of `DOCKERUSER`
- `CG_REPO` instead of `DOCKERREPO`, but keep it `usagov-2021`


## New considerations for managing the registry and tokens

Personal access tokens (PATs) in Cloud.gov workshop have expiration dates. We can set these to up to a year, but what we should do is rotate them regularly. Maybe set the push token to a 2-month expiration, create a new tokens every month, and delete the old tokens when they expire. That way we don't have to coordinate token rotation closely. The read-only token can have a longer expiration period; perhaps 3 months, so the apps running in Cloud.gov can still access the old images if they aren't updated for an extended period.

We can auto-delete images that are older than a given period, while keeping the latest "n" images, simply by configuring that in the registry!
