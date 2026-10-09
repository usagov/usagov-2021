# Deploying the log shipper

The log shipper is deployed **per space**. Each of `dev`, `stage`, `prod` and
`dr` runs its own `log-shipper-<space>` app, with its own credentials, its own
`log-storage` S3 bucket, and its own drain service. There is no longer a single
shared shipper in `tools` handling every environment.

## Deployment via CircleCI

Deployment is part of the normal application deployment. The `deploy-cloudgov`
command in [`.circleci/config.yml`](../../.circleci/config.yml) pushes cms, www,
api-proxy and waf, then runs three logshipper steps in order:

1. **`setup-logshipper-services`** — creates `cg-logshipper-creds`,
   `newrelic-creds` and `log-storage` in the space if they are missing, and
   waits for the S3 instance to finish provisioning. Existing services are left
   untouched, so this is a no-op after the first deploy.
2. **`deploy-logshipper`** — pushes `log-shipper-<space>` from a pinned
   cg-logshipper commit plus the config in `project_conf`, mapped to
   `usagov-<space>-logshipper.app.cloud.gov`. It then attaches the app to the
   space's existing egress proxy, which is how fluent-bit reaches New Relic.
3. **`setup-log-drains`** — creates `log-shipper-drain-<space>` and binds it to
   every app in the space except the shipper itself. Apps bound for the first
   time are restarted, since a syslog drain binding does nothing until the app
   restarts.

Because these hang off `deploy-cloudgov`, they run for whichever environment the
deploy job targets: `deploy-to-cloudgov-{dev,dr,stage,prod}`.

### Required CircleCI variables

| Variable | Used for |
| --- | --- |
| `LOGSHIPPER_HTTP_USER` / `LOGSHIPPER_HTTP_PASS` | Basic-auth credentials for the shipper's nginx, and embedded in the drain URL |
| `NEW_RELIC_LICENSE_KEY` | The `newrelic-creds` service |

These are only required the first time a space is set up. Once the services
exist, later deploys don't read them.

## What this deployment does not set up

- **The egress proxy itself.** `setup-egress-for-apps` only attaches the shipper
  to the proxy that already exists in the space. Standing up a proxy is
  `bin/cloudgov/deploy-egress-proxy` / `bin/cloudgov/setup-egress-for-space`, run
  separately as part of space setup.
- **Routing S3 through egress.** Only the New Relic output carries a `Proxy`
  directive. Log archives go to `log-storage` directly.

## Deploying by hand

Each script targets the currently targeted space and refuses to run against a
different one, so target first:

```sh
cf target -s dev
./.buildpack/usagov-logshipper/bin/setup-services.sh
./.buildpack/usagov-logshipper/bin/deploy-logshipper.sh manual dev
./bin/cloudgov/setup-egress-for-apps log-shipper-dev
SPACE=dev ./.buildpack/usagov-logshipper/bin/add-log-drains-for-space.sh
```

To redeploy an existing shipper without touching services or drains, run only
`deploy-logshipper.sh`.

## Upgrading cg-logshipper

`deploy-logshipper.sh` pins the upstream commit in `CG_LOGSHIPPER_COMMIT` at the
top of the script. Change that value to take a new version. The script fails
loudly if upstream's manifest starts declaring its own `routes:`, since this
deployment appends the route itself.
