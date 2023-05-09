#!/bin/sh

RETRY_SEMAPHORE_FILE=/tmp/tome-log/retry-on-next-run

export RETRY_SEMAPHORE_EXISTS=0
if [ -f $RETRY_SEMAPHORE_FILE ]; then
  export RETRY_SEMAPHORE_EXISTS=1
  rm $RETRY_SEMAPHORE_FILE
fi

if [ "$RETRY_SEMAPHORE_EXISTS" != "0" ] ; then
    echo "WILL RETRY"
fi

  

