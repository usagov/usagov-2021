#!/bin/sh

if [ -n "$PROXYROUTE" ]; then
   export HTTPS_PROXY=$PROXYROUTE
   export HTTP_PROXY=$PROXYROUTE
   export https_proxy=$PROXYROUTE
   export http_proxy=$PROXYROUTE
fi

export dotprofile="true"