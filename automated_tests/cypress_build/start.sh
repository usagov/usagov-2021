#!/bin/bash

if [ ! -f node_modules ]; then
    ln -s ../node_modules node_modules
fi

# Keep the container running.
tail -f /dev/null
