#!/bin/sh

source /etc/profile
TOME_DISABLED_STATE=$1

CHECK_DB_CONNECTION=$(drush status | grep -E 'Database\s*:' | awk '{print $3}')

if [ "$CHECK_DB_CONNECTION" != "Connected" ]; then
  echo "Drush cannot connect to the database. Exiting. Drush status says database is: " "$CHECK_DB_CONNECTION"
  exit 1
fi

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
