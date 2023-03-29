#!/bin/sh

source /etc/profile

MAINT_MODE_STATE=$1

if [ x$MAINT_MODE_STATE == x0 -o x$MAINT_MODE_STATE == x1 ]; then
    drush sset system.maintenance_mode $MAINT_MODE_STATE
    drush cr
    echo "Site Maintenance mode: " $(drush sget system.maintenance_mode)
else
    echo "Usage: $0 <mode>"
    echo "       where <mode> is 0 or 1"
fi
