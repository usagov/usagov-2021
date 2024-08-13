#!/usr/bin/env bash

. ~/.profile &> /dev/null

cf run-task callcenter --name callcenter-wt-update --command "/opt/callcenter/call-center-update"
