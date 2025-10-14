# Deploying the log shipper

Deployment via CircleCI is configured and scripted (see .circleci/config.yml).




## Default: redeploy existing app

By default, commits to the `main` branch launch the `deploy-logshipper` workflow, which is intended to update a log-shipper app that has already been deployed. This workflow starts with a manual `approve-launch` step, after which it will deploy the latest code to the `log-shipper` app in the `tools` space.

## Full deployment

A second workflow, `setup-services-and-deploy`, will create all the routes and services required to deploy the log shipper from scratch. It can also be used to update an existing installation. It requires that the `action` parameter be set to `launch`, so CircleCI won't offer this workflow by default. It can be added manually in the CircleCI UI by selecting **Trigger Pipeline** (or via API).

Note that re-running this workflow will *not* update any of the existing services, including log drain services. It will create new services for any that are missing.

