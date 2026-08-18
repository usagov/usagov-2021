#!/bin/sh
# ===================================================================
# DOWNSYNC BEHAVIORAL TESTS
# ===================================================================
# Covers the failure paths from the backup/deployment audit that reach
# deploy.sh's downsync: it must not report success after a failed decompression,
# import or upload; it must refuse to run without a verified safety backup; it
# must relay public files without corrupting them; and it must put Drupal state
# and the CF target back on every exit path.
#
# Fully hermetic: fake cf / aws / drush and two fake spaces, no network, no
# Cloud Foundry. Run from anywhere:
#     sh scripts/tests/downsync-tests.sh
# Optional: SHELL_UNDER_TEST=dash sh scripts/tests/downsync-tests.sh
# ===================================================================

set -u

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
REPO_ROOT=$(cd "$SCRIPT_DIR/../.." && pwd)
SH="${SHELL_UNDER_TEST:-sh}"

. "$SCRIPT_DIR/downsync-fakes.sh"

DS_ROOT=$(mktemp -d) || { echo "❌ Could not create sandbox"; exit 1; }
export DS_ROOT
trap 'rm -rf "$DS_ROOT"' EXIT INT TERM HUP

DEPLOY="$DS_ROOT/www/scripts/devops/deploy.sh"
TAG="AUTO-prod-t1-2026-01-01-0"

PASS=0
FAIL=0
check() {
    if [ "$2" = yes ]; then
        PASS=$((PASS + 1)); echo "  ok   $1"
    else
        FAIL=$((FAIL + 1)); echo "  FAIL $1"
    fi
}
yn() { [ "$1" = "$2" ] && echo yes || echo no; }
nz() { [ "$1" -ne 0 ] && echo yes || echo no; }
has() { echo "$OUT" | grep -q "$1" && echo yes || echo no; }
hasnt() { echo "$OUT" | grep -q "$1" && echo no || echo yes; }

# The scripts are copied rather than used in place: init_backup_system prepends
# $PROJECT_ROOT/vendor/bin to PATH, which in the real repo shadows the fake drush
# with the project's own.
mkdir -p "$DS_ROOT/www"
cp -R "$REPO_ROOT/scripts" "$DS_ROOT/www/scripts"
write_downsync_fakes "$DS_ROOT"
export PATH="$DS_ROOT/bin:$PATH"

HAVE_PY=yes
command -v python3 >/dev/null 2>&1 || HAVE_PY=no

seed() {
    rm -rf "$DS_ROOT/spaces" "$DS_ROOT/state"
    mkdir -p "$DS_ROOT/state"
    : > "$DS_ROOT/state/cf-calls.log"
    # manager.sh enforces a real rate-limit window between backups via a /tmp
    # marker, and the backup lock is a real object; clear both so consecutive
    # cases are not throttled or blocked by their predecessor.
    rm -f /tmp/backup_rate_limit_* 2>/dev/null || true
    rm -f "$DS_ROOT/www/-" 2>/dev/null || true
    unset FAKE_SSH_FAIL FAKE_TARGET_FAIL FAKE_DUMP_FAIL FAKE_IMPORT_FAIL \
          FAKE_IMPORT_FAIL_ONCE FAKE_CR_FAIL FAKE_UPDATEDB_FAIL FAKE_CIM_FAIL \
          FAKE_FAIL_CP FAKE_FAIL_SYNC FAKE_FAIL_LS FAKE_TAR_DROP FAKE_TAR_INJECT 2>/dev/null || true

    # --- FROM space (prod): holds the backup set to be copied ---
    p="$DS_ROOT/spaces/prod"
    mkdir -p "$p/auto-backups/database" "$p/auto-backups/public_backup/$TAG" "$p/web" "$p/cms/public"
    printf 'CREATE TABLE `node` (`nid` int);\nINSERT INTO `node` VALUES (777);\n-- SOURCE-PROD-DATA\n' > "$DS_ROOT/src.sql"
    { printf -- '-- MySQL dump 10.13\n-- Host: x  Database: d\n'; cat "$DS_ROOT/src.sql"; printf -- '-- Dump completed on 2026-01-01\n'; } > "$DS_ROOT/src-full.sql"
    gzip -c "$DS_ROOT/src-full.sql" > "$p/auto-backups/database/$TAG.sql.gz"
    i=1
    while [ "$i" -le 3 ]; do echo "prod-public-$i" > "$p/auto-backups/public_backup/$TAG/file$i.bin"; i=$((i+1)); done
    echo "prod-live-db" > "$p/database.sql"

    # --- TO space (dev): has its own live content to be overwritten ---
    d="$DS_ROOT/spaces/dev"
    mkdir -p "$d/auto-backups/database" "$d/auto-backups/public_backup" "$d/auto-backups/web-backup" "$d/web" "$d/cms/public"
    for n in 1 2; do echo "dev-live-static-$n" > "$d/web/devstatic$n.html"; done
    for n in 1 2 3 4; do echo "dev-live-public-$n" > "$d/cms/public/devpub$n.bin"; done
    printf 'CREATE TABLE `node` (`nid` int);\nINSERT INTO `node` VALUES (1);\n-- DEV-ORIGINAL-DATA\n' > "$d/database.sql"
    rm -rf "$d/drush-state"
    printf 'usagov.tome_run_disabled=1\n' > "$d/drush-state"

    printf 'dev' > "$DS_ROOT/state/space"
}

run_downsync() {
    OUT=$(cd "$DS_ROOT/www" && printf 'yes\n' | "$SH" "$DEPLOY" downsync prod dev "$TAG" 2>&1)
    RC=$?
    [ -n "${DEBUG_OUT:-}" ] && { echo "--- OUT (rc=$RC) ---"; echo "$OUT"; echo "--- end ---"; }
    return 0
}

dev_public_count() { find "$DS_ROOT/spaces/dev/cms/public" -type f 2>/dev/null | grep -c . ; }
dev_db_has() { grep -q "$1" "$DS_ROOT/spaces/dev/database.sql" 2>/dev/null && echo yes || echo no; }
dev_state() { grep '^usagov.tome_run_disabled=' "$DS_ROOT/spaces/dev/drush-state" 2>/dev/null | tail -1 | sed 's/.*=//'; }
dev_maint() { grep '^system.maintenance_mode=' "$DS_ROOT/spaces/dev/drush-state" 2>/dev/null | tail -1 | sed 's/.*=//'; }
current_space() { cat "$DS_ROOT/state/space" 2>/dev/null; }

echo "== downsync tests (shell under test: $SH) =="

# deploy.sh declares #!/bin/sh but still uses printf '%q', a Bash extension. Under
# dash the affected arguments are lost and a large number of cases below fail for
# that one reason. Say so up front rather than leaving it to be rediscovered.
if ! "$SH" -c "printf '%q' x" >/dev/null 2>&1 &&
   command grep -q '%q' "$REPO_ROOT/scripts/devops/deploy.sh" 2>/dev/null; then
    echo "   NOTE: $SH has no printf %q and deploy.sh still uses it (audit finding N-11)."
    echo "         Expect widespread failures below until that is fixed; they are not"
    echo "         regressions in the downsync logic itself."
fi

echo
echo "T1: the happy path copies prod data into dev and reports success"
seed
run_downsync
check "exit 0" "$(yn "$RC" 0)"
check "dev database now holds prod data" "$(dev_db_has 'SOURCE-PROD-DATA')"
check "dev original data replaced" "$([ "$(dev_db_has 'DEV-ORIGINAL-DATA')" = no ] && echo yes || echo no)"
check "dev public files replaced (3 from prod)" "$(yn "$(dev_public_count)" 3)"
check "the safety backup was verified, not assumed" "$(has 'Safety backup verified')"
check "the maintained restore path was used" "$(has 'maintained restore path')"
check "the restore made its own recovery point" "$(ls "$DS_ROOT/spaces/dev/auto-backups/database" 2>/dev/null | grep -q PRERESTORE && echo yes || echo no)"
check "reports completion" "$(has 'Downsync complete')"
check "maintenance mode cleared afterwards" "$(yn "$(dev_maint)" 0)"
check "tome left disabled, as it was found" "$(yn "$(dev_state)" 1)"
check "CF target restored to the original space" "$(yn "$(current_space)" dev)"

echo
echo "T2: no verified safety backup means nothing is touched"
seed
FAKE_DUMP_FAIL=1; export FAKE_DUMP_FAIL
run_downsync
unset FAKE_DUMP_FAIL
check "exit non-zero" "$(nz "$RC")"
check "refuses to continue without a recovery point" "$(has 'Refusing to continue')"
check "dev database untouched" "$(dev_db_has 'DEV-ORIGINAL-DATA')"
check "dev public files untouched (4)" "$(yn "$(dev_public_count)" 4)"
check "does not claim completion" "$(hasnt 'Downsync complete')"

echo
echo "T3: the shell mechanism behind the original defect"
# The old remote commands chained steps with ';' so the status came from the final
# 'rm'. These two lines are the whole defect and the whole fix.
sh -c 'false; rm -f /tmp/ds-absent-file' 2>/dev/null
check "';' chaining masks a mid-chain failure" "$(yn "$?" 0)"
sh -c 'set -e; false; rm -f /tmp/ds-absent-file' 2>/dev/null
check "'set -e' surfaces it" "$(nz "$?")"

echo
echo "T3b: a mid-chain remote failure aborts instead of reporting success"
seed
FAKE_FAIL_CP='downsync_db'; export FAKE_FAIL_CP
run_downsync
unset FAKE_FAIL_CP
check "exit non-zero" "$(nz "$RC")"
check "reports the staging failure" "$(has 'Failed to stage the database backup')"
check "dev database untouched" "$(dev_db_has 'DEV-ORIGINAL-DATA')"
check "does not claim completion" "$(hasnt 'Downsync complete')"

echo
echo "T4: a truncated database download is rejected before staging"
seed
printf 'not-a-gzip' > "$DS_ROOT/spaces/prod/auto-backups/database/$TAG.sql.gz"
run_downsync
check "exit non-zero" "$(nz "$RC")"
check "reports an invalid archive" "$(has 'not a valid gzip archive')"
check "dev database untouched" "$(dev_db_has 'DEV-ORIGINAL-DATA')"
check "nothing staged into dev" "$(ls "$DS_ROOT/spaces/dev/auto-backups/database" 2>/dev/null | grep -q "^$TAG" && echo no || echo yes)"

echo
echo "T5: an empty public backup is refused (restoring it would delete live files)"
seed
rm -f "$DS_ROOT/spaces/prod/auto-backups/public_backup/$TAG"/*
run_downsync
check "exit non-zero" "$(nz "$RC")"
check "reports that the backup holds no files" "$(echo "$OUT" | grep -qE 'contained no files|empty or invalid' && echo yes || echo no)"
check "dev public files intact (4)" "$(yn "$(dev_public_count)" 4)"

echo
echo "T6: an existing tag in the target is refused, database side"
seed
echo "pre-existing" | gzip -c > "$DS_ROOT/spaces/dev/auto-backups/database/$TAG.sql.gz"
run_downsync
check "exit non-zero" "$(nz "$RC")"
check "names the collision" "$(has 'already holds a database backup named')"
check "dev database untouched" "$(dev_db_has 'DEV-ORIGINAL-DATA')"

echo
echo "T6b: an existing tag in the target is refused, public side"
seed
mkdir -p "$DS_ROOT/spaces/dev/auto-backups/public_backup/$TAG"
echo "pre-existing" > "$DS_ROOT/spaces/dev/auto-backups/public_backup/$TAG/leftover.bin"
run_downsync
check "exit non-zero" "$(nz "$RC")"
check "names the public collision" "$(has 'already holds public files named')"
check "the pre-existing object was not deleted" "$([ -f "$DS_ROOT/spaces/dev/auto-backups/public_backup/$TAG/leftover.bin" ] && echo yes || echo no)"

echo
echo "T7: a failed import is surfaced and state is restored"
seed
FAKE_IMPORT_FAIL_ONCE="$DS_ROOT/state/import-fail"; export FAKE_IMPORT_FAIL_ONCE
: > "$FAKE_IMPORT_FAIL_ONCE"
run_downsync
unset FAKE_IMPORT_FAIL_ONCE
check "exit non-zero" "$(nz "$RC")"
check "does not claim completion" "$(hasnt 'Downsync complete')"
check "prints the recovery hint" "$(has 'DOWNSYNC FAILED')"
check "maintenance mode cleared by the trap" "$(yn "$(dev_maint)" 0)"
check "tome restored to the value it was found with" "$(yn "$(dev_state)" 1)"
check "CF target restored" "$(yn "$(current_space)" dev)"

echo
echo "T8: a post-import step failure is reported, not swallowed"
seed
FAKE_UPDATEDB_FAIL=1; export FAKE_UPDATEDB_FAIL
run_downsync
unset FAKE_UPDATEDB_FAIL
check "exit non-zero" "$(nz "$RC")"
check "names the failing step" "$(has 'database updates')"
check "states that the data was copied" "$(has 'completed the data copy')"
check "the data did land in dev" "$(dev_db_has 'SOURCE-PROD-DATA')"
check "state still restored" "$(yn "$(dev_maint)" 0)"

echo
echo "T9: public files are never made world-readable"
seed
run_downsync
check "exit 0" "$(yn "$RC" 0)"
check "no --acl public-read against cms/public" \
    "$(grep -E 'cms/public.*--acl public-read|--acl public-read.*cms/public' "$DS_ROOT/state/cf-calls.log" >/dev/null 2>&1 && echo no || echo yes)"

echo
echo "T10: a target-switch failure leaves the environment untouched"
seed
FAKE_TARGET_FAIL=prod; export FAKE_TARGET_FAIL
run_downsync
unset FAKE_TARGET_FAIL
check "exit non-zero" "$(nz "$RC")"
check "dev database untouched" "$(dev_db_has 'DEV-ORIGINAL-DATA')"
check "dev public intact (4)" "$(yn "$(dev_public_count)" 4)"

echo
echo "T11: why the local tar cannot be part of the transport (mechanism)"
if [ "$HAVE_PY" = no ]; then
    echo "  SKIP no python3, cannot emulate BusyBox tar"
else
    adroot="$DS_ROOT/state/ad"; rm -rf "$adroot"; mkdir -p "$adroot/2022-03"
    echo real > "$adroot/2022-03/photo.jpg"
    echo legit > "$adroot/._legit.bin"   # a public file whose own name starts with ._
    members() { python3 -c "import sys,tarfile; [print(m.name) for m in tarfile.open(sys.argv[1])]" "$1"; }
    /usr/bin/tar -C "$adroot" -czf "$DS_ROOT/state/plain.tgz" . 2>/dev/null
    if members "$DS_ROOT/state/plain.tgz" | grep -qE '/\._|^\._'; then
        check "repacking a tree emits AppleDouble side-cars" yes
    else
        # Only macOS tar does this; elsewhere the hazard does not exist.
        echo "  SKIP this tar emits no side-cars (not macOS bsdtar)"
    fi
    rm -rf "$DS_ROOT/state/unpacked"; mkdir -p "$DS_ROOT/state/unpacked"
    "$DS_ROOT/container-bin/tar" -C "$adroot" czf - . > "$DS_ROOT/state/busybox.tgz"
    COPYFILE_DISABLE=1 /usr/bin/tar --no-mac-metadata -xzf "$DS_ROOT/state/busybox.tgz" \
        -C "$DS_ROOT/state/unpacked" 2>/dev/null
    unpack_rc=$?
    if [ -f "$DS_ROOT/state/unpacked/._legit.bin" ]; then
        echo "  note this tar preserves ._ named members on extract"
    else
        check "unpacking reports success ($unpack_rc) yet drops the ._ named file" "$(yn "$unpack_rc" 0)"
    fi
fi

echo
echo "T11b: end to end, the target gets exactly the source files and nothing else"
if [ "$HAVE_PY" = no ]; then
    echo "  SKIP no python3"
else
    seed
    echo "prod-legit" > "$DS_ROOT/spaces/prod/auto-backups/public_backup/$TAG/._legit.bin"
    : > "$DS_ROOT/state/local-tar-calls.log"
    # Records any tar that resolves on the local PATH. Container commands resolve
    # container-bin/tar first, so anything logged here ran on the operator's machine.
    cat > "$DS_ROOT/bin/tar" <<'LOGTAR'
#!/bin/sh
echo "tar $*" >> "${DS_ROOT}/state/local-tar-calls.log"
exec /usr/bin/tar "$@"
LOGTAR
    chmod +x "$DS_ROOT/bin/tar"
    run_downsync
    rm -f "$DS_ROOT/bin/tar"
    staged_junk=$(find "$DS_ROOT/spaces/dev/auto-backups/public_backup/$TAG" -name '._*' -type f 2>/dev/null | grep -vc '\._legit\.bin$')
    live_junk=$(find "$DS_ROOT/spaces/dev/cms/public" -name '._*' -type f 2>/dev/null | grep -vc '\._legit\.bin$')
    check "exit 0" "$(yn "$RC" 0)"
    check "deploy.sh ran no local tar at all" "$([ -s "$DS_ROOT/state/local-tar-calls.log" ] && echo no || echo yes)"
    check "dev live public holds exactly the 4 source files" "$(yn "$(dev_public_count)" 4)"
    check "no side-car objects were staged" "$(yn "$staged_junk" 0)"
    check "no side-car objects reached live public files" "$(yn "$live_junk" 0)"
    check "the ._legit.bin public file survived the relay" \
        "$([ -f "$DS_ROOT/spaces/dev/cms/public/._legit.bin" ] && echo yes || echo no)"
    check "the file count came from the source bucket" "$(has 'Public files downloaded and verified (4 files')"
    check "the staged count was verified against it" "$(has 'Public files staged and verified (4 objects)')"
fi

echo
echo "T12: a lossy relay is caught against the source bucket and discarded"
if [ "$HAVE_PY" = no ]; then
    echo "  SKIP no python3"
else
    seed
    FAKE_TAR_DROP='file2.bin'; export FAKE_TAR_DROP
    run_downsync
    unset FAKE_TAR_DROP
    check "exit non-zero" "$(nz "$RC")"
    check "reports the count mismatch against the source" "$(has 'does not match prod')"
    check "the staged copy was discarded" "$(has 'Staged backup removed')"
    check "no staged database objects left behind" \
        "$(ls "$DS_ROOT/spaces/dev/auto-backups/database" 2>/dev/null | grep -q "^$TAG" && echo no || echo yes)"
    check "dev database untouched" "$(dev_db_has 'DEV-ORIGINAL-DATA')"
    check "dev public intact (4)" "$(yn "$(dev_public_count)" 4)"
fi

echo
echo "T13: gained files are caught the same way and the staged copy is discarded"
if [ "$HAVE_PY" = no ]; then
    echo "  SKIP no python3"
else
    seed
    FAKE_TAR_INJECT=2; export FAKE_TAR_INJECT
    run_downsync
    unset FAKE_TAR_INJECT
    check "exit non-zero" "$(nz "$RC")"
    check "reports the staged count mismatch" "$(has 'Staged public file count (5) does not match prod (3)')"
    check "reports removing the staged backup" "$(has 'Staged backup removed')"
    check "no staged public objects left behind" \
        "$(yn "$(find "$DS_ROOT/spaces/dev/auto-backups/public_backup/$TAG" -type f 2>/dev/null | grep -c .)" 0)"
    check "dev database untouched" "$(dev_db_has 'DEV-ORIGINAL-DATA')"
    check "dev public intact (4)" "$(yn "$(dev_public_count)" 4)"
    check "the safety backup was kept" \
        "$(ls "$DS_ROOT/spaces/dev/auto-backups/database" 2>/dev/null | grep -q DOWNSYNC && echo yes || echo no)"
fi

echo
echo "== $PASS passed, $FAIL failed =="
[ "$FAIL" -eq 0 ]
