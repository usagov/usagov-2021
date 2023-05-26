#!/bin/sh

INCLUDES_BASE="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"
if [ -f $INCLUDES_BASE/../../deploy/includes ]; then
  . $INCLUDES_BASE/../../deploy/includes
else
   echo Cannot find $INCLUDES_BASE/../../deploy/includes
   exit 1
fi

space=stage
cf disallow-space-ssh $space
if ! isSpaceSSHEnabled $space; then
   enableSpaceSSH $space
  if [ "$?" -ne "0" ]; then
    assertSpaceSSHEnabled $space
  fi
fi
