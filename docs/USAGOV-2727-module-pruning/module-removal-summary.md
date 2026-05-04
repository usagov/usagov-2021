# Safe Module Removal Summary

Mode: dry-run

## Inputs
- Root: `/srv/usagov-2021`
- Lineage: `/srv/usagov-2021/docs/drupal-module-lineage.csv`

## Preflight Counts
- Total safe modules in lineage: **50**
- Preflight candidates (contrib/custom present): **9**
- Preflight/drift/package skipped modules: **46**
- Package removal plan entries: **2**
- Package skipped entries: **5**

## Result Counts
- Removed: **0**
- Planned (dry run): **4**
- Skipped: **47**
- Failed: **0**

## Artifacts
- `/srv/usagov-2021/docs/module-removal-candidates.csv`
- `/srv/usagov-2021/docs/module-removal-skipped.csv`
- `/srv/usagov-2021/docs/package-removal-plan.csv`
- `/srv/usagov-2021/docs/package-removal-skipped.csv`
- `/srv/usagov-2021/docs/module-removal-results.csv`
- `/srv/usagov-2021/docs/drupal-module-lineage.pre-removal.csv`
- `/srv/usagov-2021/docs/drupal-module-lineage.post-removal.csv`
- `/srv/usagov-2021/docs/drupal-module-lineage.diff.txt`
