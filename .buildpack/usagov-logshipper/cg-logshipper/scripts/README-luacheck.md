# README: luacheck

The `dev-utils/luacheck-scripts.sh` script will spin up a small docker container and run luacheck on all `.lua` files in this directory.

luacheck documentation: https://luacheck.readthedocs.io/en/stable/config.html

A `--luacheck:ignore` comment after a function declaration suppresses a warning about setting a global variable and that this is an unused function. (Scripts that are called only from fluent bit will appear to be unused.) It does _not_ tell luacheck to ignore the rest of the function. 

