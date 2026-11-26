# USAGOV Logshipper

## Why this project

This repo contains USAGov-specific configuration for the https://github.com/gsa-tts/cg-logshipper.

## Developer setup

You will need the Cloud Foundry command line and a place to deploy the log-shipper in order to test your work. This project uses buildpacks and is not set up to run locally (e.g., in Docker). 

Contributors on the USAGov team should run `bin/init` after cloning this repo. Currently, this simply installs a commit-msg hook. Then when starting a new feature, create a branch named for the Jira ticket of a new feature:

```
git checkout -b USAGOV-###-new-feature-branch
```

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) for additional information.


## Public domain

This project is in the worldwide [public domain](LICENSE.md). As stated in [CONTRIBUTING](CONTRIBUTING.md):

> This project is in the public domain within the United States, and copyright and related rights in the work worldwide are waived through the [CC0 1.0 Universal public domain dedication](https://creativecommons.org/publicdomain/zero/1.0/).
>
> All contributions to this project will be released under the CC0 dedication. By submitting a pull request, you are agreeing to comply with this waiver of copyright interest.
