#!/bin/sh
/etc/nginx/dynamic/deny_domain_by_ip.sh
/usr/sbin/nginx -s reload
