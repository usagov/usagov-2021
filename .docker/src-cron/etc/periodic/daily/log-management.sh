#!/usr/bin/env bash

/opt/venv/bin/python /opt/log-management/trim-old-logs.py | tee -a /tmp/log-management.log
