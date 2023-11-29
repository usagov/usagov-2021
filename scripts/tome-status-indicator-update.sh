#!/bin/sh

#PREFIX=/var/www
PREFIX=.
IND_FILE=${PREFIX}/web/static-site-status.txt

#if [ ! -f "$IND_FILE" ]; then
   echo "
   <ul>
   <li>Static Site Generator, status as of %timestamp%: %status%</li>
   </ul>" > $IND_FILE
#fi

TR_START_TIME=$1
if [ -n "$TR_START_TIME" ]; then
    shift
fi

STATUS=$1
if [ -n "$STATUS" ]; then
    shift
fi

if [ -n "$TR_START_TIME" -a -n "$STATUS" ]; then

   sed -i "s|%status%|$STATUS|" $IND_FILE
   
   OP_START_TIME=$1
   if [ -n "$OP_START_TIME" ]; then
       shift
   fi

   OP_FAIL_TIME=$1
   if [ -n "$OP_FAIL_TIME" ]; then
      shift
   fi
else
   echo "To few args"
   exit 1
fi
