#!/bin/bash

# we might be running in circleci
if [ -f /home/circleci/project/env.local ]; then
  . /home/circleci/project/env.local
fi
# we might be running from a local dev machine
SCRIPT_DIR="$(dirname "$0")"
if [ -f $SCRIPT_DIR/env.local ]; then
  . $SCRIPT_DIR/env.local
fi
if [ -f ./env.local ]; then
  . ./env.local
fi

# just testing?
if [ "$1" == "--dryrun" ]; then
  echo=echo
  dryrun=$1
  shift
fi

# Define "usage" in the script to use help text.
if [ -n "$usage" ]; then
  if [[ "$1" == "-h" ]]; then
      echo "${usage}"
      exit 1
  fi
fi