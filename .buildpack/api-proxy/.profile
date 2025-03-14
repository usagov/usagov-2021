#!/bin/bash

PROXYROUTE=$(cf env $app | grep PROXYROUTE | awk '{print $2}')
if [ -n "$PROXYROUTE" ]; then
  cf set-env $APP HTTPS_PROXY $PROXYROUTE
  cf set-env $APP HTTP_PROXY $PROXYROUTE
  cf set-env $APP https_proxy $PROXYROUTE
  cf set-env $APP http_proxy $PROXYROUTE
fi