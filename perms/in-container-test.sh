#!/bin/sh

grep -niHR utmp /etc/passwd

# sed -i s/utmp:x:100:/utmp:x:406/g /etc/passwd

# POST=
# [ "$FORCEIDS" == "1" ] && POST='.forced'

# USER=$(whoami)
# echo
# echo "Creating file(${USER}${POST}) as user($USER)"
# rm -f /app/${USER}${POST}
# touch /app/${USER}${POST}
# echo $USER > /app/${USER}${POST}

# echo
# echo "Container permissions for file"
# echo "file created by "$(cat /app/${USER}${POST})
# ls -al /app/${USER}${POST}
# ls -n  /app/${USER}${POST}
