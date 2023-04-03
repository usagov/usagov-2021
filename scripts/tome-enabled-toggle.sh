#!/bin/sh

source /etc/profile
TOME_DISABLED_STATE=$1

if [ x$TOME_DISABLED_STATE == x0 ]; then
    drush sdel usagov.tome_run_disabled
    echo "Tome Disabled: " $(drush sget usagov.tome_run_disabled)
elif [ x$TOME_DISABLED_STATE == x1 ]; then
    drush sset usagov.tome_run_disabled 1
    echo "Tome Disabled: " $(drush sget usagov.tome_run_disabled)
else
    echo "Usage: $0 <mode>"
    echo "       where <mode> is 0 or 1"
fi
