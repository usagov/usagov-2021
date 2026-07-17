#!/bin/sh

# Backup System Test Script

# Load common utilities
SCRIPT_DIR=$(dirname "$0")
. "$SCRIPT_DIR/../common.sh"

# Test tracking
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_TOTAL=0

# Function to show usage
show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --help, -h              Show this help message"
    echo ""
    echo "Run the comprehensive backup system test suite."
    echo ""
    echo "Examples:"
    echo "  $0                      # Run full test suite"
    echo "  $0 --help               # Show this help message"
}

# Function to run a test
run_test() {
    local test_name="$1"
    local test_command="$2"

    TESTS_TOTAL=$((TESTS_TOTAL + 1))
    echo ""
    print_status $BLUE "🧪 TEST $TESTS_TOTAL: $test_name"
    echo "----------------------------------------"

    if eval "$test_command"; then
        print_status $GREEN "✅ PASSED: $test_name"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        return 0
    else
        print_status $RED "❌ FAILED: $test_name"
        TESTS_FAILED=$((TESTS_FAILED + 1))
        return 0
    fi
}

# Function to simulate S3 environment variables
setup_test_env() {
    # Check if we're in a Cloud Foundry environment
    if [ -n "$VCAP_SERVICES" ]; then
        echo "✅ Cloud Foundry environment detected"
        setup_s3_vars

        if [ "${APP_SPACE}" = "local" ]; then
            S3_EXTRA_PARAMS="--endpoint-url https://$AWS_ENDPOINT --no-verify-ssl"
        else
            S3_EXTRA_PARAMS=""
        fi
        export S3_EXTRA_PARAMS
    else
        echo "✅ Non-CF environment - checking for AWS credentials"
        if [ -z "$BUCKET_NAME" ]; then
            echo "⚠️️ BUCKET_NAME not set - some tests may fail"
        fi
    fi
}

# Function to test configuration loading
test_config_loading() {
    local config_file="$BACKUP_DIR/backup-system.conf"

    check_file "$config_file" "backup configuration file" || return 1

    # init_backup_system has already loaded the config and made constants readonly.
    # Check required variables without sourcing the file a second time.
    local required_vars="BACKUP_RETENTION_DAYS ENABLE_STATIC_AUTO_BACKUPS ENABLE_PUBLIC_AUTO_BACKUPS ENABLE_STATIC_AUTO_CLEANUP ENABLE_PUBLIC_AUTO_CLEANUP BACKUP_PREFIX ENABLE_SMART_PUBLIC_BACKUP"
    for var in $required_vars; do
        eval "var_value=\$$var"
        if [ -n "$var_value" ]; then
            echo "✅ Config variable $var = $var_value"
        else
            echo "❌ Config variable $var is not set"
            return 1
        fi
    done

    return 0
}

# Function to test script files existence and permissions
test_script_files() {
    # Check tome-sync.sh
    check_file "$PROJECT_ROOT/scripts/tome-sync.sh" "script file" || return 1
    if [ -x "$PROJECT_ROOT/scripts/tome-sync.sh" ]; then
        echo "✅ Script is executable: $PROJECT_ROOT/scripts/tome-sync.sh"
    else
        echo "❌ Script is not executable: $PROJECT_ROOT/scripts/tome-sync.sh"
        return 1
    fi

    # Check manager.sh
    check_file "$BACKUP_DIR/manager.sh" "script file" || return 1
    if [ -x "$BACKUP_DIR/manager.sh" ]; then
        echo "✅ Script is executable: $BACKUP_DIR/manager.sh"
    else
        echo "❌ Script is not executable: $BACKUP_DIR/manager.sh"
        return 1
    fi

    return 0
}

# Function to test required commands
test_dependencies() {
    local missing_commands=""

    # Check each required command
    for cmd in aws jq date grep awk sort md5sum bc; do
        if ! check_command "$cmd"; then
            # Try alternative commands
            case "$cmd" in
                "md5sum")
                    if check_command "md5"; then
                        echo "✅ Using 'md5' instead of 'md5sum'"
                    else
                        missing_commands="$missing_commands $cmd"
                    fi
                    ;;
                *)
                    missing_commands="$missing_commands $cmd"
                    ;;
            esac
        fi
    done

    if [ -z "$missing_commands" ]; then
        return 0
    else
        echo "❌ Missing required commands:$missing_commands"
        return 1
    fi
}

# Function to test AWS connectivity
test_aws_connectivity() {
    if [ -z "$BUCKET_NAME" ]; then
        echo "⚠️️ BUCKET_NAME not set - skipping AWS connectivity test"
        return 0
    fi

    echo "🔗 Testing AWS S3 connectivity to bucket: $BUCKET_NAME"

    # Test basic S3 access
    if aws s3 ls "s3://$BUCKET_NAME/" $S3_EXTRA_PARAMS >/dev/null 2>&1; then
        echo "✅ Successfully connected to S3 bucket"
    else
        echo "❌ Failed to connect to S3 bucket"
        return 1
    fi

    # Test specific backup directories
    for dir in web-backup public_backup; do
        aws s3 ls "s3://$BUCKET_NAME/$dir/" $S3_EXTRA_PARAMS >/dev/null 2>&1
        if [ $? -eq 0 ]; then
            echo "✅ Backup directory exists: $dir"
        else
            echo "✅ Backup directory will be created when needed: $dir"
        fi
    done

    return 0
}

# Function to test backup integration in tome-sync.sh
test_backup_integration() {
    local tome_sync_script="$PROJECT_ROOT/scripts/tome-sync.sh"

    # Check for backup-related code in tome-sync.sh
    # tome-sync.sh now calls manager.sh instead of directly implementing backup logic
    echo "🔍 Checking tome-sync.sh integration with backup system..."

    # Check that tome-sync calls the backup manager
    if grep -q "snapshot/manager.sh" "$tome_sync_script"; then
        echo "✅ Found backup manager reference: snapshot/manager.sh"
    else
        echo "❌ Missing backup manager reference: snapshot/manager.sh"
        return 1
    fi

    # Check for BACKUP_MANAGER variable
    if grep -q "BACKUP_MANAGER=" "$tome_sync_script"; then
        echo "✅ Found BACKUP_MANAGER variable"
    else
        echo "❌ Missing BACKUP_MANAGER variable"
        return 1
    fi

    # Check that it calls backup command
    # tome-sync.sh backs up static and public files; cron handles scheduled backups.
    if grep -q "backup static,public" "$tome_sync_script"; then
        echo "✅ Found backup static,public command call"
    else
        echo "❌ Missing backup static,public command call"
        return 1
    fi

    # Check that it uses --throttle to avoid backup churn during active Tome runs
    if grep -q "\-\-throttle" "$tome_sync_script"; then
        echo "✅ Found --throttle flag for backup throttling"
    else
        echo "❌ Missing --throttle flag"
        return 1
    fi

    # Check that it calls clean command
    if grep -q "clean static,public" "$tome_sync_script"; then
        echo "✅ Found clean command call"
    else
        echo "❌ Missing clean command call"
        return 1
    fi

    # Check for --non-interactive flag usage
    if grep -q "\-\-non-interactive" "$tome_sync_script"; then
        echo "✅ Found --non-interactive flag for automation"
    else
        echo "❌ Missing --non-interactive flag"
        return 1
    fi

    echo "✅ tome-sync.sh properly integrates with backup system via manager.sh"
    return 0
}

# Function to test backup manager functionality
test_backup_manager() {
    local manager_script="$BACKUP_DIR/manager.sh"

    # Test help functionality (backup manager shows usage and exits with code 1, which is expected)
    "$manager_script" >/dev/null 2>&1
    local exit_code=$?
    if [ $exit_code -eq 1 ] || [ $exit_code -eq 0 ]; then
        echo "✅ Backup manager script runs and shows usage correctly"
    elif [ $exit_code -eq 127 ]; then
        # Try with sh if bash is not available
        echo "⚠️ Trying backup manager with sh instead of bash..."
        sh "$manager_script" >/dev/null 2>&1
        local sh_exit_code=$?
        if [ $sh_exit_code -eq 1 ] || [ $sh_exit_code -eq 0 ]; then
            echo "✅ Backup manager script runs with sh"
        else
            echo "❌ Backup manager script fails even with sh (exit code: $sh_exit_code)"
            return 1
        fi
    else
        echo "❌ Backup manager script has execution errors (exit code: $exit_code)"
        return 1
    fi

    # Check for required functions in the script
    for func in "list_backups" "list_old_backups" "clean_old_backups" "backup_info" "restore_backup"; do
        if grep -q "$func" "$manager_script"; then
            echo "✅ Found backup manager function: $func"
        else
            echo "❌ Missing backup manager function: $func"
            return 1
        fi
    done

    return 0
}

# Function to comprehensively test manager commands
test_manager_commands() {
    local manager_script="$BACKUP_DIR/manager.sh"

    echo "🎯 Testing manager command interface..."

    # Test list command with different arguments
    echo "📋 Testing 'list' command variations..."

    # Test basic list (should work even without S3 credentials)
    "$manager_script" list >/dev/null 2>&1
    local list_exit=$?
    if [ $list_exit -eq 0 ] || [ $list_exit -eq 1 ]; then
        echo "✅ 'list' command executes (exit code: $list_exit)"
    else
        echo "❌ 'list' command failed with unexpected exit code: $list_exit"
        return 1
    fi

    # Test list with specific types
    "$manager_script" list static >/dev/null 2>&1
    if [ $? -eq 0 ] || [ $? -eq 1 ]; then
        echo "✅ 'list static' command executes"
    else
        echo "❌ 'list static' command failed"
        return 1
    fi

    "$manager_script" list db >/dev/null 2>&1
    if [ $? -eq 0 ] || [ $? -eq 1 ]; then
        echo "✅ 'list db' command executes"
    else
        echo "❌ 'list db' command failed"
        return 1
    fi

    "$manager_script" list static,public >/dev/null 2>&1
    if [ $? -eq 0 ] || [ $? -eq 1 ]; then
        echo "✅ 'list static,public' command executes"
    else
        echo "❌ 'list static,public' command failed"
        return 1
    fi

    # Test list with days parameter
    "$manager_script" list all 7 >/dev/null 2>&1
    if [ $? -eq 0 ] || [ $? -eq 1 ]; then
        echo "✅ 'list all 7' command executes"
    else
        echo "❌ 'list all 7' command failed"
        return 1
    fi

    # Test info command
    echo "ℹ️ Testing 'info' command variations..."

    "$manager_script" info >/dev/null 2>&1
    if [ $? -eq 0 ] || [ $? -eq 1 ]; then
        echo "✅ 'info' command executes"
    else
        echo "❌ 'info' command failed"
        return 1
    fi

    "$manager_script" info static >/dev/null 2>&1
    if [ $? -eq 0 ] || [ $? -eq 1 ]; then
        echo "✅ 'info static' command executes"
    else
        echo "❌ 'info static' command failed"
        return 1
    fi

    # Test restore command (should show usage/error without tag)
    echo "🔄 Testing 'restore' command..."

    "$manager_script" restore >/dev/null 2>&1
    local restore_exit=$?
    if [ $restore_exit -eq 1 ]; then
        echo "✅ 'restore' command correctly requires backup tag (exit code: 1)"
    else
        echo "❌ 'restore' command unexpected behavior (exit code: $restore_exit)"
        return 1
    fi

    # Test clean command (dry run style test)
    echo "🧹 Testing 'clean' command..."

    # Clean command validation only (no actual deletion testing)
    echo "n" | "$manager_script" clean all 30 >/dev/null 2>&1
    local clean_exit=$?
    if [ $clean_exit -eq 0 ] || [ $clean_exit -eq 1 ]; then
        echo "✅ 'clean' command interface works (cancelled properly)"
    else
        echo "❌ 'clean' command failed (exit code: $clean_exit)"
        return 1
    fi

    # Test backup command structure (without actually creating backups in test)
    echo "📦 Testing 'backup' command interface..."

    # These commands might fail due to missing S3 credentials, but they should
    # at least parse arguments correctly and show meaningful errors
    "$manager_script" backup static TEST test-suffix >/dev/null 2>&1
    local backup_exit=$?
    if [ $backup_exit -eq 0 ] || [ $backup_exit -eq 1 ]; then
        echo "✅ 'backup static TEST test-suffix' command parses correctly"
    else
        echo "⚠️ 'backup static TEST test-suffix' had issues (exit code: $backup_exit) - may be due to missing S3 access"
    fi

    # Test argument parsing for backup types
    local test_types="all static public db static,public static,db public,db static,public,db"
    for backup_type in $test_types; do
        "$manager_script" backup "$backup_type" TEST >/dev/null 2>&1
        local type_exit=$?
        if [ $type_exit -eq 0 ] || [ $type_exit -eq 1 ]; then
            echo "✅ Backup type '$backup_type' parses correctly"
        else
            echo "⚠️ Backup type '$backup_type' may have parsing issues (exit code: $type_exit)"
        fi
    done

    echo "🎯 Manager command interface testing complete"
    return 0
}

# Function to test date calculations (important for cleanup)
test_date_calculations() {
    echo "📅 Testing date calculation compatibility..."

    # Show what date command we have
    echo "Date command info: $(date --version 2>/dev/null || date 2>/dev/null | head -1 || echo 'unknown')"

    # Test both Linux and macOS date formats
    local test_days=7
    local cutoff_date_linux=$(date -u -d "${test_days} days ago" '+%Y_%m_%d' 2>/dev/null)
    local cutoff_date_macos=$(date -u -v-${test_days}d '+%Y_%m_%d' 2>/dev/null)

    if [ -n "$cutoff_date_linux" ]; then
        echo "✅ Linux date format works: $cutoff_date_linux"
        return 0
    elif [ -n "$cutoff_date_macos" ]; then
        echo "✅ macOS date format works: $cutoff_date_macos"
        return 0
    else
        echo "⚠️ Advanced date calculations not available"
        echo "Backup cleanup will be disabled in this environment"
        echo "Basic date command works: $(date '+%Y_%m_%d' 2>/dev/null || echo 'unavailable')"

        # Test if at least basic date formatting works
        if date '+%Y_%m_%d' >/dev/null 2>&1; then
            echo "✅ Date calculations test passed with limited functionality"
            return 0
        else
            echo "❌ Even basic date formatting fails"
            return 1
        fi
    fi
}

# Function to test backup naming pattern
test_backup_naming() {
    local test_space="test"
    local test_container_tag="git-abc123"
    local test_timestamp="2024_03_15_14_30_00"
    local expected_pattern="${BACKUP_PREFIX}-${test_space}-${test_container_tag}-${test_timestamp}"

    echo "🏷️ Testing backup naming pattern: $expected_pattern"

    # Test pattern matching (used in cleanup)
    if echo "$expected_pattern" | grep -q "${BACKUP_PREFIX}-[^/]*-[^/]*-[0-9_]*"; then
        echo "✅ Backup naming pattern matches cleanup regex"
    else
        echo "❌ Backup naming pattern doesn't match cleanup regex"
        return 1
    fi

    # Test date extraction (extract the date part from the pattern)
    local extracted_date=$(echo "$expected_pattern" | sed 's/.*-\([0-9_]*\).*/\1/' | cut -c 1-10)
    if [ "$extracted_date" = "2024_03_15" ]; then
        echo "✅ Date extraction works correctly: $extracted_date"
    else
        echo "❌ Date extraction failed: $extracted_date"
        return 1
    fi

    return 0
}

# Function to test date range filtering utilities
test_date_range_filtering() {
    echo "📅 Testing date range filtering functionality..."

    local common_script="$PROJECT_ROOT/scripts/common.sh"

    # Test 1: Check if date_to_epoch function exists
    if ! grep -q "^date_to_epoch()" "$common_script"; then
        echo "❌ date_to_epoch function not found in common.sh"
        return 1
    fi
    echo "✅ date_to_epoch function exists"

    # Test 2: Check if is_date_in_range function exists
    if ! grep -q "^is_date_in_range()" "$common_script"; then
        echo "❌ is_date_in_range function not found in common.sh"
        return 1
    fi
    echo "✅ is_date_in_range function exists"

    # Source common.sh to test the functions
    . "$common_script"

    # Test 3: Test date_to_epoch with valid dates
    local epoch_result=$(date_to_epoch "2025-01-01")
    if [ -n "$epoch_result" ] && [ "$epoch_result" -gt 0 ]; then
        echo "✅ date_to_epoch converts valid date: 2025-01-01 -> $epoch_result"
    else
        echo "❌ date_to_epoch failed for valid date: 2025-01-01"
        return 1
    fi

    # Test 4: Test date_to_epoch with invalid date
    local invalid_result=$(date_to_epoch "invalid-date")
    if [ -z "$invalid_result" ]; then
        echo "✅ date_to_epoch correctly rejects invalid date"
    else
        echo "❌ date_to_epoch should return empty for invalid date"
        return 1
    fi

    # Test 5: Test is_date_in_range - date within range
    if is_date_in_range "2025-06-15" "2025-01-01" "2025-12-31"; then
        echo "✅ is_date_in_range: 2025-06-15 in [2025-01-01, 2025-12-31]"
    else
        echo "❌ is_date_in_range failed: 2025-06-15 should be in range"
        return 1
    fi

    # Test 6: Test is_date_in_range - date before range
    if ! is_date_in_range "2024-12-01" "2025-01-01" "2025-12-31"; then
        echo "✅ is_date_in_range: 2024-12-01 not in [2025-01-01, 2025-12-31]"
    else
        echo "❌ is_date_in_range failed: 2024-12-01 should be out of range"
        return 1
    fi

    # Test 7: Test is_date_in_range - date after range
    if ! is_date_in_range "2026-01-01" "2025-01-01" "2025-12-31"; then
        echo "✅ is_date_in_range: 2026-01-01 not in [2025-01-01, 2025-12-31]"
    else
        echo "❌ is_date_in_range failed: 2026-01-01 should be out of range"
        return 1
    fi

    # Test 8: Test is_date_in_range - only start date (no end)
    if is_date_in_range "2025-06-15" "2025-01-01" ""; then
        echo "✅ is_date_in_range: 2025-06-15 >= 2025-01-01 (no end date)"
    else
        echo "❌ is_date_in_range failed with only start date"
        return 1
    fi

    # Test 9: Test is_date_in_range - only end date (no start)
    if is_date_in_range "2025-06-15" "" "2025-12-31"; then
        echo "✅ is_date_in_range: 2025-06-15 <= 2025-12-31 (no start date)"
    else
        echo "❌ is_date_in_range failed with only end date"
        return 1
    fi

    # Test 10: Test is_date_in_range - no constraints
    if is_date_in_range "2025-06-15" "" ""; then
        echo "✅ is_date_in_range: 2025-06-15 matches with no constraints"
    else
        echo "❌ is_date_in_range failed with no constraints"
        return 1
    fi

    # Test 11: Check manager.sh list_old_backups supports date ranges
    local manager_script="$BACKUP_DIR/manager.sh"
    if ! grep -q "list_old_backups()" "$manager_script"; then
        echo "❌ list_old_backups function not found in manager.sh"
        return 1
    fi

    # Check that list_old_backups uses date range logic
    local list_function=$(sed -n '/^list_old_backups()/,/^clean_old_backups()/p' "$manager_script")
    if echo "$list_function" | grep -q "is_date_in_range"; then
        echo "✅ list_old_backups integrates date range filtering"
    else
        echo "❌ list_old_backups missing date range filtering"
        return 1
    fi

    if echo "$list_function" | grep -q "AUTO_DB_BACKUP_PATH" && echo "$list_function" | grep -q "\\.sql\\.gz"; then
        echo "✅ list_old_backups includes database backup filtering"
    else
        echo "❌ list_old_backups missing database backup filtering"
        return 1
    fi

    # Test 12: Check manager.sh clean_old_backups supports new filter system
    if ! grep -q "clean_old_backups()" "$manager_script"; then
        echo "❌ clean_old_backups function not found in manager.sh"
        return 1
    fi

    local clean_function=$(sed -n '/^clean_old_backups()/,/^}/p' "$manager_script")
    if echo "$clean_function" | grep -q "matches_clean_filter"; then
        echo "✅ clean_old_backups integrates new filter system (matches_clean_filter)"
    else
        echo "❌ clean_old_backups missing matches_clean_filter"
        return 1
    fi

    # Test 13: Check cleanup_old_db_backups supports new filter system
    if ! grep -q "cleanup_old_db_backups()" "$manager_script"; then
        echo "❌ cleanup_old_db_backups function not found in manager.sh"
        return 1
    fi

    local db_clean_function=$(sed -n '/^cleanup_old_db_backups()/,/^}/p' "$manager_script")
    if echo "$db_clean_function" | grep -q "matches_clean_filter"; then
        echo "✅ cleanup_old_db_backups integrates new filter system (matches_clean_filter)"
    else
        echo "❌ cleanup_old_db_backups missing matches_clean_filter"
        return 1
    fi

    # Test 14: Check usage documentation mentions date format
    if grep -q "YYYY-MM-DD" "$manager_script"; then
        echo "✅ Usage documentation includes date format (YYYY-MM-DD)"
    else
        echo "❌ Usage documentation missing date format"
        return 1
    fi

    # Test 15: Check for explicit flag examples in usage
    if grep -q "\-\-in-range\|\-\-except-range" "$manager_script"; then
        echo "✅ Usage includes explicit flag examples"
    else
        echo "❌ Usage missing explicit flag examples"
        return 1
    fi

    echo "✅ Date range filtering test passed"
    return 0
}

test_explicit_clean_flags() {
    echo "🎯 Testing explicit clean flags functionality..."

    local common_script="$PROJECT_ROOT/scripts/common.sh"
    local manager_script="$BACKUP_DIR/manager.sh"

    # Test 1: Check if matches_clean_filter function exists
    if ! grep -q "^matches_clean_filter()" "$common_script"; then
        echo "❌ matches_clean_filter function not found in common.sh"
        return 1
    fi
    echo "✅ matches_clean_filter function exists"

    # Source common.sh to test the function
    . "$common_script"

    # Test 2: Test matches_clean_filter with "days" filter type (retention)
    # Backup from 5 days ago, keep last 3 days -> should DELETE (return 0)
    # Use portable date calculation (works on both GNU and BusyBox)
    local five_days_ago_epoch=$(( $(date -u +%s) - (5 * 86400) ))
    local five_days_ago=$(date -u -d "@$five_days_ago_epoch" "+%Y-%m-%d" 2>/dev/null || date -u -r "$five_days_ago_epoch" "+%Y-%m-%d" 2>/dev/null)
    if matches_clean_filter "$five_days_ago" "days" "3"; then
        echo "✅ matches_clean_filter: 5-day-old backup matches days=3 filter (delete)"
    else
        echo "❌ matches_clean_filter failed: 5-day-old backup should match days=3"
        return 1
    fi

    # Test 3: Test matches_clean_filter - backup within retention period
    # Backup from 1 day ago, keep last 3 days -> should KEEP (return 1)
    local one_day_ago_epoch=$(( $(date -u +%s) - 86400 ))
    local one_day_ago=$(date -u -d "@$one_day_ago_epoch" "+%Y-%m-%d" 2>/dev/null || date -u -r "$one_day_ago_epoch" "+%Y-%m-%d" 2>/dev/null)
    if ! matches_clean_filter "$one_day_ago" "days" "3"; then
        echo "✅ matches_clean_filter: 1-day-old backup doesn't match days=3 filter (keep)"
    else
        echo "❌ matches_clean_filter failed: 1-day-old backup should not match days=3"
        return 1
    fi

    # Test 4: Test matches_clean_filter with "in-range" filter (delete within)
    if matches_clean_filter "2025-06-15" "in-range" "2025-06-01:2025-06-30"; then
        echo "✅ matches_clean_filter: 2025-06-15 in range 2025-06-01:2025-06-30 (delete)"
    else
        echo "❌ matches_clean_filter failed: date should be in range"
        return 1
    fi

    # Test 5: Test matches_clean_filter - outside in-range (keep)
    if ! matches_clean_filter "2025-07-15" "in-range" "2025-06-01:2025-06-30"; then
        echo "✅ matches_clean_filter: 2025-07-15 not in range (keep)"
    else
        echo "❌ matches_clean_filter failed: date should be outside range"
        return 1
    fi

    # Test 6: Test matches_clean_filter with "except-range" filter (keep within)
    if ! matches_clean_filter "2025-06-15" "except-range" "2025-06-01:2025-06-30"; then
        echo "✅ matches_clean_filter: 2025-06-15 in except-range (keep)"
    else
        echo "❌ matches_clean_filter failed: date in except-range should be kept"
        return 1
    fi

    # Test 7: Test matches_clean_filter - outside except-range (delete)
    if matches_clean_filter "2025-07-15" "except-range" "2025-06-01:2025-06-30"; then
        echo "✅ matches_clean_filter: 2025-07-15 outside except-range (delete)"
    else
        echo "❌ matches_clean_filter failed: date outside except-range should be deleted"
        return 1
    fi

    # Test 8: Test matches_clean_filter with "older-date" filter
    if matches_clean_filter "2024-12-01" "older-date" "2025-01-01"; then
        echo "✅ matches_clean_filter: 2024-12-01 older than 2025-01-01 (delete)"
    else
        echo "❌ matches_clean_filter failed: older date should match"
        return 1
    fi

    # Test 9: Test matches_clean_filter - newer than older-date boundary (keep)
    if ! matches_clean_filter "2025-02-01" "older-date" "2025-01-01"; then
        echo "✅ matches_clean_filter: 2025-02-01 newer than 2025-01-01 (keep)"
    else
        echo "❌ matches_clean_filter failed: newer date should not match older-date"
        return 1
    fi

    # Test 10: Test matches_clean_filter with "newer-date" filter
    if matches_clean_filter "2025-02-01" "newer-date" "2025-01-01"; then
        echo "✅ matches_clean_filter: 2025-02-01 newer than 2025-01-01 (delete)"
    else
        echo "❌ matches_clean_filter failed: newer date should match"
        return 1
    fi

    # Test 11: Test matches_clean_filter - older than newer-date boundary (keep)
    if ! matches_clean_filter "2024-12-01" "newer-date" "2025-01-01"; then
        echo "✅ matches_clean_filter: 2024-12-01 older than 2025-01-01 (keep)"
    else
        echo "❌ matches_clean_filter failed: older date should not match newer-date"
        return 1
    fi

    # Test 12: Test matches_clean_filter with "all" filter (delete everything)
    if matches_clean_filter "2025-06-15" "all" ""; then
        echo "✅ matches_clean_filter: any date matches 'all' filter (delete)"
    else
        echo "❌ matches_clean_filter failed: 'all' should match any date"
        return 1
    fi

    # Test 13: Check manager.sh has --in-range flag in usage
    if grep -q "\-\-in-range" "$manager_script"; then
        echo "✅ manager.sh documents --in-range flag"
    else
        echo "❌ manager.sh missing --in-range flag documentation"
        return 1
    fi

    # Test 14: Check manager.sh has --except-range flag in usage
    if grep -q "\-\-except-range" "$manager_script"; then
        echo "✅ manager.sh documents --except-range flag"
    else
        echo "❌ manager.sh missing --except-range flag documentation"
        return 1
    fi

    # Test 15: Check manager.sh has --older-than-date flag in usage
    if grep -q "\-\-older-than-date" "$manager_script"; then
        echo "✅ manager.sh documents --older-than-date flag"
    else
        echo "❌ manager.sh missing --older-than-date flag documentation"
        return 1
    fi

    # Test 16: Check manager.sh has --newer-than-date flag in usage
    if grep -q "\-\-newer-than-date" "$manager_script"; then
        echo "✅ manager.sh documents --newer-than-date flag"
    else
        echo "❌ manager.sh missing --newer-than-date flag documentation"
        return 1
    fi

    # Test 17: Check manager.sh has --older-than flag in usage
    if grep -q "\-\-older-than" "$manager_script"; then
        echo "✅ manager.sh documents --older-than flag"
    else
        echo "❌ manager.sh missing --older-than flag documentation"
        return 1
    fi

    # Test 18: Check run_clean_command parses --in-range flag
    local run_clean=$(sed -n '/^run_clean_command()/,/^}/p' "$manager_script")
    if echo "$run_clean" | grep -q '\-\-in-range'; then
        echo "✅ run_clean_command parses --in-range flag"
    else
        echo "❌ run_clean_command missing --in-range parsing"
        return 1
    fi

    # Test 19: Check run_clean_command validates mutual exclusivity
    if echo "$run_clean" | grep -q "filter_count"; then
        echo "✅ run_clean_command validates mutual exclusivity (filter_count)"
    else
        echo "❌ run_clean_command missing mutual exclusivity validation"
        return 1
    fi

    # Test 20: Check run_clean_command validates date format
    if echo "$run_clean" | grep -q "Invalid date format"; then
        echo "✅ run_clean_command validates date format"
    else
        echo "❌ run_clean_command missing date format validation"
        return 1
    fi

    # Test 21: Check run_clean_command validates date range logic
    if echo "$run_clean" | grep -q "start_date.*end_date"; then
        echo "✅ run_clean_command validates date range logic"
    else
        echo "❌ run_clean_command missing date range logic validation"
        return 1
    fi

    # Test 22: Check clean_old_backups uses filter_type parameter
    local clean_func=$(sed -n '/^clean_old_backups()/,/^}/p' "$manager_script")
    if echo "$clean_func" | grep -q "filter_type"; then
        echo "✅ clean_old_backups uses filter_type parameter"
    else
        echo "❌ clean_old_backups missing filter_type parameter"
        return 1
    fi

    # Test 23: Check clean_old_backups uses matches_clean_filter
    if echo "$clean_func" | grep -q "matches_clean_filter"; then
        echo "✅ clean_old_backups uses matches_clean_filter helper"
    else
        echo "❌ clean_old_backups missing matches_clean_filter call"
        return 1
    fi

    # Test 24: Check cleanup_old_db_backups uses filter_type parameter
    local db_clean_func=$(sed -n '/^cleanup_old_db_backups()/,/^}/p' "$manager_script")
    if echo "$db_clean_func" | grep -q "filter_type"; then
        echo "✅ cleanup_old_db_backups uses filter_type parameter"
    else
        echo "❌ cleanup_old_db_backups missing filter_type parameter"
        return 1
    fi

    # Test 25: Check cleanup_old_db_backups uses matches_clean_filter
    if echo "$db_clean_func" | grep -q "matches_clean_filter"; then
        echo "✅ cleanup_old_db_backups uses matches_clean_filter helper"
    else
        echo "❌ cleanup_old_db_backups missing matches_clean_filter call"
        return 1
    fi

    # Test 26: Check README.md documents --in-range examples
    local readme="$BACKUP_DIR/README.md"
    if grep -q "\-\-in-range" "$readme"; then
        echo "✅ README.md documents --in-range examples"
    else
        echo "❌ README.md missing --in-range examples"
        return 1
    fi

    # Test 27: Check README.md documents --except-range examples
    if grep -q "\-\-except-range" "$readme"; then
        echo "✅ README.md documents --except-range examples"
    else
        echo "❌ README.md missing --except-range examples"
        return 1
    fi

    # Test 28: Check README.md documents date filter reference table
    if grep -q "Date Filter Reference" "$readme" || grep -q "Filter.*Description.*Example" "$readme"; then
        echo "✅ README.md includes date filter reference table"
    else
        echo "❌ README.md missing date filter reference table"
        return 1
    fi

    # Test 29: Check usage includes "Clean Date Filters" section
    if grep -q "Clean Date Filters" "$manager_script"; then
        echo "✅ Usage includes 'Clean Date Filters' section"
    else
        echo "❌ Usage missing 'Clean Date Filters' section"
        return 1
    fi

    # Test 30: Check usage shows explicit flag categories
    if grep -qi "Retention.*days-based\|retention-based\|delete specific periods" "$manager_script"; then
        echo "✅ Usage organizes flags by category"
    else
        echo "❌ Usage missing flag categorization"
        return 1
    fi

    echo "✅ Explicit clean flags test passed"
    return 0
}

# Function to simulate a backup scenario (dry run)
test_backup_simulation() {
    if [ -z "$BUCKET_NAME" ]; then
        echo "⚠️ BUCKET_NAME not set - skipping backup simulation"
        return 0
    fi

    echo "🔄 Simulating backup process (dry run)..."

    # Test if we can list current web and public directories
    aws s3 ls "s3://$BUCKET_NAME/web/" $S3_EXTRA_PARAMS >/dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "✅ Can access current static site directory"
    else
        echo "⚠️ Cannot access static site directory (may not exist yet)"
    fi

    aws s3 ls "s3://$BUCKET_NAME/cms/public/" $S3_EXTRA_PARAMS >/dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "✅ Can access current public files directory"
    else
        echo "⚠️ Cannot access public files directory (may not exist yet)"
    fi

    # Test if we can create a test file and copy it (actual backup simulation)
    local test_file="/tmp/backup_test_$(date +%s).txt"
    echo "backup test" > "$test_file"

    local test_backup_path="s3://$BUCKET_NAME/backup-test/test-backup/"
    aws s3 cp "$test_file" "$test_backup_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "✅ Can perform S3 copy operations (backup simulation successful)"
        # Clean up test file
        aws s3 rm "$test_backup_path$(basename "$test_file")" $S3_EXTRA_PARAMS >/dev/null 2>&1
        aws s3 rm "s3://$BUCKET_NAME/backup-test/" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
    else
        echo "❌ Cannot perform S3 copy operations"
        return 1
    fi

    rm -f "$test_file"
    return 0
}

# Function to test log directory accessibility
test_log_directory() {
    local log_dir="/tmp/tome-log"

    if [ ! -d "$log_dir" ]; then
        echo "📁 Creating log directory: $log_dir"
        mkdir -p "$log_dir" 2>/dev/null
    fi

    if [ -d "$log_dir" ] && [ -w "$log_dir" ]; then
        echo "✅ Log directory is accessible and writable: $log_dir"
        return 0
    else
        echo "❌ Log directory is not accessible or writable: $log_dir"
        return 1
    fi
}

# Function to test database backup system
test_database_backup_system() {
    echo "💾 Testing database backup system..."

    # Check if automatic backup manager exists
    if [ ! -f "$BACKUP_DIR/manager.sh" ]; then
        echo "❌ Automatic backup manager not found"
        return 1
    fi

    # Check if cron setup script exists
    if [ ! -f "$BACKUP_DIR/setup-cron.sh" ]; then
        echo "❌ Automatic backup cron setup script not found"
        return 1
    fi

    # Test database backup configuration
    echo "⚙️ Testing database backup configuration..."
    if [ "$ENABLE_DB_BACKUPS" = "true" ]; then
        echo "✅ Database backups are enabled"
    else
        echo "✅ Database backups are disabled (as configured)"
    fi

    # Test backup time configuration
    if [ -n "$DB_BACKUP_TIME" ]; then
        echo "✅ Database backup time is configured: $DB_BACKUP_TIME"
    else
        echo "❌ Database backup time not configured"
        return 1
    fi

    # Test retention configuration
    if [ -n "$DB_BACKUP_RETENTION_DAYS" ] && [ "$DB_BACKUP_RETENTION_DAYS" -gt 0 ]; then
        echo "✅ Database backup retention configured: $DB_BACKUP_RETENTION_DAYS days"
    else
        echo "❌ Database backup retention not properly configured"
        return 1
    fi

    # Test backup prefix configuration
    if [ -n "$DB_BACKUP_PREFIX" ]; then
        echo "✅ Database backup prefix configured: $DB_BACKUP_PREFIX"
    else
        echo "❌ Database backup prefix not configured"
        return 1
    fi

    # Test backup manager functions
    local manager_script="$BACKUP_DIR/manager.sh"
    for func in "list_db_backups" "cleanup_old_db_backups" "create_db_backup" "backup_info" "db_backup_info"; do
        if grep -q "$func" "$manager_script"; then
            echo "✅ Found database backup manager function: $func"
        else
            echo "❌ Missing database backup manager function: $func"
            return 1
        fi
    done

    # Check if automatic backup manager has required components
    local db_script="$BACKUP_DIR/manager.sh"
    for component in "ENABLE_DB_BACKUPS" "DB_BACKUP_PREFIX"; do
        if grep -q "$component" "$db_script"; then
            echo "✅ Automatic backup manager includes: $component"
        else
            echo "❌ Automatic backup manager missing: $component"
            return 1
        fi
    done

    # Check if manager loads common.sh (which loads backup-system.conf)
    if grep -q "common.sh" "$db_script"; then
        echo "✅ Automatic backup manager loads common utilities (includes config)"
    else
        echo "❌ Automatic backup manager missing common utilities integration"
        return 1
    fi

    # Check for direct database backup implementation
    if grep -q "drush sql:dump" "$db_script"; then
        echo "✅ Database backup script uses direct implementation"
    else
        echo "❌ Database backup script missing direct implementation"
        return 1
    fi

    local db_backup_section=$(sed -n '/^create_db_backup()/,/^create_static_backup()/p' "$manager_script")
    if echo "$db_backup_section" | grep -q 'drush sql:dump.*| tee\|gzip -c.*| tee\|aws s3 cp "$TEMP_GZIP".*| tee'; then
        echo "❌ Database backup masks critical command failures through tee pipelines"
        return 1
    fi
    echo "✅ Database backup preserves critical command exit codes"

    local backup_command_section=$(sed -n '/^run_backup_command()/,/^run_clean_command()/p' "$manager_script")
    if echo "$backup_command_section" | grep -q "failure_count" && echo "$backup_command_section" | grep -q "backup_status" && echo "$backup_command_section" | grep -q "failures"; then
        echo "✅ Backup command reports aggregate failures"
    else
        echo "❌ Backup command does not report aggregate failures"
        return 1
    fi

    echo "✅ Database backup system test passed"
    return 0
}

# Function to test backup download functionality
test_backup_download() {
    echo "📥 Testing backup download functionality..."

    # Create a test backup first
    if [ -z "$BUCKET_NAME" ]; then
        echo "⚠️ BUCKET_NAME not set - skipping download test"
        return 0
    fi

    setup_s3_vars || {
        echo "⚠️ Cannot setup S3 vars - skipping download test"
        return 0
    }

    local test_dir="/tmp/backup_download_test_$$"
    mkdir -p "$test_dir"

    # Generate unique test backup tag
    local test_tag="TEST-download-$$-$(date +%Y-%m-%d)"

    echo "🔄 Creating test backup for download testing: $test_tag"

    # Create test database backup
    local test_db_file="$test_dir/test-database.sql"
    echo "-- Test Database Backup" > "$test_db_file"
    echo "CREATE TABLE test (id INT);" >> "$test_db_file"
    gzip "$test_db_file"

    # Upload test database backup to S3
    local db_s3_path="s3://$BUCKET_NAME/${AUTO_DB_BACKUP_PATH}/${test_tag}-database.sql.gz"
    if ! aws s3 cp "$test_db_file.gz" "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1; then
        echo "❌ Failed to create test database backup"
        rm -rf "$test_dir"
        return 1
    fi
    echo "✅ Test database backup created"

    # Create test static backup (directory)
    local test_static_dir="$test_dir/static"
    mkdir -p "$test_static_dir"
    echo "<html><body>Test Static</body></html>" > "$test_static_dir/index.html"
    echo "Test file" > "$test_static_dir/test.txt"

    # Upload test static backup to S3
    local static_s3_path="s3://$BUCKET_NAME/${AUTO_STATIC_BACKUP_PATH}/${test_tag}/"
    if ! aws s3 sync "$test_static_dir/" "$static_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1; then
        echo "❌ Failed to create test static backup"
        aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
        rm -rf "$test_dir"
        return 1
    fi
    echo "✅ Test static backup created"

    # Create test public backup (directory)
    local test_public_dir="$test_dir/public"
    mkdir -p "$test_public_dir"
    echo "Public file content" > "$test_public_dir/public-file.txt"
    mkdir -p "$test_public_dir/subdir"
    echo "Subdirectory file" > "$test_public_dir/subdir/nested.txt"

    # Upload test public backup to S3
    local public_s3_path="s3://$BUCKET_NAME/${AUTO_PUBLIC_BACKUP_PATH}/${test_tag}/"
    if ! aws s3 sync "$test_public_dir/" "$public_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1; then
        echo "❌ Failed to create test public backup"
        aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
        aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
        rm -rf "$test_dir"
        return 1
    fi
    echo "✅ Test public backup created"

    # Test 1: Download database without stream
    echo ""
    echo "🧪 Test 1: Download database backup (local mode)"
    local download_dir_1="$test_dir/download_local"
    mkdir -p "$download_dir_1"

    if "$BACKUP_DIR/manager.sh" download "$test_tag" db "$download_dir_1" >/dev/null 2>&1; then
        if [ -f "$download_dir_1/${test_tag}-database.sql.gz" ]; then
            echo "✅ Database download (local mode) successful"
            # Verify file is not empty
            if [ -s "$download_dir_1/${test_tag}-database.sql.gz" ]; then
                echo "✅ Downloaded database file is not empty"
            else
                echo "❌ Downloaded database file is empty"
                aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
                aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
                aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
                rm -rf "$test_dir"
                return 1
            fi
        else
            echo "❌ Database file not found after download"
            aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
            aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
            aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
            rm -rf "$test_dir"
            return 1
        fi
    else
        echo "❌ Database download (local mode) failed"
        aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
        aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
        aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
        rm -rf "$test_dir"
        return 1
    fi

    # Test 2: Download database with stream mode
    echo ""
    echo "🧪 Test 2: Download database backup (stream mode)"
    local stream_file="$test_dir/stream-database.sql.gz"

    if "$BACKUP_DIR/manager.sh" download "$test_tag" db - --stream > "$stream_file" 2>/dev/null; then
        if [ -f "$stream_file" ] && [ -s "$stream_file" ]; then
            echo "✅ Database download (stream mode) successful"
            # Verify it's a valid gzip file
            if gunzip -t "$stream_file" 2>/dev/null; then
                echo "✅ Streamed database file is valid gzip"
            else
                echo "❌ Streamed database file is not valid gzip"
                aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
                aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
                aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
                rm -rf "$test_dir"
                return 1
            fi
        else
            echo "❌ Streamed database file is empty or missing"
            aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
            aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
            aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
            rm -rf "$test_dir"
            return 1
        fi
    else
        echo "❌ Database download (stream mode) failed"
        aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
        aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
        aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
        rm -rf "$test_dir"
        return 1
    fi

    # Test 3: Download static backup
    echo ""
    echo "🧪 Test 3: Download static backup (local mode)"
    local download_dir_2="$test_dir/download_static"
    mkdir -p "$download_dir_2"

    if "$BACKUP_DIR/manager.sh" download "$test_tag" static "$download_dir_2" >/dev/null 2>&1; then
        if [ -f "$download_dir_2/${test_tag}-static.tar.gz" ]; then
            echo "✅ Static download successful"
            # Verify tar.gz is valid
            if tar -tzf "$download_dir_2/${test_tag}-static.tar.gz" >/dev/null 2>&1; then
                echo "✅ Downloaded static tar.gz is valid"
            else
                echo "❌ Downloaded static tar.gz is invalid"
                aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
                aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
                aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
                rm -rf "$test_dir"
                return 1
            fi
        else
            echo "❌ Static file not found after download"
            aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
            aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
            aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
            rm -rf "$test_dir"
            return 1
        fi
    else
        echo "❌ Static download failed"
        aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
        aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
        aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
        rm -rf "$test_dir"
        return 1
    fi

    # Test 4: Download public backup with stream
    echo ""
    echo "🧪 Test 4: Download public backup (stream mode)"
    local stream_public="$test_dir/stream-public.tar.gz"

    if "$BACKUP_DIR/manager.sh" download "$test_tag" public - --stream > "$stream_public" 2>/dev/null; then
        if [ -f "$stream_public" ] && [ -s "$stream_public" ]; then
            echo "✅ Public download (stream mode) successful"
            # Verify it's a valid tar.gz
            if tar -tzf "$stream_public" >/dev/null 2>&1; then
                echo "✅ Streamed public tar.gz is valid"
            else
                echo "❌ Streamed public tar.gz is invalid"
                aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
                aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
                aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
                rm -rf "$test_dir"
                return 1
            fi
        else
            echo "❌ Streamed public file is empty or missing"
            aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
            aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
            aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
            rm -rf "$test_dir"
            return 1
        fi
    else
        echo "❌ Public download (stream mode) failed"
        aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
        aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
        aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
        rm -rf "$test_dir"
        return 1
    fi

    # Test 5: Download multiple types with comma-separated list
    echo ""
    echo "🧪 Test 5: Download multiple types (db,static)"
    local download_dir_3="$test_dir/download_multi"
    mkdir -p "$download_dir_3"

    if "$BACKUP_DIR/manager.sh" download "$test_tag" db,static "$download_dir_3" >/dev/null 2>&1; then
        local multi_success=true
        if [ ! -f "$download_dir_3/${test_tag}-database.sql.gz" ]; then
            echo "❌ Database file missing from multi-download"
            multi_success=false
        fi
        if [ ! -f "$download_dir_3/${test_tag}-static.tar.gz" ]; then
            echo "❌ Static file missing from multi-download"
            multi_success=false
        fi
        if [ "$multi_success" = true ]; then
            echo "✅ Multi-type download successful (db,static)"
        else
            aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
            aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
            aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
            rm -rf "$test_dir"
            return 1
        fi
    else
        echo "❌ Multi-type download failed"
        aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
        aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
        aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
        rm -rf "$test_dir"
        return 1
    fi

    # Cleanup: Remove test backups from S3
    echo ""
    echo "🧹 Cleaning up test backups..."
    aws s3 rm "$db_s3_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
    aws s3 rm "$static_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
    aws s3 rm "$public_s3_path" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1

    # Remove local test directory
    rm -rf "$test_dir"

    echo "✅ All download tests passed and cleanup complete"
    return 0
}

# Function to test new YYYY-MM-DD date format
test_date_format() {
    echo "📅 Testing YYYY-MM-DD date format implementation..."

    # Test date generation format
    local test_date=$(date +"%Y-%m-%d" 2>/dev/null)
    if echo "$test_date" | grep -qE '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'; then
        echo "✅ Date generation produces correct YYYY-MM-DD format: $test_date"
    else
        echo "❌ Date generation format incorrect: $test_date"
        return 1
    fi

    # Test date regex pattern matching
    local test_patterns="2025-10-28 2024-01-01 2023-12-31"
    for pattern in $test_patterns; do
        if echo "$pattern" | grep -qE '[0-9]{4}-[0-9]{2}-[0-9]{2}'; then
            echo "✅ Date pattern matches: $pattern"
        else
            echo "❌ Date pattern doesn't match: $pattern"
            return 1
        fi
    done

    # Test date parsing for both GNU and BSD
    local test_date="2025-10-28"
    local epoch_gnu=$(date -u -d "$test_date" '+%s' 2>/dev/null)
    local epoch_bsd=$(date -u -j -f '%Y-%m-%d' "$test_date" '+%s' 2>/dev/null)

    if [ -n "$epoch_gnu" ] || [ -n "$epoch_bsd" ]; then
        echo "✅ Date parsing works for YYYY-MM-DD format"
        [ -n "$epoch_gnu" ] && echo "  GNU date: $epoch_gnu"
        [ -n "$epoch_bsd" ] && echo "  BSD date: $epoch_bsd"
    else
        echo "❌ Date parsing failed for YYYY-MM-DD format"
        return 1
    fi

    # Test manager.sh uses correct format in documentation
    if grep -q "YYYY-MM-DD" "$BACKUP_DIR/manager.sh"; then
        echo "✅ manager.sh documentation uses YYYY-MM-DD format"
    else
        echo "❌ manager.sh documentation missing YYYY-MM-DD format"
        return 1
    fi

    echo "✅ Date format test passed"
    return 0
}

# Function to test backup tag parsing and extraction
test_tag_parsing() {
    echo "🏷️  Testing backup tag parsing and extraction..."

    # Test various tag formats
    local test_tags="
AUTO-prod-14850-2025-10-28
AUTO-prod-14850-2025-10-28-database.sql.gz
USAGOV-123-dev-git-abc123-2025-11-24-pre-deploy
AUTO-staging-14851-2025-12-31-post-update"

    for tag in $test_tags; do
        # Extract date using new pattern
        local extracted=$(echo "$tag" | grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2}' | head -1)
        if [ -n "$extracted" ] && echo "$extracted" | grep -qE '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'; then
            echo "✅ Successfully extracted date from tag: $tag -> $extracted"
        else
            echo "❌ Failed to extract date from tag: $tag"
            return 1
        fi
    done

    # Test tags without dates are handled
    local invalid_tag="AUTO-prod-14850-invalid-tag"
    local invalid_extracted=$(echo "$invalid_tag" | grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2}' | head -1)
    if [ -z "$invalid_extracted" ]; then
        echo "✅ Correctly handles tags without valid dates"
    else
        echo "❌ Incorrectly extracted from invalid tag: $invalid_extracted"
        return 1
    fi

    echo "✅ Tag parsing test passed"
    return 0
}

# Function to test Drupal state management
test_state_management() {
    echo "🔧 Testing Drupal state management..."

    local common_script="$PROJECT_ROOT/scripts/common.sh"
    local manager_script="$BACKUP_DIR/manager.sh"

    # Check if state management functions exist in common.sh
    if ! grep -q "^is_tome_running()" "$common_script"; then
        echo "❌ is_tome_running function not found in common.sh"
        return 1
    fi
    echo "✅ is_tome_running function exists"

    if ! grep -q "^prepare_drupal_state()" "$common_script"; then
        echo "❌ prepare_drupal_state function not found in common.sh"
        return 1
    fi
    echo "✅ prepare_drupal_state function exists"

    if ! grep -q "^restore_drupal_state()" "$common_script"; then
        echo "❌ restore_drupal_state function not found in common.sh"
        return 1
    fi
    echo "✅ restore_drupal_state function exists"

    # Check that backup functions call state management
    if ! grep -q "prepare_drupal_state" "$manager_script"; then
        echo "❌ manager.sh doesn't call prepare_drupal_state"
        return 1
    fi
    echo "✅ manager.sh integrates prepare_drupal_state"

    if ! grep -q "restore_drupal_state" "$manager_script"; then
        echo "❌ manager.sh doesn't call restore_drupal_state"
        return 1
    fi
    echo "✅ manager.sh integrates restore_drupal_state"

    # Check for --skip-state-management flag in backup command
    if ! grep -q "skip_state_management" "$manager_script"; then
        echo "❌ skip_state_management flag not found in manager.sh"
        return 1
    fi
    echo "✅ skip_state_management flag implemented"

    # Check for --ssm shorthand
    if ! grep -q '"--ssm"' "$manager_script"; then
        echo "❌ --ssm shorthand flag not found in manager.sh"
        return 1
    fi
    echo "✅ --ssm shorthand flag implemented"

    # Verify state management is called in create_db_backup
    if ! grep -q "create_db_backup" "$manager_script"; then
        echo "⚠️  create_db_backup function not found (may have different name)"
    else
        # Check that create_db_backup has state management logic
        local db_backup_section=$(sed -n '/^create_db_backup()/,/^}/p' "$manager_script")
        if echo "$db_backup_section" | grep -q 'prepare_drupal_state.*"maintenance"'; then
            echo "✅ Database backup integrates state preparation (maintenance mode)"
        else
            echo "❌ Database backup missing state preparation or not using maintenance mode"
            return 1
        fi

        if echo "$db_backup_section" | grep -q "restore_drupal_state"; then
            echo "✅ Database backup integrates state restoration"
        else
            echo "❌ Database backup missing state restoration"
            return 1
        fi
    fi

    # Verify state management is called in restore_backup for database restores
    if ! grep -q "restore_backup" "$manager_script"; then
        echo "⚠️  restore_backup function not found"
    else
        local restore_section=$(sed -n '/^restore_backup()/,/^}/p' "$manager_script")
        if echo "$restore_section" | grep -q 'prepare_drupal_state.*"both"'; then
            echo "✅ Restore integrates Tome and maintenance state preparation"
        else
            echo "❌ Restore missing Tome and maintenance state preparation"
            return 1
        fi

        if echo "$restore_section" | grep -q "prepare_drupal_for_backup"; then
            echo "❌ Database restore calls obsolete prepare_drupal_for_backup helper"
            return 1
        fi

        if echo "$restore_section" | grep -q "restore_drupal_state"; then
            echo "✅ Database restore integrates state restoration"
        else
            echo "❌ Database restore missing state restoration"
            return 1
        fi
    fi

    # Check that state management checks for tome process
    if ! grep -q "tome-run.sh" "$common_script"; then
        echo "❌ State management doesn't check for tome-run.sh process"
        return 1
    fi
    echo "✅ State management checks for tome-run.sh process"

    # Check for maintenance mode management
    if ! grep -q "system.maintenance_mode" "$common_script"; then
        echo "❌ State management doesn't handle maintenance mode"
        return 1
    fi
    echo "✅ State management handles maintenance mode"

    # Check for tome disabled flag management
    if ! grep -q "usagov.tome_run_disabled" "$common_script"; then
        echo "❌ State management doesn't handle tome disabled flag"
        return 1
    fi
    echo "✅ State management handles tome disabled flag"

    echo "✅ Drupal state management test passed"
    return 0
}

# Function to test restore functionality
test_restore_functionality() {
    echo "🔄 Testing restore functionality..."

    local manager_script="$BACKUP_DIR/manager.sh"

    # Check restore function exists
    if ! grep -q "^restore_backup()" "$manager_script"; then
        echo "❌ restore_backup function not found"
        return 1
    fi
    echo "✅ restore_backup function exists"

    # Check parse_restore_options function exists
    if ! grep -q "^parse_restore_options()" "$manager_script"; then
        echo "❌ parse_restore_options function not found"
        return 1
    fi
    echo "✅ parse_restore_options function exists"

    # Check smart restore finder exists in common utilities
    local common_script="$PROJECT_ROOT/scripts/common.sh"
    if ! grep -q "^find_corresponding_backup()" "$common_script"; then
        echo "❌ find_corresponding_backup function not found"
        return 1
    fi
    echo "✅ find_corresponding_backup function exists"

    # Test restore command requires tag
    "$manager_script" restore >/dev/null 2>&1
    local restore_exit=$?
    if [ $restore_exit -eq 1 ]; then
        echo "✅ Restore command correctly requires backup tag"
    else
        echo "❌ Restore command should fail without tag (exit: $restore_exit)"
        return 1
    fi

    # Check for --only option support
    if grep -q "\-\-only=" "$manager_script"; then
        echo "✅ Restore supports --only option for selective restore"
    else
        echo "❌ Restore --only option not found"
        return 1
    fi

    # Check for --skip-state-management and --ssm flags
    if grep -q "\-\-skip-state-management" "$manager_script" && grep -q "\-\-ssm" "$manager_script"; then
        echo "✅ Restore supports --skip-state-management and --ssm flags"
    else
        echo "❌ Restore state management flags not found"
        return 1
    fi

    # Check for --force and --yes options
    if grep -q "\-\-force" "$manager_script" && grep -q "\-\-yes" "$manager_script"; then
        echo "✅ Restore supports --force and --yes options"
    else
        echo "❌ Restore confirmation skip options not found"
        return 1
    fi

    local restore_section=$(sed -n '/^restore_backup()/,/^backup_info()/p' "$manager_script")
    if echo "$restore_section" | grep -q 'skip_confirmation=true' && echo "$restore_section" | grep -q 'exit 1'; then
        echo "✅ Restore cancellation returns failure unless confirmation is skipped"
    else
        echo "❌ Restore cancellation/skip-confirmation handling is incomplete"
        return 1
    fi

    if echo "$restore_section" | grep -q 's3://\$BUCKET_NAME/\$AUTO_STATIC_BACKUP_PATH/\$static_backup_tag/.*s3://\$BUCKET_NAME/web/.*--delete'; then
        echo "✅ Static restore deletes objects absent from the backup"
    else
        echo "❌ Static restore must sync the backup to web/ with --delete"
        return 1
    fi

    if echo "$restore_section" | grep -q 'Syncing theme assets from current codebase'; then
        echo "❌ Static restore must not overlay assets from the current container"
        return 1
    fi
    echo "✅ Static restore does not reintroduce current-container theme assets"

    local deploy_script="$PROJECT_ROOT/scripts/devops/deploy.sh"
    if grep -q "if ! exec_restore_command" "$deploy_script"; then
        echo "✅ Deployment rollback checks data restore failures"
    else
        echo "❌ Deployment rollback does not check data restore failures"
        return 1
    fi

    echo "✅ Restore functionality test passed"
    return 0
}

# Function to test smart public backup feature
test_smart_public_backup() {
    echo "🧠 Testing smart public backup feature..."

    # Check if smart backup is configurable
    local config_file="$BACKUP_DIR/backup-system.conf"
    if grep -q "ENABLE_SMART_PUBLIC_BACKUP" "$config_file"; then
        echo "✅ Smart public backup configuration exists"
        local smart_enabled=$(grep "^ENABLE_SMART_PUBLIC_BACKUP=" "$config_file" | cut -d= -f2)
        echo "  Current setting: $smart_enabled"
    else
        echo "❌ Smart public backup configuration missing"
        return 1
    fi

    # Check if manager implements smart backup logic
    local manager_script="$BACKUP_DIR/manager.sh"
    if grep -q "ENABLE_SMART_PUBLIC_BACKUP" "$manager_script"; then
        echo "✅ manager.sh implements smart public backup logic"
    else
        echo "❌ manager.sh missing smart public backup implementation"
        return 1
    fi

    # Check for checksum comparison logic
    if grep -q "md5sum\|MD5\|checksum" "$manager_script"; then
        echo "✅ Checksum comparison logic found"
    else
        echo "⚠️  Checksum comparison not explicitly found (may use different method)"
    fi

    echo "✅ Smart public backup test passed"
    return 0
}

# Function to test backup type combinations
test_backup_type_combinations() {
    echo "🔀 Testing backup type combinations..."

    local manager_script="$BACKUP_DIR/manager.sh"
    local common_script="$PROJECT_ROOT/scripts/common.sh"

    # Check parse_backup_types function
    if ! grep -q "^parse_backup_types()" "$common_script"; then
        echo "❌ parse_backup_types function not found"
        return 1
    fi
    echo "✅ parse_backup_types function exists"

    # Test various type combinations
    local type_combos="all static public db static,public static,db public,db static,public,db db,public,static"
    for combo in $type_combos; do
        # Test list command with combination
        "$manager_script" list "$combo" >/dev/null 2>&1
        local exit_code=$?
        if [ $exit_code -eq 0 ] || [ $exit_code -eq 1 ]; then
            echo "✅ Type combination works: $combo"
        else
            echo "❌ Type combination failed: $combo (exit: $exit_code)"
            return 1
        fi
    done

    # Test invalid type is rejected
    "$manager_script" list "invalid_type" >/dev/null 2>&1
    local invalid_exit=$?
    if [ $invalid_exit -ne 0 ]; then
        echo "✅ Invalid backup type correctly rejected"
    else
        echo "⚠️ Invalid backup type not rejected (may show all types by default)"
    fi

    echo "✅ Backup type combinations test passed"
    return 0
}

# Function to test cleanup with various retention periods
test_cleanup_retention() {
    echo "🧹 Testing cleanup with various retention periods..."

    local manager_script="$BACKUP_DIR/manager.sh"

    # Test cleanup functions exist
    local cleanup_funcs="cleanup_old_db_backups list_old_backups clean_old_backups cleanup_all_old_backups"
    for func in $cleanup_funcs; do
        if grep -q "^${func}()" "$manager_script"; then
            echo "✅ Cleanup function exists: $func"
        else
            echo "❌ Cleanup function missing: $func"
            return 1
        fi
    done

    # Test various retention periods
    local retention_days="0 1 7 14 30 60 90 365"
    for days in $retention_days; do
        # Test clean command parsing (with 'n' to cancel)
        echo "n" | "$manager_script" clean all "$days" >/dev/null 2>&1
        local exit_code=$?
        if [ $exit_code -eq 0 ] || [ $exit_code -eq 1 ]; then
            echo "✅ Retention period handled: $days days"
        else
            echo "❌ Retention period failed: $days days (exit: $exit_code)"
            return 1
        fi
    done

    # Test that 0 days requires special confirmation
    if grep -q "DELETE ALL" "$manager_script" || grep -q "delete all" "$manager_script"; then
        echo "✅ Zero-day retention has special confirmation"
    else
        echo "⚠️  Zero-day retention may not have special handling"
    fi

    echo "✅ Cleanup retention test passed"
    return 0
}

# Function to test backup info commands
test_backup_info() {
    echo "ℹ️  Testing backup info functionality..."

    local manager_script="$BACKUP_DIR/manager.sh"

    # Check info functions exist
    if ! grep -q "^backup_info()" "$manager_script"; then
        echo "❌ backup_info function not found"
        return 1
    fi
    echo "✅ backup_info function exists"

    if ! grep -q "^db_backup_info()" "$manager_script"; then
        echo "❌ db_backup_info function not found"
        return 1
    fi
    echo "✅ db_backup_info function exists"

    # Test info commands
    "$manager_script" info >/dev/null 2>&1
    if [ $? -eq 0 ] || [ $? -eq 1 ]; then
        echo "✅ 'info' command executes"
    else
        echo "❌ 'info' command failed"
        return 1
    fi

    # Test info with types
    local info_types="static public db all"
    for type in $info_types; do
        "$manager_script" info "$type" >/dev/null 2>&1
        if [ $? -eq 0 ] || [ $? -eq 1 ]; then
            echo "✅ 'info $type' command works"
        else
            echo "❌ 'info $type' command failed"
            return 1
        fi
    done

    echo "✅ Backup info test passed"
    return 0
}

# Function to test error handling
test_error_handling() {
    echo "⚠️  Testing error handling..."

    local manager_script="$BACKUP_DIR/manager.sh"

    # Test invalid commands
    "$manager_script" invalid_command >/dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "✅ Invalid command properly rejected"
    else
        echo "❌ Invalid command not properly rejected"
        return 1
    fi

    # Test commands with missing required arguments
    "$manager_script" download >/dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "✅ Download without tag properly rejected"
    else
        echo "❌ Download without tag should fail"
        return 1
    fi

    "$manager_script" backup >/dev/null 2>&1
    local backup_exit=$?
    if [ $backup_exit -eq 0 ] || [ $backup_exit -eq 1 ]; then
        echo "✅ Backup command handles missing arguments"
    else
        echo "⚠️  Backup command exit behavior: $backup_exit"
    fi

    # Test with invalid backup types
    "$manager_script" backup "invalid,types,here" >/dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "✅ Invalid backup types rejected"
    else
        echo "⚠️  Invalid backup types may not be properly validated"
    fi

    echo "✅ Error handling test passed"
    return 0
}

# Function to test cron setup
test_cron_setup() {
    echo "⏰ Testing cron setup functionality..."

    local cron_script="$BACKUP_DIR/setup-cron.sh"

    if [ ! -f "$cron_script" ]; then
        echo "❌ setup-cron.sh not found"
        return 1
    fi
    echo "✅ setup-cron.sh exists"

    if [ ! -x "$cron_script" ]; then
        echo "❌ setup-cron.sh is not executable"
        return 1
    fi
    echo "✅ setup-cron.sh is executable"

    # Check for required cron-related code
    if grep -q "crontab" "$cron_script" || grep -q "CRON" "$cron_script"; then
        echo "✅ Cron setup script contains cron management code"
    else
        echo "❌ Cron setup script missing cron management code"
        return 1
    fi

    # Check config for DB_BACKUP_TIME
    local config_file="$BACKUP_DIR/backup-system.conf"
    if grep -q "DB_BACKUP_TIME=" "$config_file"; then
        local backup_time=$(grep "^DB_BACKUP_TIME=" "$config_file" | cut -d= -f2 | tr -d '"')
        echo "✅ Database backup time configured: $backup_time"
    else
        echo "❌ DB_BACKUP_TIME not configured"
        return 1
    fi

    if [ "$backup_time" = "23:00" ]; then
        echo "✅ Database backup time is 23:00 UTC"
    else
        echo "❌ Database backup time must be 23:00 UTC (found: $backup_time)"
        return 1
    fi

    if grep -q 'utc_hour\|Converts EST' "$cron_script"; then
        echo "❌ Cron setup still contains Eastern-to-UTC conversion logic"
        return 1
    fi
    echo "✅ Cron setup uses UTC directly"

    if grep -Fq 'echo "$minute $hour * * * cd $CRON_WORK_DIR && $BACKUP_DIR/manager.sh backup $backup_types' "$cron_script"; then
        echo "✅ Cron setup schedules selected backup types at the configured UTC hour"
    else
        echo "❌ Cron entry does not use the configured UTC hour and selected backup types"
        return 1
    fi

    echo "✅ Cron setup test passed"
    return 0
}

# Function to test all configuration options
test_all_config_options() {
    echo "⚙️  Testing all configuration options..."

    local config_file="$BACKUP_DIR/backup-system.conf"

    # Required configuration variables
    local required_vars="
BACKUP_RETENTION_DAYS
BACKUP_PREFIX
AUTO_STATIC_BACKUP_PATH
AUTO_PUBLIC_BACKUP_PATH
AUTO_DB_BACKUP_PATH
BACKUP_THROTTLE_HOURS
ENABLE_STATIC_AUTO_BACKUPS
ENABLE_STATIC_AUTO_CLEANUP
ENABLE_PUBLIC_AUTO_BACKUPS
ENABLE_PUBLIC_AUTO_CLEANUP
ENABLE_SMART_PUBLIC_BACKUP
ENABLE_DB_BACKUPS
ENABLE_DB_AUTO_CLEANUP
DB_BACKUP_TIME
DB_BACKUP_RETENTION_DAYS
DB_BACKUP_PREFIX
"

    for var in $required_vars; do
        if grep -q "^${var}=" "$config_file"; then
            local value=$(grep "^${var}=" "$config_file" | cut -d= -f2)
            echo "✅ Config option exists: $var = $value"
        else
            echo "❌ Config option missing: $var"
            return 1
        fi
    done

    # Validate boolean values
    local bool_vars="ENABLE_STATIC_AUTO_BACKUPS ENABLE_STATIC_AUTO_CLEANUP ENABLE_PUBLIC_AUTO_BACKUPS ENABLE_PUBLIC_AUTO_CLEANUP ENABLE_SMART_PUBLIC_BACKUP ENABLE_DB_BACKUPS ENABLE_DB_AUTO_CLEANUP"
    for var in $bool_vars; do
        local value=$(grep "^${var}=" "$config_file" | cut -d= -f2)
        if [ "$value" = "true" ] || [ "$value" = "false" ]; then
            echo "✅ Boolean config valid: $var = $value"
        else
            echo "❌ Boolean config invalid: $var = $value (should be true/false)"
            return 1
        fi
    done

    # Validate numeric values
    local num_vars="BACKUP_RETENTION_DAYS DB_BACKUP_RETENTION_DAYS BACKUP_THROTTLE_HOURS"
    for var in $num_vars; do
        local value=$(grep "^${var}=" "$config_file" | cut -d= -f2)
        if echo "$value" | grep -qE '^[0-9]+$'; then
            echo "✅ Numeric config valid: $var = $value"
        else
            echo "❌ Numeric config invalid: $var = $value (should be a number)"
            return 1
        fi
    done

    echo "✅ All configuration options test passed"
    return 0
}

# Function to test local-manager.sh wrapper
test_local_manager() {
    echo "💻 Testing local-manager.sh wrapper..."

    local local_manager="$PROJECT_ROOT/scripts/devops/local-manager.sh"

    if [ ! -f "$local_manager" ]; then
        echo "❌ local-manager.sh not found"
        return 1
    fi
    echo "✅ local-manager.sh exists"

    if [ ! -x "$local_manager" ]; then
        echo "❌ local-manager.sh is not executable"
        return 1
    fi
    echo "✅ local-manager.sh is executable"

    # Test usage
    "$local_manager" --help >/dev/null 2>&1
    local help_exit=$?
    if [ $help_exit -eq 0 ]; then
        echo "✅ local-manager.sh --help works"
    else
        echo "⚠️  local-manager.sh --help exit code: $help_exit (may require CF CLI)"
        # Don't fail test - local-manager might check for CF CLI
    fi

    # Check for YYYY-MM-DD format in documentation
    if grep -q "YYYY-MM-DD" "$local_manager"; then
        echo "✅ local-manager.sh uses YYYY-MM-DD format"
    else
        echo "❌ local-manager.sh missing YYYY-MM-DD format"
        return 1
    fi

    # Check for cron command support
    if grep -q "cron" "$local_manager"; then
        echo "✅ local-manager.sh supports cron command"
    else
        echo "❌ local-manager.sh missing cron command"
        return 1
    fi

    # Check for all main commands
    local commands="list backup clean delete restore info download test current-digests cron state"
    for cmd in $commands; do
        if grep -q "\"$cmd\"" "$local_manager"; then
            echo "✅ local-manager.sh supports '$cmd' command"
        else
            echo "❌ local-manager.sh missing '$cmd' command"
            return 1
        fi
    done

    echo "✅ Local manager test passed"
    return 0
}

# Function to test common.sh utility functions
test_common_utilities() {
    echo "🔧 Testing common.sh utility functions..."

    local common_file="$PROJECT_ROOT/scripts/common.sh"

    if [ ! -f "$common_file" ]; then
        echo "❌ common.sh not found"
        return 1
    fi
    echo "✅ common.sh exists"

    # Source common.sh
    . "$common_file"

    # Test init_backup_system was called
    if [ -n "$BACKUP_DIR" ] && [ -n "$PROJECT_ROOT" ]; then
        echo "✅ init_backup_system initialized paths correctly"
    else
        echo "❌ init_backup_system failed to initialize paths"
        return 1
    fi

    # Test check_command function
    if check_command "sh"; then
        echo "✅ check_command works for existing commands"
    else
        echo "❌ check_command failed for existing command"
        return 1
    fi

    if ! check_command "nonexistent_command_xyz"; then
        echo "✅ check_command correctly identifies missing commands"
    else
        echo "❌ check_command incorrectly found nonexistent command"
        return 1
    fi

    # Test get_container_tag function
    local container_tag=$(get_container_tag)
    if [ -n "$container_tag" ]; then
        echo "✅ get_container_tag returns: $container_tag"
    else
        echo "❌ get_container_tag returned empty"
        return 1
    fi

    # Test print_status function (just verify it doesn't error)
    print_status $BLUE "Test message" >/dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "✅ print_status function works"
    else
        echo "❌ print_status function failed"
        return 1
    fi

    # Test log_message function
    log_message "Test log message" >/dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "✅ log_message function works"
    else
        echo "❌ log_message function failed"
        return 1
    fi

    if printf 'no\n' | confirm_action "Test confirmation" >/dev/null 2>&1; then
        echo "❌ confirm_action should fail when confirmation is declined"
        return 1
    else
        echo "✅ confirm_action fails when confirmation is declined"
    fi

    # Test validate_sql_dump function exists
    if ! grep -q "^validate_sql_dump()" "$common_file"; then
        echo "❌ validate_sql_dump function not found"
        return 1
    fi
    echo "✅ validate_sql_dump function exists"

    # Test validate_sql_dump with a mock SQL file
    local test_sql="/tmp/test_dump_$$.sql"

    # Create a valid-looking SQL dump
    cat > "$test_sql" << 'EOF'
-- MySQL dump 10.13  Distrib 5.7.32, for Linux (x86_64)
--
-- Host: localhost    Database: drupal
-- ------------------------------------------------------
-- Server version	5.7.32

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;

CREATE TABLE `users` (
  `uid` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` VALUES (1,'admin');

-- Dump completed on 2025-11-28
EOF

    if validate_sql_dump "$test_sql" >/dev/null 2>&1; then
        echo "✅ validate_sql_dump accepts valid SQL dump"
    else
        echo "❌ validate_sql_dump rejected valid SQL dump"
        rm -f "$test_sql"
        return 1
    fi

    # Serialized Drupal data commonly contains words like "system"; it is not
    # a dangerous SQL statement and must not block a database restore.
    echo "INSERT INTO config VALUES (1, 'system');" > "$test_sql"
    if validate_sql_content "$test_sql" >/dev/null 2>&1; then
        echo "✅ validate_sql_content accepts serialized data"
    else
        echo "❌ validate_sql_content rejected safe serialized data"
        rm -f "$test_sql"
        return 1
    fi

    echo "SYSTEM whoami;" > "$test_sql"
    if ! validate_sql_content "$test_sql" >/dev/null 2>&1; then
        echo "✅ validate_sql_content rejects dangerous SQL statements"
    else
        echo "❌ validate_sql_content accepted a dangerous SQL statement"
        rm -f "$test_sql"
        return 1
    fi

    # Test with invalid content
    echo "This is not a SQL dump" > "$test_sql"
    if ! validate_sql_dump "$test_sql" >/dev/null 2>&1; then
        echo "✅ validate_sql_dump rejects invalid SQL dump"
    else
        echo "❌ validate_sql_dump accepted invalid SQL dump"
        rm -f "$test_sql"
        return 1
    fi

    rm -f "$test_sql"

    # Database backup and restore must return Drupal to the state found at
    # the beginning of the operation, including an already-disabled Tome.
    if (
        fake_maintenance=1
        fake_tome_disabled=1
        drush() {
            case "$1:$2" in
                sget:system.maintenance_mode) echo "$fake_maintenance" ;;
                sget:usagov.tome_run_disabled) [ "$fake_tome_disabled" = "1" ] && echo "1" ;;
                sset:system.maintenance_mode) fake_maintenance="$3" ;;
                sset:usagov.tome_run_disabled) fake_tome_disabled=1 ;;
                sdel:usagov.tome_run_disabled) fake_tome_disabled=0 ;;
                cr:*) : ;;
                *) return 1 ;;
            esac
        }
        INSIDE_TOME_PROCESS=1
        prepare_drupal_state both 0 >/dev/null &&
            restore_drupal_state both >/dev/null &&
            [ "$fake_maintenance" = "1" ] &&
            [ "$fake_tome_disabled" = "1" ]
    ); then
        echo "✅ Drupal state is restored to captured values"
    else
        echo "❌ Drupal state was not restored to captured values"
        return 1
    fi

    echo "✅ Common utilities test passed"
    return 0
}

# Function to test setup-cron.sh script
test_cron_script() {
    echo "📅 Testing setup-cron.sh script..."

    local cron_script="$BACKUP_DIR/setup-cron.sh"

    if [ ! -f "$cron_script" ]; then
        echo "❌ setup-cron.sh not found"
        return 1
    fi
    echo "✅ setup-cron.sh exists"

    if [ ! -x "$cron_script" ]; then
        echo "❌ setup-cron.sh is not executable"
        return 1
    fi
    echo "✅ setup-cron.sh is executable"

    # Check for all required cron operations
    local operations="setup remove status list test"
    for op in $operations; do
        if grep -qi "$op" "$cron_script"; then
            echo "✅ setup-cron.sh supports '$op' operation"
        else
            echo "⚠️  setup-cron.sh may not support '$op' operation"
        fi
    done

    # Test help command
    "$cron_script" --help >/dev/null 2>&1
    local help_exit=$?
    if [ $help_exit -eq 0 ] || [ $help_exit -eq 1 ]; then
        echo "✅ setup-cron.sh --help works"
    else
        echo "⚠️  setup-cron.sh --help had issues (exit: $help_exit)"
    fi

    # Check syntax
    sh -n "$cron_script" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "✅ setup-cron.sh has valid shell syntax"
    else
        echo "❌ setup-cron.sh has syntax errors"
        return 1
    fi

    echo "✅ Cron script test passed"
    return 0
}

# Function to test S3 path configurations
test_s3_paths() {
    echo "☁️  Testing S3 path configurations..."

    # Test all S3 paths are configured
    if [ -n "$AUTO_STATIC_BACKUP_PATH" ]; then
        echo "✅ AUTO_STATIC_BACKUP_PATH configured: $AUTO_STATIC_BACKUP_PATH"
    else
        echo "❌ AUTO_STATIC_BACKUP_PATH not configured"
        return 1
    fi

    if [ -n "$AUTO_PUBLIC_BACKUP_PATH" ]; then
        echo "✅ AUTO_PUBLIC_BACKUP_PATH configured: $AUTO_PUBLIC_BACKUP_PATH"
    else
        echo "❌ AUTO_PUBLIC_BACKUP_PATH not configured"
        return 1
    fi

    if [ -n "$AUTO_DB_BACKUP_PATH" ]; then
        echo "✅ AUTO_DB_BACKUP_PATH configured: $AUTO_DB_BACKUP_PATH"
    else
        echo "❌ AUTO_DB_BACKUP_PATH not configured"
        return 1
    fi

    # Verify paths don't have leading/trailing slashes issues
    for path in "$AUTO_STATIC_BACKUP_PATH" "$AUTO_PUBLIC_BACKUP_PATH" "$AUTO_DB_BACKUP_PATH"; do
        if echo "$path" | grep -q "^/"; then
            echo "⚠️  Path has leading slash (may cause S3 issues): $path"
        fi
        if echo "$path" | grep -q "/$"; then
            echo "⚠️  Path has trailing slash: $path"
        fi
    done

    # Test paths don't conflict
    if [ "$AUTO_STATIC_BACKUP_PATH" = "$AUTO_PUBLIC_BACKUP_PATH" ] || \
       [ "$AUTO_STATIC_BACKUP_PATH" = "$AUTO_DB_BACKUP_PATH" ] || \
       [ "$AUTO_PUBLIC_BACKUP_PATH" = "$AUTO_DB_BACKUP_PATH" ]; then
        echo "❌ S3 backup paths conflict with each other"
        return 1
    else
        echo "✅ S3 backup paths are distinct"
    fi

    echo "✅ S3 paths test passed"
    return 0
}

# Function to test documentation consistency
test_documentation() {
    echo "📚 Testing documentation consistency..."

    local readme="$BACKUP_DIR/README.md"

    if [ ! -f "$readme" ]; then
        echo "❌ README.md not found"
        return 1
    fi
    echo "✅ README.md exists"

    # Check README uses YYYY-MM-DD format
    if grep -q "YYYY-MM-DD" "$readme"; then
        echo "✅ README.md uses YYYY-MM-DD format"
    else
        echo "❌ README.md missing YYYY-MM-DD format"
        return 1
    fi

    # Verify all main commands are documented
    local commands="list backup clean restore info download"
    for cmd in $commands; do
        if grep -q "$cmd" "$readme"; then
            echo "✅ README.md documents '$cmd' command"
        else
            echo "❌ README.md missing '$cmd' command documentation"
            return 1
        fi
    done

    # Check for backup types documentation
    local types="static public db database"
    for type in $types; do
        if grep -qi "$type" "$readme"; then
            echo "✅ README.md documents '$type' backup type"
        else
            echo "⚠️  README.md may be missing '$type' backup type"
        fi
    done

    echo "✅ Documentation test passed"
    return 0
}

# Function to test the delete command (delete specific backups by tag)
test_delete_command() {
    echo "🗑️  Testing delete command..."

    local manager_script="$BACKUP_DIR/manager.sh"

    # Check delete_backup function exists
    if ! grep -q "^delete_backup()" "$manager_script"; then
        echo "❌ delete_backup function not found"
        return 1
    fi
    echo "✅ delete_backup function exists"

    # Check delete command is routed in the dispatcher
    if grep -q '"delete")' "$manager_script"; then
        echo "✅ delete command is routed in dispatcher"
    else
        echo "❌ delete command not routed in dispatcher"
        return 1
    fi

    # Check delete is documented in usage
    if grep -q "delete <tag>" "$manager_script"; then
        echo "✅ delete command documented in usage"
    else
        echo "❌ delete command missing from usage"
        return 1
    fi

    # Delete without a tag should fail (requires at least one tag)
    "$manager_script" delete >/dev/null 2>&1
    local delete_exit=$?
    if [ $delete_exit -ne 0 ]; then
        echo "✅ delete without tag correctly rejected (exit: $delete_exit)"
    else
        echo "❌ delete without tag should fail"
        return 1
    fi

    # Check delete supports multiple tags (loop over tags)
    local delete_func=$(sed -n '/^delete_backup()/,/^}/p' "$manager_script")
    if echo "$delete_func" | grep -q "for backup_tag in"; then
        echo "✅ delete supports multiple tags"
    else
        echo "❌ delete missing multiple-tag support"
        return 1
    fi

    # Check delete supports non-interactive flag
    if echo "$delete_func" | grep -q "non_interactive"; then
        echo "✅ delete supports -y/--non-interactive flag"
    else
        echo "❌ delete missing non-interactive support"
        return 1
    fi

    # Check local-manager.sh routes delete to CF
    local local_manager="$PROJECT_ROOT/scripts/devops/local-manager.sh"
    if grep -q '"delete")' "$local_manager"; then
        echo "✅ local-manager.sh routes delete command"
    else
        echo "❌ local-manager.sh missing delete command"
        return 1
    fi

    echo "✅ Delete command test passed"
    return 0
}

# Function to test backup throttling feature (--throttle)
test_backup_throttle() {
    echo "⏳ Testing backup throttle feature..."

    local manager_script="$BACKUP_DIR/manager.sh"
    local config_file="$BACKUP_DIR/backup-system.conf"

    # Check BACKUP_THROTTLE_HOURS is configured
    if grep -q "^BACKUP_THROTTLE_HOURS=" "$config_file"; then
        local throttle_hours=$(grep "^BACKUP_THROTTLE_HOURS=" "$config_file" | cut -d= -f2)
        echo "✅ BACKUP_THROTTLE_HOURS configured: $throttle_hours"
    else
        echo "❌ BACKUP_THROTTLE_HOURS not configured"
        return 1
    fi

    # Check get_last_backup_age_hours helper exists
    if ! grep -q "^get_last_backup_age_hours()" "$manager_script"; then
        echo "❌ get_last_backup_age_hours function not found"
        return 1
    fi
    echo "✅ get_last_backup_age_hours function exists"

    # Check run_backup_command parses --throttle
    local backup_func=$(sed -n '/^run_backup_command()/,/^}/p' "$manager_script")
    if echo "$backup_func" | grep -q "\-\-throttle"; then
        echo "✅ run_backup_command parses --throttle flag"
    else
        echo "❌ run_backup_command missing --throttle parsing"
        return 1
    fi

    # Check throttle logic compares against BACKUP_THROTTLE_HOURS
    if echo "$backup_func" | grep -q "BACKUP_THROTTLE_HOURS"; then
        echo "✅ Throttle logic uses BACKUP_THROTTLE_HOURS threshold"
    else
        echo "❌ Throttle logic missing BACKUP_THROTTLE_HOURS comparison"
        return 1
    fi

    # Check --throttle is documented in usage
    if grep -q "\-\-throttle" "$manager_script"; then
        echo "✅ --throttle documented in usage"
    else
        echo "❌ --throttle missing from usage"
        return 1
    fi

    # Verify the throttle flag parses without error (backup with throttle, no S3 needed to parse)
    "$manager_script" backup static TEST "" --throttle >/dev/null 2>&1
    local throttle_exit=$?
    if [ $throttle_exit -eq 0 ] || [ $throttle_exit -eq 1 ]; then
        echo "✅ backup --throttle parses correctly (exit: $throttle_exit)"
    else
        echo "⚠️  backup --throttle exit behavior: $throttle_exit (may be due to missing S3 access)"
    fi

    echo "✅ Backup throttle test passed"
    return 0
}

# Function to test the reorganized state command
test_state_commands() {
    echo "🔧 Testing state command..."

    local manager_script="$BACKUP_DIR/manager.sh"
    local local_manager="$PROJECT_ROOT/scripts/devops/local-manager.sh"

    # Check state command is routed
    if grep -q '"state")' "$manager_script"; then
        echo "✅ state command routed in manager.sh"
    else
        echo "❌ state command not routed in manager.sh"
        return 1
    fi

    # Check shared state dispatcher exists
    local common_script="$PROJECT_ROOT/scripts/common.sh"
    if grep -q '^state_command()' "$common_script"; then
        echo "✅ state_command function exists in common.sh"
    else
        echo "❌ state_command function missing from common.sh"
        return 1
    fi

    # Check disable and enable actions use the generalized state helpers
    local state_func=$(sed -n '/^state_command()/,/^}/p' "$common_script")
    if echo "$state_func" | grep -q 'prepare_drupal_state' && \
       echo "$state_func" | grep -q 'restore_drupal_state'; then
        echo "✅ state command supports disable and enable actions"
    else
        echo "❌ state command missing enable/disable helpers"
        return 1
    fi

    # Check manager delegates to state_command
    if sed -n '/"state")/,/;;/p' "$manager_script" | grep -q "state_command"; then
        echo "✅ manager.sh delegates state operations to common.sh"
    else
        echo "❌ manager.sh does not delegate state operations"
        return 1
    fi

    # Check state command is documented in usage
    if grep -q "state <action> <type>" "$manager_script"; then
        echo "✅ state command documented in usage"
    else
        echo "❌ state command missing from usage"
        return 1
    fi

    # Check local-manager.sh routes state command to CF
    if grep -q '"state")' "$local_manager"; then
        echo "✅ local-manager.sh routes state command"
    else
        echo "❌ local-manager.sh missing state command"
        return 1
    fi

    echo "✅ State command test passed"
    return 0
}

# Main execution
main() {
    # Parse command line arguments
    while [ $# -gt 0 ]; do
        case $1 in
            --help|-h)
                show_usage
                exit 0
                ;;
            *)
                echo "Unknown option: $1"
                show_usage
                exit 1
                ;;
        esac
    done

    # Run the test suite
    print_status $BLUE "🧪 Backup System Test Suite"
    print_status $BLUE "========================="

    echo ""
    print_status $YELLOW "🔧 Setting up test environment..."
    setup_test_env

    # Run all tests - organized by category
    echo ""
    print_status $BLUE "📋 BASIC SYSTEM TESTS"
    print_status $BLUE "====================="
    run_test "Configuration File Loading" "test_config_loading"
    run_test "All Configuration Options" "test_all_config_options"
    run_test "Script Files and Permissions" "test_script_files"
    run_test "Required Dependencies" "test_dependencies"
    run_test "AWS Connectivity" "test_aws_connectivity"
    run_test "Log Directory Access" "test_log_directory"

    echo ""
    print_status $BLUE "📅 DATE FORMAT TESTS"
    print_status $BLUE "===================="
    run_test "YYYY-MM-DD Date Format" "test_date_format"
    run_test "Date Calculations" "test_date_calculations"
    run_test "Backup Tag Parsing" "test_tag_parsing"
    run_test "Backup Naming Pattern" "test_backup_naming"
    run_test "Date Range Filtering" "test_date_range_filtering"
    run_test "Explicit Clean Flags" "test_explicit_clean_flags"

    echo ""
    print_status $BLUE "🔧 MANAGER FUNCTIONALITY TESTS"
    print_status $BLUE "=============================="
    run_test "Backup Integration in tome-sync.sh" "test_backup_integration"
    run_test "Backup Manager Functionality" "test_backup_manager"
    run_test "Manager Commands Interface" "test_manager_commands"
    run_test "Backup Type Combinations" "test_backup_type_combinations"
    run_test "Backup Info Functionality" "test_backup_info"
    run_test "Delete Command" "test_delete_command"
    run_test "Backup Throttle Feature" "test_backup_throttle"
    run_test "State Command" "test_state_commands"
    run_test "Error Handling" "test_error_handling"

    echo ""
    print_status $BLUE "💾 BACKUP OPERATIONS TESTS"
    print_status $BLUE "=========================="
    run_test "Database Backup System" "test_database_backup_system"
    run_test "Smart Public Backup" "test_smart_public_backup"
    run_test "Cleanup with Retention Periods" "test_cleanup_retention"
    run_test "Backup Simulation" "test_backup_simulation"
    run_test "Backup Download Functionality" "test_backup_download"

    echo ""
    print_status $BLUE "🔄 RESTORE & ADVANCED TESTS"
    print_status $BLUE "==========================="
    run_test "Restore Functionality" "test_restore_functionality"
    run_test "Drupal State Management" "test_state_management"
    run_test "Cron Setup" "test_cron_setup"
    run_test "Cron Script" "test_cron_script"
    run_test "Local Manager Wrapper" "test_local_manager"
    run_test "Common Utilities" "test_common_utilities"
    run_test "S3 Path Configuration" "test_s3_paths"
    run_test "Documentation Consistency" "test_documentation"

    # Test results summary
    echo ""
    echo ""
    print_status $BLUE "═══════════════════════════════════════════════════════════════"
    print_status $BLUE "                      📊 TEST RESULTS SUMMARY"
    print_status $BLUE "═══════════════════════════════════════════════════════════════"
    echo ""

    local pass_rate=0
    if [ $TESTS_TOTAL -gt 0 ]; then
        pass_rate=$((TESTS_PASSED * 100 / TESTS_TOTAL))
    fi

    echo "📊 Total Tests Run: $TESTS_TOTAL"
    print_status $GREEN "✅ Tests Passed: $TESTS_PASSED"
    print_status $RED "❌ Tests Failed: $TESTS_FAILED"
    echo "📈 Pass Rate: ${pass_rate}%"
    echo ""

    # Category breakdown
    echo "Test Categories Covered:"
    echo "  • Basic System Tests (6 tests)"
    echo "  • Date Format Tests (6 tests)"
    echo "  • Manager Functionality Tests (9 tests)"
    echo "  • Backup Operations Tests (5 tests)"
    echo "  • Restore & Advanced Tests (8 tests)"
    echo "  • Total: 34 comprehensive tests"
    echo ""

    # Final summary
    if [ $TESTS_FAILED -eq 0 ]; then
        print_status $BLUE "═══════════════════════════════════════════════════════════════"
        print_status $GREEN "🎉 ALL TESTS PASSED! The backup system is fully operational."
        print_status $BLUE "═══════════════════════════════════════════════════════════════"
        echo ""
        print_status $YELLOW "✨ Key Features Validated:"
        echo "  ✅ YYYY-MM-DD date format implementation"
        echo "  ✅ Date range filtering (list & clean by date ranges)"
        echo "  ✅ Database backup system with cron scheduling"
        echo "  ✅ Static site and public files backup"
        echo "  ✅ Smart public backup (checksum-based)"
        echo "  ✅ Backup restoration with find_corresponding"
        echo "  ✅ Cleanup with configurable retention"
        echo "  ✅ Download functionality (local & stream modes)"
        echo "  ✅ Multi-type backup support"
        echo "  ✅ Drupal state management (tome & maintenance mode)"
        echo "  ✅ Local and remote manager scripts"
        echo ""
        print_status $YELLOW "👉 Next Steps:"
        echo "  1. Create a test backup:"
        echo "     scripts/snapshot/manager.sh backup all TEST test-backup"
        echo ""
        echo "  2. List existing backups:"
        echo "     scripts/snapshot/manager.sh list"
        echo ""
        echo "  3. View backup info:"
        echo "     scripts/snapshot/manager.sh info"
        echo ""
        echo "  4. Setup automated backups (if on Cloud Foundry):"
        echo "     scripts/snapshot/setup-cron.sh"
        echo ""
        echo "  5. Run Tome sync to test automatic backup integration:"
        echo "     scripts/tome-sync.sh"
        echo ""
        return 0
    else
        print_status $BLUE "═══════════════════════════════════════════════════════════════"
        print_status $RED "❌ SOME TESTS FAILED (${TESTS_FAILED}/${TESTS_TOTAL})"
        print_status $BLUE "═══════════════════════════════════════════════════════════════"
        echo ""
        print_status $YELLOW "🔧 Common Fixes:"
        echo "  1. Install missing dependencies:"
        echo "     • AWS CLI: https://aws.amazon.com/cli/"
        echo "     • jq: brew install jq (macOS) or apt-get install jq (Linux)"
        echo ""
        echo "  2. Configure AWS credentials:"
        echo "     • Set VCAP_SERVICES environment (Cloud Foundry)"
        echo "     • Or configure aws cli: aws configure"
        echo ""
        echo "  3. Verify file permissions:"
        echo "     chmod +x scripts/snapshot/*.sh"
        echo ""
        echo "  4. Check S3 bucket access:"
        echo "     aws s3 ls s3://\$BUCKET_NAME/"
        echo ""
        print_status $YELLOW "💡 Tip: Review failed test output above for specific issues"
        echo ""
        return 1
    fi
}

# Initialize backup system paths
init_backup_system

# Check if we can find tome-sync.sh
if [ ! -f "$PROJECT_ROOT/scripts/tome-sync.sh" ]; then
    print_status $RED "❌ Error: Cannot find required files (tome-sync.sh not found at $PROJECT_ROOT/scripts/tome-sync.sh)"
    print_status $YELLOW "💡 Please ensure you're running from the project root or scripts/snapshot directory"
    exit 1
fi

# Run main function
main "$@"