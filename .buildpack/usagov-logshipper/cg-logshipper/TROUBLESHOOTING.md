# Troubleshooting

Let's start with what you should expect to see. Here are the logs from a successful startup:

```
   2023-12-14T17:13:04.01-0800 [API/5] OUT Starting app with guid ef37b01b-0954-4eb7-9b31-e6c189864c08
   2023-12-14T17:13:04.40-0800 [CELL/0] OUT Cell e49592cc-346d-4fd2-b553-bd659bffafef creating container for instance 6bb71729-dc0b-476e-6728-eaeb
   2023-12-14T17:13:05.21-0800 [CELL/0] OUT Security group rules were updated
   2023-12-14T17:13:05.23-0800 [CELL/0] OUT Cell e49592cc-346d-4fd2-b553-bd659bffafef successfully created container for instance 6bb71729-dc0b-476e-6728-eaeb
   2023-12-14T17:13:05.53-0800 [CELL/0] OUT Downloading droplet...
   2023-12-14T17:13:08.51-0800 [CELL/0] OUT Downloaded droplet (47.8M)
   2023-12-14T17:13:08.51-0800 [CELL/0] OUT Starting health monitoring of container
   2023-12-14T17:13:08.72-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] OUT Invoking pre-start scripts.
   2023-12-14T17:13:08.73-0800 [APP/PROC/WEB/0] OUT Invoking pre-start scripts.
   2023-12-14T17:13:09.13-0800 [APP/PROC/WEB/0] OUT Invoking start command.
   2023-12-14T17:13:09.13-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] OUT Invoking start command.
   2023-12-14T17:13:09.14-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR Fluent Bit v2.2.0
   2023-12-14T17:13:09.14-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR * Copyright (C) 2015-2023 The Fluent Bit Authors
   2023-12-14T17:13:09.14-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR * Fluent Bit is a CNCF sub-project under the umbrella of Fluentd
   2023-12-14T17:13:09.14-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR * https://fluentbit.io
   2023-12-14T17:13:09.15-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR [2023/12/15 01:13:09] [ info] [fluent bit] version=2.2.0, commit=, pid=7
   2023-12-14T17:13:09.15-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR [2023/12/15 01:13:09] [ info] [storage] ver=1.5.1, type=memory, sync=normal, checksum=off, max_chunks_up=128
   2023-12-14T17:13:09.15-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR [2023/12/15 01:13:09] [ info] [cmetrics] version=0.6.4
   2023-12-14T17:13:09.15-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR [2023/12/15 01:13:09] [ info] [ctraces ] version=0.3.1
   2023-12-14T17:13:09.15-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR [2023/12/15 01:13:09] [ info] [input:dummy:dummy.0] initializing
   2023-12-14T17:13:09.15-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR [2023/12/15 01:13:09] [ info] [input:dummy:dummy.0] storage_strategy='memory' (memory only)
   2023-12-14T17:13:09.15-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR [2023/12/15 01:13:09] [ info] [input:tcp:tcp.1] initializing
   2023-12-14T17:13:09.15-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR [2023/12/15 01:13:09] [ info] [input:tcp:tcp.1] storage_strategy='memory' (memory only)
   2023-12-14T17:13:09.15-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR [2023/12/15 01:13:09] [ info] [fstore] created root path /tmp/fluent-bit/s3/cg-99d65a95-8898-4469-ac9f-b10ee90b84a0
   2023-12-14T17:13:09.15-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR [2023/12/15 01:13:09] [ info] [output:s3:s3.0] Using upload size 50000000 bytes
   2023-12-14T17:13:09.16-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR [2023/12/15 01:13:09] [ info] [output:s3:s3.0] worker #0 started
   2023-12-14T17:13:09.17-0800 [APP/PROC/WEB/SIDECAR/FLUENTBIT/0] ERR [2023/12/15 01:13:09] [ info] [sp] stream processor started
   2023-12-14T17:13:11.52-0800 [CELL/0] OUT Container became healthy
```

Note that we see a couple of lines of status from the `tcp` input and the `s3` output, but nothing from the `newrelic` output.

## New Relic (newrelic)

This log shipper uses a plugin named `newrelic`, which is provided by New Relic. This is *not* the same as the `nrlogs` plugin that Fluent Bit includes by default. You can find New Relic's documentation of their plugin at https://docs.newrelic.com/docs/logs/forward-logs/fluent-bit-plugin-log-forwarding/ .

The `newrelic` plugin will log errors if it cannot connect to New Relic because of bad credentials. Note that you will get the same messages if you have provided good credentials, but have set the endpoint to the wrong New Relic API endpoint.

(**TODO**: produce this condition and note what it looks like.)

### Finding the logs in New Relic's UI
New Relic has *Logs* in two places in its UI. There is a global *Logs* section, which is where you should look first. The URL of this view will start with https://one.newrelic.com/logger (in fact, if you are logged in, that URL should take you there) and the heading will be something like _All logs_. There is another, very similar looking, logs view in the *APM & Services* section under each entity New Relic knows about.

Each APM & Service instance that New Relic classifies as a "service" will have its own individual logs that are available when navigating to a specific service. When navigating to the "Global Logs Inbox" as specified above, you will see all logs from all services. To filter on specific logs that are coming from `cg-logshipper` you can add a column of `newrelic.source`, or, run a query with the following: `newrelic.source:"api.logs"`. Logshipper will have a source of `newrelic.source:"api.logs"`, where application logs will have a source of `newrelic.source:"logs.APM"`.

If you plan to have the logshipper deployed in multiple environments, it may also be beneficial to add a column of `tags.space_name` so that when all instances of logshipper, as well as application instances are putting logs into the inbox, it is easier to see which environments are, in fact, sending logs to New Relic.

![image](https://github.com/GSA-TTS/cg-logshipper/assets/130377221/a832b1f9-02df-41c2-a0c4-8f3f9558f67e)

A useful configuration was the inclusion of a sample message, something like this in `fluentbit.conf`:
[INPUT]
    name      dummy
    dummy     {"message":"Fluent Bit - Heartbeat", "temp": "0.74", "extra": "false"}
    interval_sec 60


By default, logs sent by the logshipper are not associated with a particular entity!

**TODO**: try adding an entity.guid as described in https://docs.newrelic.com/docs/new-relic-solutions/new-relic-one/core-concepts/what-entity-new-relic/#entity-synthesis and document that.

### Might it be the egress proxy?

See https://github.com/GSA-TTS/cg-egress-proxy?tab=readme-ov-file#troubleshooting for hints on troubleshooting whether your egress proxy is working.

Note that cg-logshipper expects the `$PROXYROUTE` environment variable, which it will use to set `$HTTPS_PROXY` on startup.

## s3

So far, we haven't run into any puzzles with this one, so this section is empty.