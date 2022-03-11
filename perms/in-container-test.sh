#!/bin/sh

POST=""
[ "$FORCEIDS" == "1" ] && POST='-forced'

USER=$(whoami)
MYUID=$(id -u)
MYGID=$(id -g)

echo
echo "Creating file(${USER}${POST}) as user($USER) with UID($MYUID) and GUI($MYGID)"
rm -f /perms/${USER}${POST}
touch /perms/${USER}${POST}
echo $USER > /perms/${USER}${POST}

echo
echo "Container permissions for file"
echo "file created by "$(cat /perms/${USER}${POST})
ls -al /perms/${USER}${POST}
ls -n  /perms/${USER}${POST}
