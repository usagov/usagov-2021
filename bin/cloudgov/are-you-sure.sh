#!/bin/bash

usage="
$0: Are you sure?!

Usage:
   $0 -h

Options:
-h:            show help and exit

NOTES:
Interactive.  Y/N input.
"

PREAMBLE=$(printf "$( ( cd -- "$(dirname "$0")" >/dev/null 2>&1 || exit ) && pwd -P )/bin/includes/preamble.sh" | sed -E 's/bin\/.*\/includes/bin\/includes/g')
if [ -f "$PREAMBLE" ]; then
  # shellcheck source=bin/includes/preamble.sh
  . "$PREAMBLE"
else
   echo Cannot find preamble at "$PREAMBLE"
   exit 1
fi

read -p "Are you sure? " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    # handle exits from shell or function but don't exit interactive shell
    [[ "$0" = "$BASH_SOURCE" ]] && exit 1 || return 1;
fi