#!/bin/ash

SCRIPT_PATH=$(dirname "$0")

URI=${1:-http://beta.usa.gov}

TOMELOGDIR=/tmp/tome-log
TOMELOG=$TOMELOGDIR/tome_$(date +"%Y_%m_%d_%H_%M_%S").log

mkdir -p $TOMELOGDIR
touch $TOMELOG

$SCRIPT_PATH/tome-build.sh $URI 2>&1 | tee $TOMELOG
$SCRIPT_PATH/tome-sync.sh $TOMELOG

echo "Full Log : $TOMELOG";
