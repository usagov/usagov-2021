#!/bin/sh

#PREFIX=/var/www
PREFIX=.
IND_FILE=${PREFIX}/web/static-site-status.txt

echo "
<div>
   <span>Static Site Generator, status as of %timestamp%:</span>
   <p>
      %status%
   </p>
</div>" > $IND_FILE

TR_START_TIME=$1
if [ -n "$TR_START_TIME" ]; then
    shift
fi

STATUS=$1
if [ -n "$STATUS" ]; then
    shift
fi

if [ -n "$TR_START_TIME" -a -n "$STATUS" ]; then

   humanDateUTC=$(date -u -d @"$TR_START_TIME")
   sed -i "s|%timestamp%|$humanDateUTC|" $IND_FILE
   sed -i "s|%status%|$STATUS|" $IND_FILE
   
else
   echo "To few args"
   exit 1
fi
