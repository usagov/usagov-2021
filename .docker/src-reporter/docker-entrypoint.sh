#!/bin/sh
set -e

export https_proxy="$PROXYROUTE"
echo "The proxy connection URL is:"
echo "  $https_proxy"