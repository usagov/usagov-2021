#!/bin/sh
# ===================================================================
# FAKES FOR THE DOWNSYNC TESTS
# ===================================================================
# Writes fake cf / aws / drush / container-tar into $1/bin and $1/container-bin.
# Sourced by downsync-tests.sh; not useful on its own.
# ===================================================================

write_downsync_fakes() {
    local root="$1"
    mkdir -p "$root/bin" "$root/container-bin"

    # --- cf ---------------------------------------------------------------
    # "cf ssh cms -c CMD" runs CMD locally with /var/www rewritten onto the fake
    # container tree, so the remote scripts' `set -e` semantics are exercised for
    # real. Each space gets its own bucket directory.
    cat > "$root/bin/cf" <<'FAKECF'
#!/bin/sh
H3="${DS_ROOT:?DS_ROOT not set}"
CUR="$H3/state/space"
echo "cf $*" >> "$H3/state/cf-calls.log"
case "$1" in
target)
    if [ "$2" = "-s" ]; then
        if [ -n "$FAKE_TARGET_FAIL" ] && [ "$3" = "$FAKE_TARGET_FAIL" ]; then
            echo "FAILED: cannot target $3" >&2; exit 1
        fi
        printf '%s' "$3" > "$CUR"; exit 0
    fi
    printf 'api endpoint: https://fake\norg: fake-org\nspace:  %s\n' "$(cat "$CUR" 2>/dev/null)"
    exit 0 ;;
ssh)
    cmd=""; shift
    while [ $# -gt 0 ]; do
        case "$1" in -c) cmd="$2"; shift 2 ;; *) shift ;; esac
    done
    space=$(cat "$CUR" 2>/dev/null)
    [ -n "$space" ] || { echo "no space targeted" >&2; exit 1; }
    if [ -n "$FAKE_SSH_FAIL" ] && echo "$cmd" | grep -q "$FAKE_SSH_FAIL"; then
        echo "fake cf ssh: injected failure" >&2; exit 1
    fi
    # Container paths onto the fake tree; drop the profile sourcing, which would
    # otherwise pull in the host environment.
    cmd=$(printf '%s' "$cmd" | sed "s|/var/www|$H3/www|g; s|\. /etc/profile|:|g")
    # container-bin first: the container's tar is BusyBox, which has no concept of
    # AppleDouble. That difference is the point of several tests below.
    FAKE_SPACE="$space" \
    BUCKET_NAME="${space}-bucket" \
    APP_SPACE="$space" \
    CONTAINER_TAG="t1" \
    S3_EXTRA_PARAMS="" \
    FS3="$H3/spaces/$space" \
    DSTATE="$H3/spaces/$space/drush-state" \
    FAKE_DB="$H3/spaces/$space/database.sql" \
    PATH="$H3/container-bin:$H3/bin:$PATH" \
    sh -c "$cmd"
    exit $? ;;
esac
exit 0
FAKECF

    # --- aws --------------------------------------------------------------
    # Directory-backed S3: real ls/cp/sync --delete semantics, plus the
    # If-None-Match precondition the backup lock depends on.
    cat > "$root/bin/aws" <<'FAKEAWS'
#!/bin/sh
R="${FS3:?FS3 not set}"
key_of() { echo "$1" | sed 's|^s3://[^/]*/||; s|/$||'; }
resolve() { case "$1" in s3://*) echo "$R/$(key_of "$1")" ;; *) echo "${1%/}" ;; esac; }
svc="$1"; shift; act="$1"; shift
case "$svc $act" in
"s3 ls")
    t=""; rec=0
    for a in "$@"; do case "$a" in --recursive) rec=1 ;; s3://*) [ -z "$t" ] && t="$a" ;; esac; done
    [ -n "$FAKE_FAIL_LS" ] && echo "$t" | grep -q "$FAKE_FAIL_LS" && { echo "ls failed" >&2; exit 1; }
    p=$(resolve "$t")
    if [ "$rec" = 1 ]; then
        [ -d "$p" ] || { [ -f "$p" ] && { echo "2026-01-01 00:00:00 10 $(key_of "$t")"; exit 0; }; exit 1; }
        found=$(cd "$p" && find . -type f | sed 's|^\./||')
        [ -z "$found" ] && exit 1
        pre=$(key_of "$t")
        echo "$found" | sed "s|^|2026-01-01 00:00:00 10 $pre/|"
        exit 0
    fi
    if [ -f "$p" ]; then echo "2026-01-01 00:00:00 10 $(basename "$p")"; exit 0; fi
    [ -d "$p" ] || exit 1
    n=0
    for f in "$p"/*; do
        [ -e "$f" ] || continue
        n=$((n+1)); b=$(basename "$f")
        if [ -d "$f" ]; then echo "                           PRE $b/"; else echo "2026-01-01 00:00:00 10 $b"; fi
    done
    [ "$n" = 0 ] && exit 1
    exit 0 ;;
"s3 cp")
    rec=0; s=""; d=""
    for a in "$@"; do
        case "$a" in
            --recursive) rec=1 ;;
            --*) ;;
            *) if [ -z "$s" ]; then s="$a"; elif [ -z "$d" ]; then d="$a"; fi ;;
        esac
    done
    [ -n "$FAKE_FAIL_CP" ] && echo "$s $d" | grep -q "$FAKE_FAIL_CP" && { echo "cp failed" >&2; exit 1; }
    if [ "$d" = "-" ]; then ss=$(resolve "$s"); [ -f "$ss" ] || exit 1; cat "$ss"; exit 0; fi
    dd=$(resolve "$d")
    if [ "$s" = "-" ]; then mkdir -p "$(dirname "$dd")"; cat > "$dd"; exit 0; fi
    ss=$(resolve "$s")
    # Static and public backups copy whole prefixes with --recursive.
    if [ "$rec" = 1 ]; then
        [ -d "$ss" ] || exit 1
        mkdir -p "$dd"
        (cd "$ss" && find . -type f | sed 's|^\./||') | while read -r rel; do
            mkdir -p "$dd/$(dirname "$rel")"; cp "$ss/$rel" "$dd/$rel"
        done
        exit 0
    fi
    mkdir -p "$(dirname "$dd")"
    [ -f "$ss" ] || exit 1; cp "$ss" "$dd"; exit 0 ;;
"s3 sync")
    del=0; s=""; d=""
    for a in "$@"; do case "$a" in --delete) del=1 ;; --*) ;; *) if [ -z "$s" ]; then s="$a"; elif [ -z "$d" ]; then d="$a"; fi ;; esac; done
    [ -n "$FAKE_FAIL_SYNC" ] && echo "$d" | grep -q "$FAKE_FAIL_SYNC" && { echo "sync failed" >&2; exit 1; }
    ss=$(resolve "$s"); dd=$(resolve "$d"); mkdir -p "$dd"
    if [ -d "$ss" ]; then
        (cd "$ss" && find . -type f | sed 's|^\./||') | while read -r rel; do
            mkdir -p "$dd/$(dirname "$rel")"; cp "$ss/$rel" "$dd/$rel"
        done
    fi
    if [ "$del" = 1 ] && [ -d "$dd" ]; then
        (cd "$dd" && find . -type f | sed 's|^\./||') | while read -r rel; do
            [ -f "$ss/$rel" ] || rm -f "$dd/$rel"
        done
    fi
    exit 0 ;;
"s3 rm")
    rec=0; t=""
    for a in "$@"; do case "$a" in --recursive) rec=1 ;; s3://*) [ -z "$t" ] && t="$a" ;; esac; done
    p=$(resolve "$t")
    if [ "$rec" = 1 ]; then rm -rf "$p"; else [ -e "$p" ] || exit 1; rm -f "$p"; fi
    exit 0 ;;
"s3api put-object")
    key=""; body=""; cond=0
    while [ $# -gt 0 ]; do
        case "$1" in
            --key) key="$2"; shift 2 ;;
            --body) body="$2"; shift 2 ;;
            --if-none-match) cond=1; shift 2 ;;
            *) shift ;;
        esac
    done
    dest="$R/$key"
    if [ "$cond" = 1 ] && [ -e "$dest" ]; then
        echo "An error occurred (PreconditionFailed) when calling the PutObject operation" >&2
        exit 254
    fi
    mkdir -p "$(dirname "$dest")"; cp "$body" "$dest"; exit 0 ;;
"s3api head-object")
    key=""
    while [ $# -gt 0 ]; do case "$1" in --key) key="$2"; shift 2 ;; *) shift ;; esac; done
    [ -f "$R/$key" ] && { echo '{"ContentLength":10}'; exit 0; }
    echo "An error occurred (404)" >&2; exit 254 ;;
"s3api list-objects-v2")
    pre=""
    while [ $# -gt 0 ]; do case "$1" in --prefix) pre="$2"; shift 2 ;; *) shift ;; esac; done
    p="$R/${pre%/}"
    [ -d "$p" ] || { [ -f "$p" ] && { echo "$pre"; exit 0; }; exit 0; }
    (cd "$p" && find . -type f | sed "s|^\./|${pre%/}/|")
    exit 0 ;;
esac
exit 0
FAKEAWS

    # --- drush ------------------------------------------------------------
    cat > "$root/bin/drush" <<'FAKEDRUSH'
#!/bin/sh
DS="${DSTATE:-/tmp/ds}"
DB="${FAKE_DB:-/tmp/db.sql}"
get() { grep "^$1=" "$DS" 2>/dev/null | tail -1 | sed 's/^[^=]*=//'; }
put() { t="$DS.t"; grep -v "^$1=" "$DS" 2>/dev/null > "$t"; echo "$1=$2" >> "$t"; mv "$t" "$DS"; }
del() { t="$DS.t"; grep -v "^$1=" "$DS" 2>/dev/null > "$t"; mv "$t" "$DS"; }
case "$1" in
    sget) get "$2"; exit 0 ;;
    sset) put "$2" "$3"; exit 0 ;;
    sdel) del "$2"; exit 0 ;;
    cr) [ -n "$FAKE_CR_FAIL" ] && exit 1; exit 0 ;;
    updatedb) [ -n "$FAKE_UPDATEDB_FAIL" ] && exit 1; exit 0 ;;
    cim) [ -n "$FAKE_CIM_FAIL" ] && exit 1; exit 0 ;;
    php-eval) exit 0 ;;
    sql:dump)
        [ -n "$FAKE_DUMP_FAIL" ] && exit 1
        out=$(echo "$@" | sed -n 's/.*--result-file=\([^ ]*\).*/\1/p')
        if [ -n "$out" ]; then
            { printf -- '-- MySQL dump 10.13\n-- Host: x  Database: d\n'; cat "$DB" 2>/dev/null; printf -- '-- Dump completed on 2026-01-01\n'; } > "$out"
        fi
        exit 0 ;;
    sql-cli|sql:cli)
        if [ -n "$FAKE_IMPORT_FAIL" ]; then cat > /dev/null; exit 1; fi
        if [ -n "$FAKE_IMPORT_FAIL_ONCE" ] && [ -f "$FAKE_IMPORT_FAIL_ONCE" ]; then
            rm -f "$FAKE_IMPORT_FAIL_ONCE"; cat > /dev/null; exit 1
        fi
        cat > "$DB"; exit 0 ;;
    sqlq|sql:query) echo 1; exit 0 ;;
    status) echo "Drupal bootstrap : Successful"; exit 0 ;;
esac
exit 0
FAKEDRUSH

    # --- container tar ----------------------------------------------------
    # Stands in for BusyBox tar. It has no notion of AppleDouble, so a "._name"
    # member extracts as an ordinary file — which is exactly how macOS tar's
    # side-cars became junk objects in the target bucket. Python is used because no
    # host tar reproduces that faithfully: bsdtar interprets the members even with
    # --no-mac-metadata, and silently drops ._ named files on extract.
    #   FAKE_TAR_DROP=<basename>  omit that member when creating (lossy transfer)
    #   FAKE_TAR_INJECT=<n>       create n extra files when extracting (gained files)
    cat > "$root/container-bin/tar" <<'FAKETAR'
#!/usr/bin/env python3
import io, os, sys, tarfile

args = sys.argv[1:]
mode = chdir = None
paths = []
i = 0
while i < len(args):
    a = args[i]
    if a == '-C':
        i += 1
        chdir = args[i]
    elif a in ('czf', 'xzf', 'tzf', 'cf', 'xf', 'tf') or (
            a.startswith('-') and len(a) > 1 and not os.path.exists(a)):
        for ch in a.lstrip('-'):
            if ch in 'cxt':
                mode = ch
    elif a == '-':
        pass
    else:
        paths.append(a)
    i += 1

if mode is None:
    sys.exit("fake tar: no mode in {}".format(args))
if chdir:
    os.chdir(chdir)

drop = os.environ.get('FAKE_TAR_DROP', '')
inject = int(os.environ.get('FAKE_TAR_INJECT', '0') or 0)

if mode == 'c':
    with tarfile.open(fileobj=sys.stdout.buffer, mode='w|gz') as t:
        for root in (paths or ['.']):
            for dirpath, dirnames, filenames in os.walk(root):
                dirnames.sort()
                t.add(dirpath, arcname=dirpath, recursive=False)
                for name in sorted(filenames):
                    if drop and name == drop:
                        continue
                    full = os.path.join(dirpath, name)
                    with open(full, 'rb') as fh:
                        data = fh.read()
                    ti = tarfile.TarInfo(full)
                    ti.size = len(data)
                    ti.mode = 0o644
                    t.addfile(ti, io.BytesIO(data))
else:
    with tarfile.open(fileobj=sys.stdin.buffer, mode='r|gz') as t:
        for m in t:
            if mode == 't':
                print(m.name)
            else:
                t.extract(m, '.', filter='fully_trusted')
    for n in range(inject):
        with open('injected-{}.bin'.format(n), 'w') as fh:
            fh.write('injected\n')
FAKETAR

    chmod +x "$root/bin/cf" "$root/bin/aws" "$root/bin/drush" "$root/container-bin/tar"
}
