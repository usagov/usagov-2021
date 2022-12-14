#!/bin/bash


SCRIPT_DIR="$(dirname $(readlink -f "$0"))"
if [ -f $SCRIPT_DIR/nr-util.sh ]; then
  . $SCRIPT_DIR/nr-util.sh
fi

killNewRelicDaemon

echo

makeNoise DAN

echo
