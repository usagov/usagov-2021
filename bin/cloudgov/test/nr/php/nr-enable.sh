#!/bin/bash

SCRIPT_DIR="$(dirname $(readlink -f "$0"))"
if [ -f $SCRIPT_DIR/nr-util.sh ]; then
  . $SCRIPT_DIR/nr-util.sh
fi

if [ "$1" == "1" ]; then
  enableNewRelic 1
  setLogFiles 1
elif [ "$1" == "0" ]; then
  enableNewRelic 0
  setLogFiles 0
fi

if [ -f "/home/vcap/app/newrelic.logs" ]; then
  echo "tail -f /home/vcap/app/newrelic.logs"
fi