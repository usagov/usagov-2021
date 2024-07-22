#!/usr/bin/env bash

while [ 1 -eq 1 ]; do
  cf run-task cfevents --name cfevents-instance --command "/opt/cfevents/capture-latest-events dr"
  sleep 60
done
