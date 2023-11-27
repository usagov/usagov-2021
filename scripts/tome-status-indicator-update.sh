#!/bin/sh

IND_FILE=/var/www/web/static-site-status.txt

if [ $ARGV < 2 ]; then
   exit 1
fi

OP=$1
shift

TS=$1
shift
