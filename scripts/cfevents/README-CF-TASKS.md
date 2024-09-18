# Cloud Foundry Tasks

## Background
A CF Task will just run an app to perform a single function (e.g. run a script) and then exit, rather than having an always-running app doing nothing most of the time.

### 1. Running a CF Task

`cf run-task cfevents --name cfevents-status --command "/opt/cfevents/app-status dr"`

`cf run-task cfevents --name cfevents-capture --command "/opt/cfevents/capture-latest-events dr"`

#### `--name` is a name to use for this particular task.  Can be useful when listing tasks

#### `--command` is the script to be run

### 2. Checking the status of a CF Task

```
cf tasks cfevents

Getting tasks for app cfevents in org gsa-tts-usagov / space dr as mark.vitek@gsa.gov...

id   name                state       start time                      command
21   cfevents-instance   SUCCEEDED   Thu, 25 Jul 2024 14:56:00 UTC   /opt/cfevents/capture-latest-events dr
20   cfevents-instance   SUCCEEDED   Thu, 25 Jul 2024 14:55:00 UTC   /opt/cfevents/capture-latest-events dr
...
```

### 3. Checking the output of a CF Task

It's a little clunky, but the only way to see the output of the task run is with cf logs

`cf logs cfevents --recent`

## Creating a Cloud Foundry App to be run as a Task

### 1. Manifest YAML file
The main things here are `no-route: true` and `type: task`.  
```
---
version: 1
applications:
  - name: cfevents
    path: .docker/src-cfevents
    no-route: true
    health-check-type: process
    type: task
    docker:
      image: gsatts/usagov-2021:cfevents
    instances: 0
    memory: 256M
    services:
      - secrets
      - secauthsecrets
      - storage
      - cfevents-service-account
```

### 2. .docker/Dockerfile-myapp

This is nothing special - whatever your app needs.

### 3. .docker/src-myapp/

Again - whatever your app needs.

If you need Cloud Foundry CLI access or S3 bucket access, look at `.docker/src-cfevents/root/.profile`  (currently only in branch `USAGOV-1557-cf-logs-to-new-relic`)

#### Note that the above branch info will be obsolete, once this branch is merged.

### 4. Build/deploy scripts

Our usual:

`bin/cloudgov/container-build-myapp`

`bin/cloudgov/container-push-myapp`

`bin/cloudgov/deploy-myapp`

You should clone these from the cfevents-specific scripts (currently only in branch `USAGOV-1557-cf-logs-to-new-relic`).  Especially the deploy script, as it does some task specific stuff with the `cf push` and app egress setup.

#### Note that the above branch info will be obsolete, once this branch is merged.
