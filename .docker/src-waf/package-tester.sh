#!/bin/bash

# Get a list of all installed packages
installed_packages=$(dpkg -l | grep "^ii" | awk '{print $2}')

# Loop through each package and check if it's being used by a running process
for pkg in $installed_packages; do
    ps aux | grep -i "/bin/$pkg" | grep -v grep > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "$pkg is currently being used by a running process"
    fi
done