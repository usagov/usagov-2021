#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="/srv/usagov-2021"
DOCS_DIR=""
LINEAGE_CSV=""
EXECUTE_MODE=0
SKILL_GENERATOR="/home/ubuntu/.codex/skills/drupal-module-lineage-generator/scripts/generate_drupal_module_lineage.sh"
DRUSH_BIN=""

usage() {
  cat <<'EOF'
Usage:
  scripts/safe-module-removal.sh [--root <path>] [--docs-dir <path>] [--lineage <path>] [--execute]

Defaults:
  --root      /srv/usagov-2021
  --docs-dir  <root>/docs
  --lineage   <docs-dir>/drupal-module-lineage.csv

Behavior:
  - Dry run by default (no code mutations).
  - Use --execute to perform removals.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --root)
      ROOT_DIR="${2:-}"
      shift 2
      ;;
    --docs-dir)
      DOCS_DIR="${2:-}"
      shift 2
      ;;
    --lineage)
      LINEAGE_CSV="${2:-}"
      shift 2
      ;;
    --execute)
      EXECUTE_MODE=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage
      exit 2
      ;;
  esac
done

if [[ -z "$DOCS_DIR" ]]; then
  DOCS_DIR="$ROOT_DIR/docs"
fi
if [[ -z "$LINEAGE_CSV" ]]; then
  LINEAGE_CSV="$DOCS_DIR/drupal-module-lineage.csv"
fi

DRUSH_BIN="$ROOT_DIR/bin/drush"

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "Required command not found: $1" >&2
    exit 1
  }
}

require_cmd awk
require_cmd sort
require_cmd comm
require_cmd jq
require_cmd sqlite3

if [[ ! -d "$ROOT_DIR" ]]; then
  echo "Root directory not found: $ROOT_DIR" >&2
  exit 1
fi
if [[ ! -f "$LINEAGE_CSV" ]]; then
  echo "Lineage CSV not found: $LINEAGE_CSV" >&2
  exit 1
fi
if [[ ! -f "$ROOT_DIR/composer.lock" ]]; then
  echo "composer.lock not found: $ROOT_DIR/composer.lock" >&2
  exit 1
fi
if [[ ! -f "$ROOT_DIR/composer.json" ]]; then
  echo "composer.json not found: $ROOT_DIR/composer.json" >&2
  exit 1
fi
if [[ ! -x "$DRUSH_BIN" ]]; then
  echo "Drush wrapper not executable: $DRUSH_BIN" >&2
  exit 1
fi

mkdir -p "$DOCS_DIR"

PRE_CANDIDATES="$DOCS_DIR/module-removal-candidates.csv"
PRE_SKIPPED="$DOCS_DIR/module-removal-skipped.csv"
PKG_PLAN="$DOCS_DIR/package-removal-plan.csv"
PKG_SKIPPED="$DOCS_DIR/package-removal-skipped.csv"
RESULTS_CSV="$DOCS_DIR/module-removal-results.csv"
SUMMARY_MD="$DOCS_DIR/module-removal-summary.md"
PRE_LINEAGE_SNAPSHOT="$DOCS_DIR/drupal-module-lineage.pre-removal.csv"
POST_LINEAGE_SNAPSHOT="$DOCS_DIR/drupal-module-lineage.post-removal.csv"
LINEAGE_DIFF="$DOCS_DIR/drupal-module-lineage.diff.txt"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

SAFE_ROWS="$TMP_DIR/safe_rows.csv"
ALL_SKIPS_RAW="$TMP_DIR/all_skips_raw.csv"
PRE_CONTRIB_CANDIDATES="$TMP_DIR/pre_contrib_candidates.txt"
PRE_CUSTOM_CANDIDATES="$TMP_DIR/pre_custom_candidates.txt"
DRIFT_ENABLED="$TMP_DIR/drift_enabled.txt"
DRIFT_SKIPS="$TMP_DIR/drift_skips.csv"
CONTRIB_ALL_MODULES="$TMP_DIR/contrib_all_modules.csv"
MODULE_TO_PACKAGE="$TMP_DIR/module_to_package.csv"
PACKAGE_LOCK_LIST="$TMP_DIR/package_lock_list.txt"
PACKAGE_PLAN_RAW="$TMP_DIR/package_plan_raw.csv"
PACKAGE_SKIP_RAW="$TMP_DIR/package_skip_raw.csv"
FINAL_CONTRIB_PACKAGE_PLAN="$TMP_DIR/final_contrib_package_plan.txt"
FINAL_CUSTOM_PLAN="$TMP_DIR/final_custom_module_plan.txt"
RESULTS_RAW="$TMP_DIR/results_raw.csv"

echo "entity_type,name,action,status,reason" > "$RESULTS_RAW"

cp "$LINEAGE_CSV" "$PRE_LINEAGE_SNAPSHOT"

# Extract safe rows only.
awk -F',' 'NR>1 && $11=="yes" {print $0}' "$LINEAGE_CSV" > "$SAFE_ROWS"

# Preflight candidate and skip manifests.
{
  echo "module_name,module_type,project_key,parent_module,enabled_runtime_drush,enabled_in_core_extension,safe_to_flag_for_deletion,candidate_source"
  awk -F',' '
    $7=="yes" && ($2=="contrib" || $2=="custom") {
      print $1 "," $2 "," $3 "," $5 "," $8 "," $9 "," $11 ",preflight_safe_present_non_core"
    }
  ' "$SAFE_ROWS" | sort
} > "$PRE_CANDIDATES"

{
  echo "module_name,module_type,project_key,skip_reason,enabled_runtime_drush,enabled_in_core_extension,safe_to_flag_for_deletion"
  awk -F',' '
    $7!="yes" {
      print $1 "," $2 "," $3 ",not_present_in_codebase," $8 "," $9 "," $11
      next
    }
    !($2=="contrib" || $2=="custom") {
      print $1 "," $2 "," $3 ",excluded_module_type_core_or_other," $8 "," $9 "," $11
      next
    }
  ' "$SAFE_ROWS" | sort
} > "$PRE_SKIPPED"

tail -n +2 "$PRE_CANDIDATES" | awk -F',' '$2=="contrib"{print $1}' | sort -u > "$PRE_CONTRIB_CANDIDATES"
tail -n +2 "$PRE_CANDIDATES" | awk -F',' '$2=="custom"{print $1}' | sort -u > "$PRE_CUSTOM_CANDIDATES"

# Current enabled runtime modules (drift check source of truth).
"$DRUSH_BIN" pml --type=module --status=enabled --format=list \
  | awk '/^[a-z][a-z0-9_]*$/' \
  | sort -u > "$DRIFT_ENABLED"

# Drift skips at module level.
{
  echo "module_name,module_type,project_key,skip_reason,enabled_runtime_drush,enabled_in_core_extension,safe_to_flag_for_deletion"
  awk -F',' 'NR==FNR{en[$1]=1; next}
    NR>1 && en[$1] {
      print $1 "," $2 "," $3 ",drift_enabled_now," $8 "," $9 "," $11
    }' "$DRIFT_ENABLED" "$PRE_CANDIDATES" | sort -u
} > "$DRIFT_SKIPS"

# Build contrib module universe and module->package mapping from lineage + filesystem key.
awk -F',' 'NR>1 && $2=="contrib" && $7=="yes" {
  print $1 "," $3
}' "$LINEAGE_CSV" | sort -u > "$CONTRIB_ALL_MODULES"

awk -F',' '{
  module=$1
  project=$2
  if (project != "" && project != "n/a") {
    print module ",drupal/" project
  }
}' "$CONTRIB_ALL_MODULES" | sort -u > "$MODULE_TO_PACKAGE"

# Available drupal-module packages from composer.lock.
jq -r '.packages[] | select(.type=="drupal-module") | .name' "$ROOT_DIR/composer.lock" | sort -u > "$PACKAGE_LOCK_LIST"

# Package planning (all-or-nothing rule).
while IFS=',' read -r module package; do
  [[ -z "$module" || -z "$package" ]] && continue
  # Candidate package only if module is in preflight contrib candidates.
  if ! grep -qx "$module" "$PRE_CONTRIB_CANDIDATES"; then
    continue
  fi

  # Package must exist in composer.lock as drupal-module.
  if ! grep -qx "$package" "$PACKAGE_LOCK_LIST"; then
    echo "$package,$module,package_not_in_composer_lock" >> "$PACKAGE_SKIP_RAW"
    continue
  fi

  pkg_key="${package#drupal/}"
  all_count="$(awk -F',' -v k="$pkg_key" '$2==k{c++} END{print c+0}' "$CONTRIB_ALL_MODULES")"
  safe_count="$(awk -F',' -v k="$pkg_key" '$3==k && $2=="contrib" && $7=="yes" && $11=="yes"{c++} END{print c+0}' "$LINEAGE_CSV")"

  if [[ "$all_count" -eq 0 ]]; then
    echo "$package,$module,no_modules_discovered_for_package" >> "$PACKAGE_SKIP_RAW"
    continue
  fi
  if [[ "$all_count" -ne "$safe_count" ]]; then
    echo "$package,$module,mixed_package_not_all_safe" >> "$PACKAGE_SKIP_RAW"
    continue
  fi

  echo "$package" >> "$FINAL_CONTRIB_PACKAGE_PLAN"
done < "$MODULE_TO_PACKAGE"

if [[ -f "$FINAL_CONTRIB_PACKAGE_PLAN" ]]; then
  sort -u "$FINAL_CONTRIB_PACKAGE_PLAN" -o "$FINAL_CONTRIB_PACKAGE_PLAN"
else
  : > "$FINAL_CONTRIB_PACKAGE_PLAN"
fi

# Remove drifted modules from custom plan.
comm -23 "$PRE_CUSTOM_CANDIDATES" "$DRIFT_ENABLED" > "$FINAL_CUSTOM_PLAN"

# Drop packages with any currently enabled module.
while IFS= read -r package; do
  [[ -z "$package" ]] && continue
  pkg_key="${package#drupal/}"
  if awk -F',' -v k="$pkg_key" '$2==k{print $1}' "$CONTRIB_ALL_MODULES" | grep -Fxqf "$DRIFT_ENABLED"; then
    mod_name="$(awk -F',' -v p="$package" '$2==p{print $1; exit}' "$MODULE_TO_PACKAGE")"
    echo "$package,${mod_name:-unknown},package_contains_drift_enabled_module" >> "$PACKAGE_SKIP_RAW"
  else
    echo "$package" >> "$PACKAGE_PLAN_RAW"
  fi
done < "$FINAL_CONTRIB_PACKAGE_PLAN"

if [[ -f "$PACKAGE_PLAN_RAW" ]]; then
  sort -u "$PACKAGE_PLAN_RAW" -o "$PACKAGE_PLAN_RAW"
else
  : > "$PACKAGE_PLAN_RAW"
fi

{
  echo "package_name,source,reason"
  awk '{print $1 ",contrib_composer,all_modules_safe_and_not_drift_enabled"}' "$PACKAGE_PLAN_RAW"
} > "$PKG_PLAN"

{
  echo "package_name,module_name,skip_reason"
  if [[ -f "$PACKAGE_SKIP_RAW" ]]; then
    sort -u "$PACKAGE_SKIP_RAW"
  fi
} > "$PKG_SKIPPED"

# Build unified skip manifest (preflight + drift + package-derived module skips).
cat "$PRE_SKIPPED" > "$ALL_SKIPS_RAW"
tail -n +2 "$DRIFT_SKIPS" >> "$ALL_SKIPS_RAW" || true

# Add modules skipped due to package-level decisions.
if [[ -f "$PACKAGE_SKIP_RAW" ]]; then
  while IFS=',' read -r package _module reason; do
    [[ -z "$package" || -z "$reason" ]] && continue
    pkg_key="${package#drupal/}"
    awk -F',' -v k="$pkg_key" -v r="$reason" '
      NR>1 && $2=="contrib" && $3==k && $7=="yes" && $11=="yes" {
        print $1 "," $2 "," $3 "," r "," $8 "," $9 "," $11
      }' "$LINEAGE_CSV" >> "$ALL_SKIPS_RAW"
  done < "$PACKAGE_SKIP_RAW"
fi

{
  echo "module_name,module_type,project_key,skip_reason,enabled_runtime_drush,enabled_in_core_extension,safe_to_flag_for_deletion"
  awk -F',' 'NR>1 && $1!="" {print $0}' "$ALL_SKIPS_RAW" | sort -u
} > "$PRE_SKIPPED"

# Capture planned actions in results.
while IFS= read -r package; do
  [[ -z "$package" ]] && continue
  echo "package,$package,remove,$([[ $EXECUTE_MODE -eq 1 ]] && echo pending || echo planned_dry_run),eligible_contrib_package" >> "$RESULTS_RAW"
done < "$PACKAGE_PLAN_RAW"

while IFS= read -r module; do
  [[ -z "$module" ]] && continue
  echo "module,$module,remove,$([[ $EXECUTE_MODE -eq 1 ]] && echo pending || echo planned_dry_run),eligible_custom_module" >> "$RESULTS_RAW"
done < "$FINAL_CUSTOM_PLAN"

# Capture skips in results.
tail -n +2 "$PRE_SKIPPED" | awk -F',' '{print "module," $1 ",skip,skipped," $4}' >> "$RESULTS_RAW"
tail -n +2 "$PKG_SKIPPED" | awk -F',' '{print "package," $1 ",skip,skipped," $3}' | sort -u >> "$RESULTS_RAW"

# Execute removals when requested.
if [[ "$EXECUTE_MODE" -eq 1 ]]; then
  pushd "$ROOT_DIR" >/dev/null

  while IFS= read -r package; do
    [[ -z "$package" ]] && continue
    if composer remove --no-interaction "$package"; then
      awk -F',' -v p="$package" 'BEGIN{OFS=","} $1=="package" && $2==p && $3=="remove"{$4="removed"} {print $0}' "$RESULTS_RAW" > "$TMP_DIR/results.tmp" && mv "$TMP_DIR/results.tmp" "$RESULTS_RAW"
    else
      awk -F',' -v p="$package" 'BEGIN{OFS=","} $1=="package" && $2==p && $3=="remove"{$4="failed"; $5="composer_remove_failed"} {print $0}' "$RESULTS_RAW" > "$TMP_DIR/results.tmp" && mv "$TMP_DIR/results.tmp" "$RESULTS_RAW"
      popd >/dev/null
      echo "Composer removal failed for package: $package" >&2
      exit 3
    fi
  done < "$PACKAGE_PLAN_RAW"

  if composer install --no-interaction; then
    echo "operation,composer_install,reconcile,ok,composer_install_completed" >> "$RESULTS_RAW"
  else
    echo "operation,composer_install,reconcile,failed,composer_install_failed" >> "$RESULTS_RAW"
    popd >/dev/null
    exit 3
  fi

  while IFS= read -r module; do
    [[ -z "$module" ]] && continue
    custom_dir="$ROOT_DIR/web/modules/custom/$module"
    if [[ -d "$custom_dir" ]]; then
      rm -rf "$custom_dir"
      awk -F',' -v m="$module" 'BEGIN{OFS=","} $1=="module" && $2==m && $3=="remove"{$4="removed"} {print $0}' "$RESULTS_RAW" > "$TMP_DIR/results.tmp" && mv "$TMP_DIR/results.tmp" "$RESULTS_RAW"
    else
      awk -F',' -v m="$module" 'BEGIN{OFS=","} $1=="module" && $2==m && $3=="remove"{$4="failed"; $5="custom_module_path_missing"} {print $0}' "$RESULTS_RAW" > "$TMP_DIR/results.tmp" && mv "$TMP_DIR/results.tmp" "$RESULTS_RAW"
      popd >/dev/null
      echo "Custom module directory missing: $custom_dir" >&2
      exit 3
    fi
  done < "$FINAL_CUSTOM_PLAN"

  # Drupal tracking/state checks.
  if "$DRUSH_BIN" cr; then
    echo "operation,drush_cr,cache_rebuild,ok,cache_rebuild_completed" >> "$RESULTS_RAW"
  else
    echo "operation,drush_cr,cache_rebuild,failed,config_dependency_or_bootstrap_error" >> "$RESULTS_RAW"
    popd >/dev/null
    exit 4
  fi

  # Validate no active config dependencies reference missing module code.
  if "$DRUSH_BIN" php:eval '
    $module_ext = \Drupal::service("extension.list.module");
    $available = array_keys($module_ext->getList());
    $available_set = array_fill_keys($available, TRUE);
    $storage = \Drupal::service("config.storage");
    $missing = [];
    foreach ($storage->listAll() as $name) {
      $data = $storage->read($name);
      if (!is_array($data) || empty($data["dependencies"]["module"]) || !is_array($data["dependencies"]["module"])) {
        continue;
      }
      foreach ($data["dependencies"]["module"] as $dep) {
        if (!isset($available_set[$dep])) {
          $missing[] = $name . ":" . $dep;
        }
      }
    }
    if (!empty($missing)) {
      print implode(PHP_EOL, $missing);
      exit(2);
    }
    print "OK";
  ' >/tmp/module_removal_config_dep_check.out.$$ 2>&1; then
    echo "operation,drush_config_dependency_check,config_validation,ok,no_missing_module_dependencies" >> "$RESULTS_RAW"
  else
    cat /tmp/module_removal_config_dep_check.out.$$ >&2 || true
    rm -f /tmp/module_removal_config_dep_check.out.$$ || true
    echo "operation,drush_config_dependency_check,config_validation,failed,config_dependency_or_bootstrap_error" >> "$RESULTS_RAW"
    popd >/dev/null
    exit 4
  fi
  rm -f /tmp/module_removal_config_dep_check.out.$$ || true

  if composer validate --no-check-publish >/dev/null; then
    echo "operation,composer_validate,validation,ok,composer_validate_completed" >> "$RESULTS_RAW"
  else
    echo "operation,composer_validate,validation,failed,composer_validate_failed" >> "$RESULTS_RAW"
    popd >/dev/null
    exit 5
  fi

  popd >/dev/null
fi

# Post-removal lineage regeneration and validations.
if [[ -x "$SKILL_GENERATOR" ]]; then
  bash "$SKILL_GENERATOR" --root "$ROOT_DIR" --output "$POST_LINEAGE_SNAPSHOT" >/dev/null
else
  echo "Skill generator not found/executable: $SKILL_GENERATOR" >&2
  cp "$LINEAGE_CSV" "$POST_LINEAGE_SNAPSHOT"
fi

sort "$PRE_LINEAGE_SNAPSHOT" > "$TMP_DIR/pre_sorted.txt"
sort "$POST_LINEAGE_SNAPSHOT" > "$TMP_DIR/post_sorted.txt"
{
  echo "# Removed lines from lineage (pre -> post)"
  comm -23 "$TMP_DIR/pre_sorted.txt" "$TMP_DIR/post_sorted.txt" || true
  echo
  echo "# Added lines in lineage (post -> pre)"
  comm -13 "$TMP_DIR/pre_sorted.txt" "$TMP_DIR/post_sorted.txt" || true
} > "$LINEAGE_DIFF"

# Verify removed modules/packages absent where applicable.
if [[ "$EXECUTE_MODE" -eq 1 ]]; then
  while IFS=',' read -r entity name action status reason; do
    [[ "$entity" == "module" && "$action" == "remove" ]] || continue
    [[ "$status" == "removed" ]] || continue
    if awk -F',' -v m="$name" '
      NR>1 && $1==m && ($7=="yes" || $8=="yes" || $9=="yes"){bad=1}
      END{exit bad?1:0}
    ' "$POST_LINEAGE_SNAPSHOT"; then
      echo "verification,module:$name,verify,ok,absent_or_not_enabled_in_post_lineage" >> "$RESULTS_RAW"
    else
      echo "verification,module:$name,verify,failed,still_present_or_enabled_in_post_lineage" >> "$RESULTS_RAW"
    fi
  done < "$RESULTS_RAW"

  while IFS=',' read -r entity name action status reason; do
    [[ "$entity" == "package" && "$action" == "remove" ]] || continue
    [[ "$status" == "removed" ]] || continue
    if jq -r '.packages[] | select(.type=="drupal-module") | .name' "$ROOT_DIR/composer.lock" | grep -qx "$name"; then
      echo "verification,package:$name,verify,failed,package_still_in_composer_lock" >> "$RESULTS_RAW"
    else
      echo "verification,package:$name,verify,ok,package_absent_from_composer_lock" >> "$RESULTS_RAW"
    fi
  done < "$RESULTS_RAW"
else
  echo "verification,dry_run,verify,not_applicable,execution_not_performed" >> "$RESULTS_RAW"
fi

cp "$RESULTS_RAW" "$RESULTS_CSV"

# Summary report.
total_safe="$(awk -F',' 'NR>1 && $11=="yes"{c++} END{print c+0}' "$LINEAGE_CSV")"
pre_candidates_count="$(awk 'NR>1{c++} END{print c+0}' "$PRE_CANDIDATES")"
pre_skipped_count="$(awk 'NR>1{c++} END{print c+0}' "$PRE_SKIPPED")"
package_plan_count="$(awk 'NR>1{c++} END{print c+0}' "$PKG_PLAN")"
package_skip_count="$(awk 'NR>1{c++} END{print c+0}' "$PKG_SKIPPED")"

removed_count="$(awk -F',' 'NR>1 && $4=="removed"{c++} END{print c+0}' "$RESULTS_CSV")"
planned_count="$(awk -F',' 'NR>1 && $4=="planned_dry_run"{c++} END{print c+0}' "$RESULTS_CSV")"
failed_count="$(awk -F',' 'NR>1 && $4=="failed"{c++} END{print c+0}' "$RESULTS_CSV")"
skipped_count="$(awk -F',' 'NR>1 && $3=="skip"{c++} END{print c+0}' "$RESULTS_CSV")"

{
  echo "# Safe Module Removal Summary"
  echo
  echo "Mode: $([[ $EXECUTE_MODE -eq 1 ]] && echo "execute" || echo "dry-run")"
  echo
  echo "## Inputs"
  echo "- Root: \`$ROOT_DIR\`"
  echo "- Lineage: \`$LINEAGE_CSV\`"
  echo
  echo "## Preflight Counts"
  echo "- Total safe modules in lineage: **$total_safe**"
  echo "- Preflight candidates (contrib/custom present): **$pre_candidates_count**"
  echo "- Preflight/drift/package skipped modules: **$pre_skipped_count**"
  echo "- Package removal plan entries: **$package_plan_count**"
  echo "- Package skipped entries: **$package_skip_count**"
  echo
  echo "## Result Counts"
  echo "- Removed: **$removed_count**"
  echo "- Planned (dry run): **$planned_count**"
  echo "- Skipped: **$skipped_count**"
  echo "- Failed: **$failed_count**"
  echo
  echo "## Artifacts"
  echo "- \`$PRE_CANDIDATES\`"
  echo "- \`$PRE_SKIPPED\`"
  echo "- \`$PKG_PLAN\`"
  echo "- \`$PKG_SKIPPED\`"
  echo "- \`$RESULTS_CSV\`"
  echo "- \`$PRE_LINEAGE_SNAPSHOT\`"
  echo "- \`$POST_LINEAGE_SNAPSHOT\`"
  echo "- \`$LINEAGE_DIFF\`"
} > "$SUMMARY_MD"

echo "Generated:"
echo "  $PRE_CANDIDATES"
echo "  $PRE_SKIPPED"
echo "  $PKG_PLAN"
echo "  $PKG_SKIPPED"
echo "  $RESULTS_CSV"
echo "  $SUMMARY_MD"
echo "Done."
