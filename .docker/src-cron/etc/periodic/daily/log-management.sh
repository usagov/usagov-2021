#!/usr/bin/env bash

python3 /opt/log-management/trim-old-logs.py | tee -a /tmp/log-management.log
