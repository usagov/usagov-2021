#!/usr/bin/env bash

set -uo pipefail

CSV_FILE="docs/USAGOV-2727_drupal-module-inventory.csv"
dryrun="--dryrun"

usage() {
  echo "Usage: $0 [--dryrun] <module_operation> <core_extension>"
  echo "  module_operation: enable|disable"
  echo "  core_extension: 0|1|true|false"
}

if [[ "${1:-}" == "--dryrun" ]]; then
  dryrun="--dryrun"
  shift
fi

if [[ $# -ne 2 ]]; then
  echo "Error: Invalid number of arguments."
  usage
  exit 1
fi

module_operation="$1"
core_extension_input="$2"

case "$module_operation" in
  enable|disable)
    ;;
  *)
    echo "Error: module_operation must be 'enable' or 'disable'."
    usage
    exit 1
    ;;
esac

case "${core_extension_input,,}" in
  1|true)
    core_extension="1"
    ;;
  0|false)
    core_extension="0"
    ;;
  *)
    echo "Error: core_extension must be one of: 0, 1, true, false."
    usage
    exit 1
    ;;
esac

if [[ ! -r "$CSV_FILE" ]]; then
  echo "Error: CSV file not found or unreadable: $CSV_FILE"
  exit 1
fi

if ! awk -F, 'NF != 4 {exit 1}' "$CSV_FILE"; then
  echo "Error: CSV file must have exactly 4 columns on every line: $CSV_FILE"
  exit 1
fi

if [[ "$module_operation" == "enable" ]]; then
  expected_col3="0"
else
  expected_col3="1"
fi

while IFS=, read -r module present_on_disk enabled_in_core_extension config_evidence; do
  # Skip header and blank lines.
  if [[ "$module" == "module" || -z "$module" ]]; then
    continue
  fi

  if [[ "$enabled_in_core_extension" != "$expected_col3" ]]; then
    continue
  fi

  if [[ "$enabled_in_core_extension" != "$core_extension" ]]; then
    continue
  fi

  if [[ "$enabled_in_core_extension" == "0" ]]; then
    drush_args=(pm:enable "$module" --yes)
  else
    drush_args=(pm:uninstall "$module" --yes)
  fi

  if [[ -n "$dryrun" ]]; then
    echo "bin/drush ${drush_args[*]}"
    continue
  fi

  if ! bin/drush "${drush_args[@]}"; then
    echo "Error: Drush command failed for module '$module': bin/drush ${drush_args[*]}"
    exit 2
  fi
done < "$CSV_FILE"
