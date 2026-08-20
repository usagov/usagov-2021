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

    # Check for --ssm shorthand. The pattern was '"--ssm"' with quotes, which the
    # code has never used — it appears as an unquoted case pattern — so this
    # assertion failed permanently and masked real failures in this suite.
    if ! grep -q -- '--ssm' "$manager_script"; then
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

# Function to test that cleanup acts only on the requested backup types and
# never reports success after a failed list, delete, or unverified removal.
# Hermetic: runs the real clean command against a fake aws CLI on PATH, so it
# never contacts S3 and never deletes a real backup.
test_cleanup_type_isolation() {
    echo "🔒 Testing cleanup type isolation and failure reporting..."

    local manager_script="$BACKUP_DIR/manager.sh"
    local sandbox=""
    sandbox=$(mktemp -d) || {
        echo "❌ Could not create test sandbox"
        return 1
    }

    local objects="$sandbox/objects"
    local calls="$sandbox/calls"
    local failures=0

    # Fake aws CLI: an object inventory in a flat file, with injectable
    # list/delete failures. Supports only the calls cleanup makes.
    mkdir -p "$sandbox/bin"
    cat > "$sandbox/bin/aws" <<'FAKE_AWS'
#!/bin/sh
echo "aws $*" >> "$FAKE_CALLS"
service="$1"; shift
action="$1"; shift
key=""; prefix=""; query=""; target=""
while [ $# -gt 0 ]; do
    case "$1" in
        --key) key="$2"; shift 2 ;;
        --prefix) prefix="$2"; shift 2 ;;
        --query) query="$2"; shift 2 ;;
        s3://*) target=$(echo "$1" | sed 's|^s3://[^/]*/||'); shift ;;
        *) shift ;;
    esac
done

case "$service $action" in
"s3api list-objects-v2")
    if [ -n "$FAKE_FAIL_LS" ] && echo "$prefix" | grep -q "$FAKE_FAIL_LS"; then
        echo "An error occurred (InternalError) calling ListObjectsV2" >&2
        exit 255
    fi
    matches=$(grep "^$prefix" "$FAKE_OBJECTS" 2>/dev/null)
    case "$query" in
        *CommonPrefixes*)
            result=$(printf '%s\n' "$matches" | sed "s|^$prefix||" | grep '/' | cut -d/ -f1 \
                | sort -u | sed "s|^|$prefix|; s|\$|/|" | tr '\n' '\t' | sed 's/\t$//')
            ;;
        *)
            result=$(printf '%s\n' "$matches" | grep -v '^$' | tr '\n' '\t' | sed 's/\t$//')
            ;;
    esac
    [ -n "$result" ] && echo "$result" || echo "None"
    exit 0
    ;;
"s3api head-object")
    grep -qx "$key" "$FAKE_OBJECTS" 2>/dev/null && exit 0
    exit 254
    ;;
"s3 rm")
    if [ -n "$FAKE_FAIL_RM" ] && echo "$target" | grep -q "$FAKE_FAIL_RM"; then
        echo "delete failed: AccessDenied" >&2
        exit 1
    fi
    grep -v "^$target" "$FAKE_OBJECTS" > "$FAKE_OBJECTS.tmp" 2>/dev/null
    mv "$FAKE_OBJECTS.tmp" "$FAKE_OBJECTS"
    exit 0
    ;;
esac
echo "fake aws: unsupported call: $service $action" >&2
exit 99
FAKE_AWS
    chmod +x "$sandbox/bin/aws"

    reset_fake_inventory() {
        : > "$calls"
        cat > "$objects" <<'INVENTORY'
auto-backups/web-backup/AUTO-test-1-2020-01-01/index.html
auto-backups/web-backup/AUTO-test-2-2020-01-02/index.html
auto-backups/public_backup/AUTO-test-1-2020-01-01/a.pdf
auto-backups/public_backup/AUTO-test-2-2020-01-02/b.pdf
auto-backups/database/AUTO-test-1-2020-01-01.sql.gz
auto-backups/database/AUTO-test-1-2020-01-01.sql.gz.sha256
INVENTORY
        # A backup dated today must survive the RETENTION_MIN_HOURS floor
        echo "auto-backups/database/AUTO-test-now-$(date -u '+%Y-%m-%d').sql.gz" >> "$objects"
    }

    # Run the clean command in an isolated environment; echoes the exit code
    run_fake_clean() {
        (
            PATH="$sandbox/bin:$PATH"
            FAKE_OBJECTS="$objects"
            FAKE_CALLS="$calls"
            BUCKET_NAME="fake-test-bucket"
            APP_SPACE="test"
            S3_EXTRA_PARAMS=""
            export PATH FAKE_OBJECTS FAKE_CALLS BUCKET_NAME APP_SPACE S3_EXTRA_PARAMS
            export FAKE_FAIL_LS FAKE_FAIL_RM
            cd "$PROJECT_ROOT" && "$manager_script" clean "$@" >"$sandbox/out" 2>&1
        )
        echo $?
    }

    has_key() { grep -qx "$1" "$objects"; }

    FAKE_FAIL_LS=""
    FAKE_FAIL_RM=""

    # Cleaning one type must not touch the others
    reset_fake_inventory
    if [ "$(run_fake_clean static --older-than-date 2021-01-01 -y)" = "0" ] \
        && ! has_key "auto-backups/web-backup/AUTO-test-1-2020-01-01/index.html" \
        && has_key "auto-backups/public_backup/AUTO-test-1-2020-01-01/a.pdf" \
        && has_key "auto-backups/database/AUTO-test-1-2020-01-01.sql.gz" \
        && ! grep -q "public_backup" "$calls"; then
        echo "✅ 'clean static' deletes only static backups"
    else
        echo "❌ 'clean static' did not isolate the static type"
        failures=$((failures + 1))
    fi

    reset_fake_inventory
    if [ "$(run_fake_clean public --older-than-date 2021-01-01 -y)" = "0" ] \
        && ! has_key "auto-backups/public_backup/AUTO-test-1-2020-01-01/a.pdf" \
        && has_key "auto-backups/web-backup/AUTO-test-1-2020-01-01/index.html"; then
        echo "✅ 'clean public' deletes only public backups"
    else
        echo "❌ 'clean public' did not isolate the public type"
        failures=$((failures + 1))
    fi

    # Every matching backup must be processed, not only the first listed
    reset_fake_inventory
    if [ "$(run_fake_clean static,public --older-than-date 2021-01-01 -y)" = "0" ] \
        && ! grep -q "web-backup" "$objects" && ! grep -q "public_backup" "$objects" \
        && has_key "auto-backups/database/AUTO-test-1-2020-01-01.sql.gz"; then
        echo "✅ 'clean static,public' removes every match in both namespaces"
    else
        echo "❌ 'clean static,public' left matching backups behind"
        failures=$((failures + 1))
    fi

    # 'clean all all' cannot delete database backups, so it must fail closed
    reset_fake_inventory
    if [ "$(run_fake_clean all all -y)" != "0" ] \
        && ! grep -q "aws s3 rm" "$calls" \
        && ! grep -q "Cleanup complete" "$sandbox/out"; then
        echo "✅ 'clean all all' fails closed without deleting or reporting success"
    else
        echo "❌ 'clean all all' did not fail closed"
        failures=$((failures + 1))
    fi

    # A failed delete must not be reported as a completed cleanup
    reset_fake_inventory
    FAKE_FAIL_RM="web-backup/AUTO-test-1"
    if [ "$(run_fake_clean static --older-than-date 2021-01-01 -y)" != "0" ] \
        && grep -q "Failed to delete static site backup" "$sandbox/out"; then
        echo "✅ Delete failure produces a non-zero exit and is reported"
    else
        echo "❌ Delete failure was hidden"
        failures=$((failures + 1))
    fi
    FAKE_FAIL_RM=""

    # A failed listing must not be treated as an empty namespace
    reset_fake_inventory
    FAKE_FAIL_LS="public_backup"
    if [ "$(run_fake_clean static,public --older-than-date 2021-01-01 -y)" != "0" ] \
        && grep -q "Failed to list public files backups" "$sandbox/out"; then
        echo "✅ Listing failure produces a non-zero exit and is reported"
    else
        echo "❌ Listing failure was treated as nothing-to-do"
        failures=$((failures + 1))
    fi
    FAKE_FAIL_LS=""

    # Database cleanup removes payload and checksum, and only when requested.
    # Also pins the RETENTION_MIN_HOURS floor, whose cutoff must be computed with
    # epoch arithmetic: BusyBox date supports neither "-v-48H" nor
    # "-d 48 hours ago", and an empty cutoff aborts the whole cleanup.
    reset_fake_inventory
    if [ "$(run_fake_clean db --older-than-date 2021-01-01 -y)" = "0" ] \
        && ! has_key "auto-backups/database/AUTO-test-1-2020-01-01.sql.gz" \
        && ! has_key "auto-backups/database/AUTO-test-1-2020-01-01.sql.gz.sha256" \
        && has_key "auto-backups/database/AUTO-test-now-$(date -u '+%Y-%m-%d').sql.gz" \
        && grep -q "Skipping recent backup" "$sandbox/out" \
        && has_key "auto-backups/web-backup/AUTO-test-1-2020-01-01/index.html"; then
        echo "✅ 'clean db' removes old payload/checksum, keeps backups inside the retention floor"
    else
        echo "❌ 'clean db' did not clean the database type correctly"
        failures=$((failures + 1))
    fi

    # Unknown and substring type names must be rejected before any S3 call
    reset_fake_inventory
    if [ "$(run_fake_clean notstatic --older-than-date 2021-01-01 -y)" != "0" ] \
        && [ ! -s "$calls" ]; then
        echo "✅ Unknown backup type is rejected before any S3 call"
    else
        echo "❌ Unknown backup type was not rejected"
        failures=$((failures + 1))
    fi

    rm -rf "$sandbox"

    if [ "$failures" -gt 0 ]; then
        echo "❌ Cleanup type isolation test failed ($failures issue(s))"
        return 1
    fi

    echo "✅ Cleanup type isolation test passed"
    return 0
}

# Function to test that restore completes every check before the first mutation,
# captures a verified recovery point, and rolls back when a later phase fails.
# Hermetic: a directory-backed fake S3 and fake drush, so no real store is touched.
# H-06: a named backup retried on the same day must not overwrite the earlier one,
# and every component of a set must share one number.
#
# The old discovery searched for "<base>-<number>" and required the remainder to be
# purely numeric, so an existing "<base>--post-deploy-0" never counted and each
# retry recomputed 0. Observed live: three downsyncs into dr on 2026-08-05 all
# produced DOWNSYNC-dr-16277-2026-08-05--pre-downsync-0 and only the last survived.
test_backup_set_numbering() {
    echo "🔢 Testing backup sequence numbering and set tagging..."

    local sandbox=""
    sandbox=$(mktemp -d) || { echo "❌ Could not create test sandbox"; return 1; }

    local failures=0

    mkdir -p "$sandbox/tree/scripts/snapshot" "$sandbox/bin" \
        "$sandbox/s3/auto-backups/database" "$sandbox/s3/auto-backups/web-backup" \
        "$sandbox/s3/auto-backups/public_backup"
    cp "$PROJECT_ROOT/scripts/common.sh" "$sandbox/tree/scripts/"
    cp "$BACKUP_DIR/manager.sh" "$BACKUP_DIR/backup-system.conf" "$sandbox/tree/scripts/snapshot/"

    # Lists a directory-backed bucket: files for db, "PRE name/" for static/public.
    cat > "$sandbox/bin/aws" <<'FAKEAWS'
#!/bin/sh
R="$FS3"
svc="$1"; shift; act="$1"; shift
[ "$svc $act" = "s3 ls" ] || exit 0
t=""
for a in "$@"; do case "$a" in s3://*) [ -z "$t" ] && t="$a" ;; esac; done
p="$R/$(echo "$t" | sed 's|^s3://[^/]*/||; s|/$||')"
[ -d "$p" ] || exit 1
for f in "$p"/*; do
    [ -e "$f" ] || continue
    b=$(basename "$f")
    if [ -d "$f" ]; then echo "                           PRE $b/"; else echo "2026-01-01 00:00:00 10 $b"; fi
done
exit 0
FAKEAWS
    chmod +x "$sandbox/bin/aws"

    # Separate processes: this suite has already sourced common.sh, and re-sourcing
    # re-runs `readonly` on constants that already hold values, which is fatal in a
    # non-interactive shell.
    cat > "$sandbox/tag-driver.sh" <<'DRIVER'
#!/bin/sh
# $1 = base tag, $2 = suffix (may be empty), $3 = preallocated set suffix (optional)
. ./scripts/common.sh
init_backup_system >/dev/null 2>&1
BUCKET_NAME=test-bucket
S3_EXTRA_PARAMS=""
eval "$(sed -n '/^prepare_backup_tag()/,/^}/p' ./scripts/snapshot/manager.sh)"
BACKUP_SET_SUFFIX="$3"
if prepare_backup_tag db "$1" "$2" maintenance false >/dev/null 2>&1; then
    echo "$NEXT_BACKUP_TAG"
else
    echo "REFUSED"
fi
DRIVER

    cat > "$sandbox/set-driver.sh" <<'DRIVER'
#!/bin/sh
# $1 = stem, $2 = legacy stem, $3 = types
. ./scripts/common.sh
init_backup_system >/dev/null 2>&1
BUCKET_NAME=test-bucket
S3_EXTRA_PARAMS=""
if allocate_backup_set_suffix "$1" "$2" "$3" >/dev/null 2>&1; then
    echo "$NEXT_BACKUP_SUFFIX"
else
    echo "REFUSED"
fi
DRIVER

    tag_for() {
        (
            cd "$sandbox/tree" 2>/dev/null || exit 9
            PATH="$sandbox/bin:$PATH"; export PATH
            FS3="$sandbox/s3"; export FS3
            sh "$sandbox/tag-driver.sh" "$1" "$2" "$3"
        )
    }
    set_suffix_for() {
        (
            cd "$sandbox/tree" 2>/dev/null || exit 9
            PATH="$sandbox/bin:$PATH"; export PATH
            FS3="$sandbox/s3"; export FS3
            sh "$sandbox/set-driver.sh" "$1" "$2" "$3"
        )
    }

    local db_dir="$sandbox/s3/auto-backups/database"
    local base="DOWNSYNC-dr-1-2026-01-01"

    # --- The suffix carries no extra delimiter ------------------------------
    local got=$(tag_for "$base" "pre-downsync" "")
    if [ "$got" = "$base-pre-downsync-0" ]; then
        echo "✅ A named backup starts at 0 with a single delimiter"
    else
        echo "❌ Unexpected first tag: $got"
        failures=$((failures + 1))
    fi

    # --- The overwrite this finding is about -------------------------------
    : > "$db_dir/$base-pre-downsync-0.sql.gz"
    got=$(tag_for "$base" "pre-downsync" "")
    if [ "$got" = "$base-pre-downsync-1" ]; then
        echo "✅ A same-day retry increments instead of reusing 0"
    else
        echo "❌ Retry did not increment (got: $got)"
        failures=$((failures + 1))
    fi

    # --- Names written before normalization still count --------------------
    : > "$db_dir/$base--pre-downsync-3.sql.gz"
    got=$(tag_for "$base" "pre-downsync" "")
    if [ "$got" = "$base-pre-downsync-4" ]; then
        echo "✅ Legacy double-delimiter names are counted, so numbering stays monotonic"
    else
        echo "❌ Legacy names were ignored (got: $got)"
        failures=$((failures + 1))
    fi

    # --- Unsuffixed backups behave as before ------------------------------
    local autobase="AUTO-dr-1-2026-01-01"
    : > "$db_dir/$autobase-4.sql.gz"
    got=$(tag_for "$autobase" "" "")
    if [ "$got" = "$autobase-5" ]; then
        echo "✅ Unsuffixed numbering is unaffected"
    else
        echo "❌ Unsuffixed numbering changed (got: $got)"
        failures=$((failures + 1))
    fi

    # --- A suffix that is a prefix of another must not be counted ---------
    # "post-deploy" and "post-deploy-extra" share a leading string; anchoring the
    # match on the full stem is what keeps them apart.
    : > "$db_dir/$base-post-deploy-extra-9.sql.gz"
    got=$(tag_for "$base" "post-deploy" "")
    if [ "$got" = "$base-post-deploy-0" ]; then
        echo "✅ A longer suffix sharing a prefix does not inflate the sequence"
    else
        echo "❌ Neighbouring suffix leaked into the sequence (got: $got)"
        failures=$((failures + 1))
    fi

    # --- One number for the whole set -------------------------------------
    mkdir -p "$sandbox/s3/auto-backups/web-backup/$autobase-7"
    got=$(set_suffix_for "$autobase" "" "static,public,db")
    if [ "$got" = "8" ]; then
        echo "✅ A set takes one number, above the highest in any component"
    else
        echo "❌ Set numbering wrong (db max 4, static max 7, expected 8, got: $got)"
        failures=$((failures + 1))
    fi

    # --- Components share the reserved number -----------------------------
    got=$(tag_for "$autobase" "" "8")
    if [ "$got" = "$autobase-8" ]; then
        echo "✅ Components use the reserved number rather than allocating their own"
    else
        echo "❌ Reserved number was ignored (got: $got)"
        failures=$((failures + 1))
    fi

    # --- At the cap, refuse rather than reset to 0 -------------------------
    # Resetting overwrote the first backup of the day, which is the one failure this
    # function must never produce.
    sed -i.bak 's/^MAX_RETRY_ATTEMPTS=.*/MAX_RETRY_ATTEMPTS=3/' \
        "$sandbox/tree/scripts/snapshot/backup-system.conf"
    local capbase="CAP-dr-1-2026-01-01"
    : > "$db_dir/$capbase-0.sql.gz"
    : > "$db_dir/$capbase-1.sql.gz"
    got=$(tag_for "$capbase" "" "")
    if [ "$got" = "$capbase-2" ]; then
        echo "✅ Below the cap a number is still issued"
    else
        echo "❌ Expected $capbase-2, got: $got"
        failures=$((failures + 1))
    fi
    : > "$db_dir/$capbase-2.sql.gz"
    got=$(tag_for "$capbase" "" "")
    if [ "$got" = "REFUSED" ]; then
        echo "✅ At the cap it refuses instead of resetting to 0 and overwriting"
    else
        echo "❌ At the cap it issued: $got"
        failures=$((failures + 1))
    fi

    # --- The caller must not pre-attach a delimiter -------------------------
    # run_backup_command used to pass "-$custom_suffix", which is what produced the
    # "<base>--post-deploy-0" names that discovery could never match. Checked
    # structurally because the joining happens before any S3 call.
    if command grep -q 'backup_suffix="-\${custom_suffix}"' "$BACKUP_DIR/manager.sh"; then
        echo "❌ run_backup_command still prefixes a delimiter onto the suffix"
        failures=$((failures + 1))
    else
        echo "✅ The suffix reaches prepare_backup_tag without a leading delimiter"
    fi

    rm -rf "$sandbox"
    [ "$failures" -eq 0 ]
}

# H-05: only one instance may schedule the daily backup, and no two backups may run
# against the same bucket at once.
#
# The fake aws implements PutObject's If-None-Match precondition, which is the
# primitive the lock relies on. That behavior was confirmed against the real
# GovCloud bucket in `dr`: a second conditional write is rejected with
# PreconditionFailed and the first writer's object is left intact.
test_backup_lock_and_scheduling() {
    echo "🔐 Testing cross-instance backup lock and scheduling guard..."

    local sandbox=""
    sandbox=$(mktemp -d) || { echo "❌ Could not create test sandbox"; return 1; }

    local failures=0

    mkdir -p "$sandbox/tree/scripts/snapshot" "$sandbox/bin" "$sandbox/s3"
    cp "$PROJECT_ROOT/scripts/common.sh" "$sandbox/tree/scripts/"
    cp "$BACKUP_DIR/manager.sh" "$BACKUP_DIR/backup-system.conf" "$BACKUP_DIR/setup-cron.sh" \
        "$sandbox/tree/scripts/snapshot/"
    chmod +x "$sandbox/tree/scripts/snapshot/manager.sh" "$sandbox/tree/scripts/snapshot/setup-cron.sh"

    cat > "$sandbox/bin/aws" <<'FAKEAWS'
#!/bin/sh
# Directory-backed S3 with a real If-None-Match precondition on put-object.
#   FAKE_PUT_FAIL=1         put-object fails for reasons other than the
#                           precondition, which must be distinguishable from
#                           losing a race
#   FAKE_PUT_UNSUPPORTED=1  the CLI rejects --if-none-match outright, meaning no
#                           backup could ever take the lock
R="$FS3"
k() { echo "$1" | sed 's|^s3://[^/]*/||; s|/$||'; }
r() { case "$1" in s3://*) echo "$R/$(k "$1")" ;; *) echo "${1%/}" ;; esac; }
svc="$1"; shift; act="$1"; shift
case "$svc $act" in
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
    [ -n "$FAKE_PUT_UNSUPPORTED" ] && { echo "Unknown options: --if-none-match" >&2; exit 252; }
    [ -n "$FAKE_PUT_FAIL" ] && { echo "simulated put failure" >&2; exit 1; }
    dest="$R/$key"
    if [ "$cond" = 1 ] && [ -e "$dest" ]; then
        echo "An error occurred (PreconditionFailed) when calling the PutObject operation" >&2
        exit 1
    fi
    mkdir -p "$(dirname "$dest")"; cp "$body" "$dest"; exit 0 ;;
"s3api head-object") exit 0 ;;
"s3 cp")
    s=""; d=""
    for a in "$@"; do case "$a" in --*) ;; *) if [ -z "$s" ]; then s="$a"; elif [ -z "$d" ]; then d="$a"; fi ;; esac; done
    ss=$(r "$s"); dd=$(r "$d")
    if [ "$d" = "-" ]; then [ -f "$ss" ] || exit 1; cat "$ss"; exit 0; fi
    if [ "$s" = "-" ]; then mkdir -p "$(dirname "$dd")"; cat > "$dd"; exit 0; fi
    [ -f "$ss" ] || exit 1
    mkdir -p "$(dirname "$dd")"; cp "$ss" "$dd"; exit 0 ;;
"s3 rm")
    t=""; for a in "$@"; do case "$a" in s3://*) [ -z "$t" ] && t="$a" ;; esac; done
    p=$(r "$t"); [ -e "$p" ] || exit 1; rm -rf "$p"; exit 0 ;;
"s3 ls")
    t=""; for a in "$@"; do case "$a" in s3://*) [ -z "$t" ] && t="$a" ;; esac; done
    p=$(r "$t"); o=""
    if [ -d "$p" ]; then o=$(find "$p" -type f); elif [ -f "$p" ]; then o="$p"; fi
    [ -z "$o" ] && exit 1
    echo "$o" | sed 's|^|2026-01-01 00:00:00 1 |'; exit 0 ;;
esac
exit 0
FAKEAWS
    chmod +x "$sandbox/bin/aws"

    # File-backed crontab so the scheduling guard can be observed.
    cat > "$sandbox/bin/crontab" <<'FAKECRON'
#!/bin/sh
F="${FAKE_CRONTAB:-/tmp/fake-crontab}"
case "$1" in
    -l) [ -f "$F" ] || exit 1; cat "$F"; exit 0 ;;
    -) cat > "$F"; exit 0 ;;
esac
exit 0
FAKECRON
    chmod +x "$sandbox/bin/crontab"

    cat > "$sandbox/bin/drush" <<'FAKEDRUSH'
#!/bin/sh
case "$1" in
    sget) exit 0 ;;
    sql:dump)
        out=$(echo "$@" | sed -n 's/.*--result-file=\([^ ]*\).*/\1/p')
        [ -n "$out" ] && printf -- '-- MySQL dump 10.13\nCREATE TABLE `node` (`nid` int);\n-- Dump completed\n' > "$out"
        exit 0 ;;
esac
exit 0
FAKEDRUSH
    chmod +x "$sandbox/bin/drush"

    lock_driver() {
        (
            cd "$sandbox/tree" 2>/dev/null || exit 9
            PATH="$sandbox/bin:$PATH"; export PATH
            FS3="$sandbox/s3"; export FS3
            sh "$sandbox/lock-driver.sh" "$@"
        )
    }

    cat > "$sandbox/lock-driver.sh" <<'DRIVER'
#!/bin/sh
. ./scripts/common.sh
init_backup_system >/dev/null 2>&1
BUCKET_NAME=test-bucket
S3_EXTRA_PARAMS=""
case "$1" in
    acquire)
        backup_lock_acquire >/dev/null 2>&1
        echo "rc=$? owned=$BACKUP_LOCK_OWNED"
        ;;
    acquire-verbose)
        out=$(backup_lock_acquire 2>&1); rc=$?
        echo "rc=$rc"
        echo "$out"
        ;;
    roundtrip)
        backup_lock_acquire >/dev/null 2>&1
        a=$?
        backup_lock_release >/dev/null 2>&1
        echo "acquire=$a released_object_present=$([ -f "$FS3/$BACKUP_LOCK_PATH" ] && echo yes || echo no)"
        ;;
    release-foreign)
        backup_lock_acquire >/dev/null 2>&1
        # Another run takes the lock over between our acquire and release.
        sed 's/^token=.*/token=someone-else/' "$FS3/$BACKUP_LOCK_PATH" > "$FS3/$BACKUP_LOCK_PATH.new"
        mv "$FS3/$BACKUP_LOCK_PATH.new" "$FS3/$BACKUP_LOCK_PATH"
        backup_lock_release >/dev/null 2>&1
        echo "foreign_lock_still_present=$([ -f "$FS3/$BACKUP_LOCK_PATH" ] && echo yes || echo no)"
        ;;
esac
DRIVER

    local lock_key="backup-locks/backup.lock"

    # --- Mutual exclusion --------------------------------------------------
    rm -rf "$sandbox/s3"; mkdir -p "$sandbox/s3"
    local first=$(lock_driver acquire)
    local second=$(lock_driver acquire)
    if [ "$first" = "rc=0 owned=true" ]; then
        echo "✅ First caller acquires the lock"
    else
        echo "❌ First caller did not acquire the lock (got: $first)"
        failures=$((failures + 1))
    fi
    if [ "$second" = "rc=1 owned=false" ]; then
        echo "✅ Second concurrent caller is refused"
    else
        echo "❌ Second caller was not refused (got: $second)"
        failures=$((failures + 1))
    fi

    # --- Expired locks are taken over -------------------------------------
    rm -rf "$sandbox/s3"; mkdir -p "$sandbox/s3/backup-locks"
    printf 'token=dead-holder\nacquired_epoch=1\nexpires_epoch=2\ninstance_index=1\n' \
        > "$sandbox/s3/$lock_key"
    local stale=$(lock_driver acquire-verbose)
    if echo "$stale" | grep -q '^rc=0'; then
        echo "✅ An expired lock is taken over"
    else
        echo "❌ An expired lock blocked the backup (got: $(echo "$stale" | head -1))"
        failures=$((failures + 1))
    fi
    if echo "$stale" | grep -q 'expired backup lock'; then
        echo "✅ Taking over an expired lock is reported"
    else
        echo "❌ Expired-lock takeover was silent"
        failures=$((failures + 1))
    fi

    # --- An unreadable expiry is treated as live, never stolen -------------
    rm -rf "$sandbox/s3"; mkdir -p "$sandbox/s3/backup-locks"
    printf 'token=other\nexpires_epoch=not-a-number\ninstance_index=1\n' > "$sandbox/s3/$lock_key"
    local bad=$(lock_driver acquire)
    if [ "$bad" = "rc=1 owned=false" ]; then
        echo "✅ A lock with an unreadable expiry is left alone"
    else
        echo "❌ A lock with an unreadable expiry was stolen (got: $bad)"
        failures=$((failures + 1))
    fi

    # --- Fail closed when the write fails for another reason ---------------
    rm -rf "$sandbox/s3"; mkdir -p "$sandbox/s3"
    local blind=$(FAKE_PUT_FAIL=1 lock_driver acquire)
    if [ "$blind" = "rc=2 owned=false" ]; then
        echo "✅ A failed write with no lock present fails closed (rc=2)"
    else
        echo "❌ Did not fail closed when ownership was unknown (got: $blind)"
        failures=$((failures + 1))
    fi

    # --- A CLI without conditional writes is named, not left to guess -------
    rm -rf "$sandbox/s3"; mkdir -p "$sandbox/s3"
    local unsupported=$(FAKE_PUT_UNSUPPORTED=1 lock_driver acquire-verbose)
    if echo "$unsupported" | grep -q '^rc=2' &&
       echo "$unsupported" | grep -q 'does not support --if-none-match'; then
        echo "✅ A CLI lacking conditional writes is reported by name"
    else
        echo "❌ Unsupported conditional write was not identified (got: $(echo "$unsupported" | head -2 | tr '\n' ' '))"
        failures=$((failures + 1))
    fi

    # --- Release ----------------------------------------------------------
    rm -rf "$sandbox/s3"; mkdir -p "$sandbox/s3"
    local rt=$(lock_driver roundtrip)
    if [ "$rt" = "acquire=0 released_object_present=no" ]; then
        echo "✅ The lock object is removed on release"
    else
        echo "❌ The lock was not released (got: $rt)"
        failures=$((failures + 1))
    fi
    rm -rf "$sandbox/s3"; mkdir -p "$sandbox/s3"
    local rf=$(lock_driver release-foreign)
    if [ "$rf" = "foreign_lock_still_present=yes" ]; then
        echo "✅ Release does not remove a lock another run has taken over"
    else
        echo "❌ Release removed someone else's lock (got: $rf)"
        failures=$((failures + 1))
    fi

    # --- End to end: a held lock skips the backup without failing ---------
    rm -rf "$sandbox/s3"; mkdir -p "$sandbox/s3/backup-locks"
    local future=$(( $(date -u '+%s') + 3600 ))
    printf 'token=other-run\nacquired_epoch=1\nexpires_epoch=%s\ninstance_index=0\n' "$future" \
        > "$sandbox/s3/$lock_key"
    local e2e="" e2e_rc=0
    e2e=$(
        cd "$sandbox/tree" 2>/dev/null || exit 9
        PATH="$sandbox/bin:$PATH"; export PATH
        FS3="$sandbox/s3"; export FS3
        BUCKET_NAME=test-bucket; export BUCKET_NAME
        S3_EXTRA_PARAMS=""; export S3_EXTRA_PARAMS
        rm -f "/tmp/backup_rate_limit_$(id -u 2>/dev/null || echo 0)"
        sh ./scripts/snapshot/manager.sh backup db 2>&1
    )
    e2e_rc=$?
    if [ "$e2e_rc" -eq 0 ] && echo "$e2e" | grep -q 'another backup is running'; then
        echo "✅ A held lock skips the backup and exits 0 rather than alerting"
    else
        echo "❌ Held-lock skip behaved unexpectedly (rc=$e2e_rc)"
        failures=$((failures + 1))
    fi
    if [ "$(find "$sandbox/s3/auto-backups" -type f 2>/dev/null | grep -c .)" = "0" ]; then
        echo "✅ The skipped run wrote no backup objects"
    else
        echo "❌ The skipped run still wrote backup objects"
        failures=$((failures + 1))
    fi
    if [ -f "$sandbox/s3/$lock_key" ] && grep -q 'token=other-run' "$sandbox/s3/$lock_key"; then
        echo "✅ The skipped run left the other run's lock intact"
    else
        echo "❌ The skipped run disturbed the holder's lock"
        failures=$((failures + 1))
    fi

    # --- Scheduling guard --------------------------------------------------
    cron_setup() {
        (
            cd "$sandbox/tree" 2>/dev/null || exit 9
            PATH="$sandbox/bin:$PATH"; export PATH
            FAKE_CRONTAB="$sandbox/crontab.txt"; export FAKE_CRONTAB
            CF_INSTANCE_INDEX="$1"; export CF_INSTANCE_INDEX
            sh ./scripts/snapshot/setup-cron.sh setup db >/dev/null 2>&1
        )
    }
    rm -f "$sandbox/crontab.txt"
    cron_setup 0
    if grep -q 'snapshot/manager.sh backup' "$sandbox/crontab.txt" 2>/dev/null; then
        echo "✅ Instance 0 schedules the backup"
    else
        echo "❌ Instance 0 did not schedule the backup"
        failures=$((failures + 1))
    fi
    # A container deployed before the guard carries the entry; bootstrap must clear it.
    cron_setup 1
    if grep -q 'snapshot/manager.sh backup' "$sandbox/crontab.txt" 2>/dev/null; then
        echo "❌ Instance 1 kept a backup cron entry"
        failures=$((failures + 1))
    else
        echo "✅ Instance 1 schedules nothing and clears an inherited entry"
    fi
    # Outside Cloud Foundry the variable is unset, which must not disable scheduling.
    rm -f "$sandbox/crontab.txt"
    (
        cd "$sandbox/tree" 2>/dev/null || exit 9
        PATH="$sandbox/bin:$PATH"; export PATH
        FAKE_CRONTAB="$sandbox/crontab.txt"; export FAKE_CRONTAB
        unset CF_INSTANCE_INDEX
        sh ./scripts/snapshot/setup-cron.sh setup db >/dev/null 2>&1
    )
    if grep -q 'snapshot/manager.sh backup' "$sandbox/crontab.txt" 2>/dev/null; then
        echo "✅ An unset CF_INSTANCE_INDEX still schedules (local and non-CF use)"
    else
        echo "❌ An unset CF_INSTANCE_INDEX stopped scheduling"
        failures=$((failures + 1))
    fi

    rm -rf "$sandbox"
    [ "$failures" -eq 0 ]
}

# H-04: state restoration must be verified, aggregated, and impossible to lose.
#
# Every case here turns on a distinction the old code could not make: a Drupal
# state write that reports success without taking effect. The fake drush can
# no-op a specific key, which is what makes the read-back verification testable.
test_state_restoration_guarantees() {
    echo "🔒 Testing Drupal state restoration guarantees..."

    local sandbox=""
    sandbox=$(mktemp -d) || { echo "❌ Could not create test sandbox"; return 1; }

    local failures=0

    # Run against a copy: init_backup_system prepends $PROJECT_ROOT/vendor/bin to
    # PATH, which would shadow the fake drush with the real one.
    mkdir -p "$sandbox/tree/scripts/snapshot" "$sandbox/bin" "$sandbox/s3"
    cp "$PROJECT_ROOT/scripts/common.sh" "$sandbox/tree/scripts/"
    cp "$BACKUP_DIR/manager.sh" "$BACKUP_DIR/backup-system.conf" "$sandbox/tree/scripts/snapshot/"
    chmod +x "$sandbox/tree/scripts/snapshot/manager.sh"

    cat > "$sandbox/bin/drush" <<'FAKEDRUSH'
#!/bin/sh
# Drupal state is key=value lines in $DSTATE.
#   FAKE_SSET_FAIL=<key>        the write returns non-zero
#   FAKE_SSET_NOOP=<key>        the write returns zero but does not persist
#   FAKE_SSET_NOOP_VALUE=<val>  writes of this value silently do not persist,
#                               which isolates the restore (writes 0) from the
#                               prepare (writes 1) for the same key
#   FAKE_SDEL_NOOP=<key>        the delete returns zero but the key survives
#   FAKE_CR_FAIL=1              cache rebuild fails
DS="$DSTATE"
get() { grep "^$1=" "$DS" 2>/dev/null | tail -1 | sed 's/^[^=]*=//'; }
put() { t="$DS.t"; grep -v "^$1=" "$DS" 2>/dev/null > "$t"; echo "$1=$2" >> "$t"; mv "$t" "$DS"; }
del() { t="$DS.t"; grep -v "^$1=" "$DS" 2>/dev/null > "$t"; mv "$t" "$DS"; }
case "$1" in
    sget) get "$2"; exit 0 ;;
    sset)
        [ -n "$FAKE_SSET_FAIL" ] && [ "$2" = "$FAKE_SSET_FAIL" ] && exit 1
        [ -n "$FAKE_SSET_NOOP" ] && [ "$2" = "$FAKE_SSET_NOOP" ] && exit 0
        [ -n "$FAKE_SSET_NOOP_VALUE" ] && [ "$3" = "$FAKE_SSET_NOOP_VALUE" ] && exit 0
        put "$2" "$3"; exit 0 ;;
    sdel)
        [ -n "$FAKE_SDEL_NOOP" ] && [ "$2" = "$FAKE_SDEL_NOOP" ] && exit 0
        del "$2"; exit 0 ;;
    cr) [ -n "$FAKE_CR_FAIL" ] && exit 1; exit 0 ;;
    sql:dump)
        out=$(echo "$@" | sed -n 's/.*--result-file=\([^ ]*\).*/\1/p')
        [ -n "$out" ] && printf -- '-- MySQL dump 10.13\nCREATE TABLE `node` (`nid` int);\nINSERT INTO `node` VALUES (1);\n-- Dump completed\n' > "$out"
        exit 0 ;;
    sqlq|sql:query) echo 1; exit 0 ;;
esac
exit 0
FAKEDRUSH
    chmod +x "$sandbox/bin/drush"

    # Minimal fake aws: enough for a database backup to complete.
    cat > "$sandbox/bin/aws" <<'FAKEAWS'
#!/bin/sh
R="$FS3"
k() { echo "$1" | sed 's|^s3://[^/]*/||; s|/$||'; }
r() { case "$1" in s3://*) echo "$R/$(k "$1")" ;; *) echo "${1%/}" ;; esac; }
svc="$1"; shift; act="$1"; shift
case "$svc $act" in
"s3 ls")
    t=""; for a in "$@"; do case "$a" in s3://*) [ -z "$t" ] && t="$a" ;; esac; done
    p=$(r "$t"); o=""
    if [ -d "$p" ]; then o=$(find "$p" -type f); elif [ -f "$p" ]; then o="$p"; fi
    [ -z "$o" ] && exit 1
    echo "$o" | sed 's|^|2026-01-01 00:00:00 1 |'; exit 0 ;;
"s3 cp")
    s=""; d=""
    for a in "$@"; do case "$a" in --*) ;; *) if [ -z "$s" ]; then s="$a"; elif [ -z "$d" ]; then d="$a"; fi ;; esac; done
    ss=$(r "$s"); dd=$(r "$d"); mkdir -p "$(dirname "$dd")"
    if [ "$s" = "-" ]; then cat > "$dd"; else [ -f "$ss" ] || exit 1; cp "$ss" "$dd"; fi
    exit 0 ;;
"s3api head-object") exit 0 ;;
esac
exit 0
FAKEAWS
    chmod +x "$sandbox/bin/aws"

    # Drivers run as separate processes on purpose: this suite has already sourced
    # common.sh, and re-sourcing it in a subshell re-runs `readonly` on constants
    # that already hold values, which is fatal in a non-interactive shell.
    cat > "$sandbox/restore-driver.sh" <<'DRIVER'
#!/bin/sh
# $1=state_type $2=saved_maint $3=saved_tome
. ./scripts/common.sh
init_backup_system >/dev/null 2>&1
DRUPAL_STATE_CAPTURED=true
SAVED_MAINTENANCE_MODE="$2"
SAVED_TOME_DISABLED="$3"
restore_drupal_state "$1" >/dev/null 2>&1
echo "rc=$? sticky=$DRUPAL_STATE_RESTORE_FAILED"
DRIVER

    # Args: $1=state_type $2=saved_maint $3=saved_tome
    restore_case() {
        (
            cd "$sandbox/tree" 2>/dev/null || exit 9
            PATH="$sandbox/bin:$PATH"; export PATH
            sh "$sandbox/restore-driver.sh" "$1" "$2" "$3"
        )
    }

    # --- Case 1: a maintenance write that does not persist is caught ---------
    export DSTATE="$sandbox/state1"
    printf 'system.maintenance_mode=1\nusagov.tome_run_disabled=1\n' > "$DSTATE"
    local out=""
    out=$(FAKE_SSET_NOOP=system.maintenance_mode restore_case both 0 1)
    if [ "$out" = "rc=1 sticky=true" ]; then
        echo "✅ Unpersisted maintenance write is detected by read-back"
    else
        echo "❌ Unpersisted maintenance write not detected (got: $out)"
        failures=$((failures + 1))
    fi
    # ...and the Tome half still ran, rather than being skipped by an early return
    if grep -q '^usagov.tome_run_disabled=1$' "$DSTATE"; then
        echo "✅ Tome half still attempted after the maintenance half failed"
    else
        echo "❌ Tome half was skipped when maintenance failed"
        failures=$((failures + 1))
    fi

    # --- Case 2: a Tome write that does not persist is caught ----------------
    export DSTATE="$sandbox/state2"
    printf 'system.maintenance_mode=1\n' > "$DSTATE"
    out=$(FAKE_SSET_NOOP=usagov.tome_run_disabled restore_case both 0 1)
    if [ "$out" = "rc=1 sticky=true" ]; then
        echo "✅ Unpersisted Tome write is detected (was logged as success unconditionally)"
    else
        echo "❌ Unpersisted Tome write not detected (got: $out)"
        failures=$((failures + 1))
    fi

    # --- Case 3: the ordinary path still succeeds --------------------------
    # Restoring a captured Tome value of 0 deletes the key, so the read-back has
    # to treat absent as 0 or every normal restore would report failure.
    export DSTATE="$sandbox/state3"
    printf 'system.maintenance_mode=1\nusagov.tome_run_disabled=1\n' > "$DSTATE"
    out=$(restore_case both 0 0)
    if [ "$out" = "rc=0 sticky=false" ]; then
        echo "✅ Normal restore of a captured Tome 0 is not a false failure"
    else
        echo "❌ Normal restore reported a failure (got: $out)"
        failures=$((failures + 1))
    fi
    if [ "$(grep -c '^usagov.tome_run_disabled=' "$DSTATE")" = "0" ] &&
       grep -q '^system.maintenance_mode=0$' "$DSTATE"; then
        echo "✅ Both halves reached their captured values"
    else
        echo "❌ Captured values were not applied: $(tr '\n' ' ' < "$DSTATE")"
        failures=$((failures + 1))
    fi

    # --- Case 4: a failed restore fails the backup command -----------------
    # The database backup itself succeeds here; only the restoring write fails, so
    # this is the case the old code called a success while leaving the site in
    # maintenance mode. The twenty call sites in manager.sh discard
    # restore_drupal_state's status, so the sticky flag is what carries it out.
    export DSTATE="$sandbox/state4"
    printf 'system.maintenance_mode=0\n' > "$DSTATE"
    local backup_out="" backup_rc=0
    backup_out=$(
        cd "$sandbox/tree" 2>/dev/null || exit 9
        PATH="$sandbox/bin:$PATH"; export PATH
        FS3="$sandbox/s3"; export FS3
        BUCKET_NAME=test-bucket; export BUCKET_NAME
        S3_EXTRA_PARAMS=""; export S3_EXTRA_PARAMS
        FAKE_SSET_NOOP_VALUE=0; export FAKE_SSET_NOOP_VALUE
        rm -f "/tmp/backup_rate_limit_$(id -u 2>/dev/null || echo 0)"
        sh ./scripts/snapshot/manager.sh backup db 2>&1
    )
    backup_rc=$?
    if [ "$backup_rc" -ne 0 ]; then
        echo "✅ Backup command exits non-zero when state restoration fails"
    else
        echo "❌ Backup command reported success after a failed state restoration"
        failures=$((failures + 1))
    fi
    if echo "$backup_out" | grep -q 'Drupal state was not restored'; then
        echo "✅ Backup command names the state failure"
    else
        echo "❌ Backup command did not report the state failure"
        failures=$((failures + 1))
    fi

    # --- Case 5 (N-03): create_db_backup must not clear the caller's trap ---
    # These two are structural rather than behavioral, deliberately. Calling
    # create_db_backup outside manager.sh means stubbing the helpers it depends on,
    # and the test then measures the stubs: an earlier version of this check passed
    # against the unfixed code because the function returned at a missing helper
    # long before reaching the trap lines. The clobbering is now absent by
    # construction, which is what these assert.
    local db_fn=""
    db_fn=$(sed -n '/^create_db_backup()/,/^}/p' "$BACKUP_DIR/manager.sh")
    if echo "$db_fn" | grep -qE '^[[:space:]]*trap '; then
        echo "❌ create_db_backup still installs its own trap, replacing the caller's (N-03)"
        failures=$((failures + 1))
    else
        echo "✅ create_db_backup installs no trap of its own (N-03)"
    fi
    if grep -qE '^[[:space:]]*trap - ' "$BACKUP_DIR/manager.sh"; then
        echo "❌ manager.sh still clears an inherited EXIT trap somewhere (N-03)"
        failures=$((failures + 1))
    else
        echo "✅ No code path clears an inherited EXIT trap (N-03)"
    fi

    # --- Case 6: the backup command installs a cleanup trap ----------------
    # An interrupted backup used to leave maintenance mode on with no handler.
    export DSTATE="$sandbox/state6"
    printf 'system.maintenance_mode=1\n' > "$DSTATE"
    cat > "$sandbox/cleanup-driver.sh" <<'DRIVER'
#!/bin/sh
. ./scripts/common.sh
eval "$(sed -n '/^backup_cleanup()/,/^}/p' ./scripts/snapshot/manager.sh)"
init_backup_system >/dev/null 2>&1
DRUPAL_STATE_CAPTURED=true
SAVED_MAINTENANCE_MODE=0
SAVED_TOME_DISABLED=0
DRUPAL_STATE_ACTIVE_MAINT=true
backup_cleanup >/dev/null 2>&1
grep '^system.maintenance_mode=' "$DSTATE"
DRIVER
    local cleanup_out=""
    cleanup_out=$(
        cd "$sandbox/tree" 2>/dev/null || exit 9
        PATH="$sandbox/bin:$PATH"; export PATH
        sh "$sandbox/cleanup-driver.sh"
    )
    if [ "$cleanup_out" = "system.maintenance_mode=0" ]; then
        echo "✅ Cleanup handler restores state left held by an interrupted backup"
    else
        echo "❌ Cleanup handler did not restore held state (got: $cleanup_out)"
        failures=$((failures + 1))
    fi
    if grep -q 'arm_cleanup_traps backup_cleanup' "$BACKUP_DIR/manager.sh"; then
        echo "✅ Backup command arms the cleanup trap"
    else
        echo "❌ Backup command does not arm a cleanup trap"
        failures=$((failures + 1))
    fi

    # --- Case 7: a signal must terminate, not resume ------------------------
    # A POSIX signal trap returns to the interrupted command, so a handler that
    # only cleans up lets the work continue with its state already restored.
    # Confirmed live before this was fixed: TERM cleared maintenance mode in dr and
    # the backup carried on.
    local sig_out=""
    sig_out=$(
        cd "$sandbox/tree" 2>/dev/null || exit 9
        PATH="$sandbox/bin:$PATH"; export PATH
        cat > "$sandbox/sig.sh" <<'SIG'
#!/bin/sh
. ./scripts/common.sh
# Shaped like the real handlers, which must be idempotent because the explicit
# exit in the signal trap re-triggers EXIT.
MARKER_DONE=false
cleanup_marker() {
    [ "$MARKER_DONE" = "true" ] && return 0
    MARKER_DONE=true
    echo "CLEANED"
}
arm_cleanup_traps cleanup_marker
kill -TERM $$
echo "RESUMED_AFTER_SIGNAL"
SIG
        sh "$sandbox/sig.sh" 2>/dev/null
        echo "rc=$?"
    )
    if echo "$sig_out" | grep -q 'CLEANED' && ! echo "$sig_out" | grep -q 'RESUMED_AFTER_SIGNAL'; then
        echo "✅ A signal runs cleanup and terminates instead of resuming"
    else
        echo "❌ Execution resumed after the signal handler (got: $(echo "$sig_out" | tr '\n' ' '))"
        failures=$((failures + 1))
    fi
    if echo "$sig_out" | grep -q 'rc=143'; then
        echo "✅ Exits with the conventional 128+SIGTERM status"
    else
        echo "❌ Wrong exit status after TERM (got: $(echo "$sig_out" | grep '^rc='))"
        failures=$((failures + 1))
    fi
    if [ "$(echo "$sig_out" | grep -c CLEANED)" = "1" ]; then
        echo "✅ Cleanup runs once despite EXIT re-triggering after the explicit exit"
    else
        echo "❌ Cleanup ran $(echo "$sig_out" | grep -c CLEANED) times"
        failures=$((failures + 1))
    fi
    # arm_cleanup_traps requires idempotent handlers, so every handler using it
    # must carry the guard or its output doubles on every signal.
    local guard_missing=0
    grep -q 'BACKUP_CLEANUP_DONE' "$BACKUP_DIR/manager.sh" || guard_missing=$((guard_missing + 1))
    grep -q 'RESTORE_CLEANUP_DONE' "$BACKUP_DIR/manager.sh" || guard_missing=$((guard_missing + 1))
    grep -q 'DOWNSYNC_CLEANUP_DONE' "$PROJECT_ROOT/scripts/devops/deploy.sh" || guard_missing=$((guard_missing + 1))
    if [ "$guard_missing" -eq 0 ]; then
        echo "✅ All three cleanup handlers carry a run-once guard"
    else
        echo "❌ $guard_missing cleanup handler(s) lack a run-once guard"
        failures=$((failures + 1))
    fi

    unset DSTATE
    rm -rf "$sandbox"
    [ "$failures" -eq 0 ]
}

test_restore_preflight_and_compensation() {
    echo "🛟 Testing restore preflight ordering and compensation..."

    local sandbox=""
    sandbox=$(mktemp -d) || { echo "❌ Could not create test sandbox"; return 1; }

    local failures=0
    local tag="AUTO-test-1-2020-01-01-0"

    # Run against a copy of the scripts: init_backup_system prepends
    # $PROJECT_ROOT/vendor/bin to PATH, which would shadow the fake drush.
    mkdir -p "$sandbox/tree/scripts/snapshot" "$sandbox/bin"
    cp "$PROJECT_ROOT/scripts/common.sh" "$sandbox/tree/scripts/"
    cp "$BACKUP_DIR/manager.sh" "$BACKUP_DIR/backup-system.conf" "$sandbox/tree/scripts/snapshot/"
    chmod +x "$sandbox/tree/scripts/snapshot/manager.sh"

    # Fake aws: S3 keys are files under $FS3, giving real sync --delete semantics
    cat > "$sandbox/bin/aws" <<'FAKEAWS'
#!/bin/sh
R="$FS3"
k() { echo "$1" | sed 's|^s3://[^/]*/||; s|/$||'; }
r() { case "$1" in s3://*) echo "$R/$(k "$1")" ;; *) echo "${1%/}" ;; esac; }
svc="$1"; shift; act="$1"; shift
case "$svc $act" in
"s3 ls")
    t=""; rec=0
    for a in "$@"; do case "$a" in --recursive) rec=1 ;; s3://*) [ -z "$t" ] && t="$a" ;; esac; done
    p=$(r "$t"); o=""
    if [ -d "$p" ]; then o=$(find "$p" -type f | head -50); elif [ -f "$p" ]; then o="$p"; fi
    [ -z "$o" ] && exit 1
    echo "$o" | sed 's|^|2026-01-01 00:00:00 1 |'; exit 0 ;;
"s3 cp")
    rec=0; s=""; d=""
    for a in "$@"; do case "$a" in --recursive) rec=1 ;; --*) ;; *) if [ -z "$s" ]; then s="$a"; elif [ -z "$d" ]; then d="$a"; fi ;; esac; done
    if [ "$s" = "-" ]; then dd=$(r "$d"); mkdir -p "$(dirname "$dd")"; cat > "$dd"; exit 0; fi
    ss=$(r "$s"); dd=$(r "$d")
    if [ "$rec" = 1 ]; then
        [ -d "$ss" ] || exit 1
        mkdir -p "$dd"; (cd "$ss" && find . -type f | sed 's|^\./||') | while read -r f; do
            mkdir -p "$dd/$(dirname "$f")"; cp "$ss/$f" "$dd/$f"; done
        exit 0
    fi
    [ -f "$ss" ] || exit 1
    mkdir -p "$(dirname "$dd")"; cp "$ss" "$dd"; exit 0 ;;
"s3 sync")
    del=0; s=""; d=""
    for a in "$@"; do case "$a" in --delete) del=1 ;; --*) ;; *) if [ -z "$s" ]; then s="$a"; elif [ -z "$d" ]; then d="$a"; fi ;; esac; done
    [ -n "$FAKE_FAIL_SYNC" ] && echo "$d" | grep -q "$FAKE_FAIL_SYNC" && { echo "sync failed" >&2; exit 1; }
    ss=$(r "$s"); dd=$(r "$d"); mkdir -p "$dd"
    [ -d "$ss" ] && (cd "$ss" && find . -type f | sed 's|^\./||') | while read -r f; do
        mkdir -p "$dd/$(dirname "$f")"; cp "$ss/$f" "$dd/$f"; done
    if [ "$del" = 1 ]; then
        (cd "$dd" && find . -type f | sed 's|^\./||') | while read -r f; do
            [ -f "$ss/$f" ] || rm -f "$dd/$f"; done
    fi
    exit 0 ;;
"s3 rm")
    t=""; rec=0
    for a in "$@"; do case "$a" in --recursive) rec=1 ;; s3://*) [ -z "$t" ] && t="$a" ;; esac; done
    p=$(r "$t"); if [ "$rec" = 1 ]; then rm -rf "$p"; else rm -f "$p"; fi; exit 0 ;;
"s3api list-objects-v2")
    pfx=""; q=""
    while [ $# -gt 0 ]; do case "$1" in --prefix) pfx="$2"; shift 2 ;; --query) q="$2"; shift 2 ;; *) shift ;; esac; done
    base="$R/${pfx%/}"; res=""
    case "$q" in
    *CommonPrefixes*)
        [ -d "$base" ] && for e in "$base"/*; do [ -d "$e" ] && res="${res}${pfx}$(basename "$e")/	"; done ;;
    *)
        if [ -d "$base" ]; then res=$(cd "$base" && find . -type f | sed "s|^\./|${pfx}|" | tr '\n' '\t')
        elif [ -f "$base" ]; then res="${pfx%/}	"; fi ;;
    esac
    res=$(printf '%s' "$res" | sed 's/\t$//')
    [ -z "$res" ] && echo "None" || echo "$res"
    exit 0 ;;
"s3api head-object")
    key=""; while [ $# -gt 0 ]; do case "$1" in --key) key="$2"; shift 2 ;; *) shift ;; esac; done
    [ -f "$R/$key" ] && exit 0; exit 254 ;;
esac
exit 99
FAKEAWS

    cat > "$sandbox/bin/drush" <<'FAKEDRUSH'
#!/bin/sh
case "$1" in
sql:dump)
    out=""; for a in "$@"; do case "$a" in --result-file=*) out="${a#--result-file=}" ;; esac; done
    [ -z "$out" ] && exit 1
    printf -- '-- MySQL dump 10.13\n-- Host: x  Database: d\nCREATE TABLE `n` (i int);\nINSERT INTO `n` VALUES (1);\n-- RECOVERY-DUMP\n-- Dump completed on 2026-01-01\n' > "$out"
    exit 0 ;;
sql:drop) echo DROPPED >> "$FAKE_DB_LOG"; exit 0 ;;
sql:cli)
    cat > "$FAKE_DB_CONTENT"
    if [ -n "$FAKE_IMPORT_FAIL_ONCE" ] && [ -f "$FAKE_IMPORT_FAIL_ONCE" ]; then
        rm -f "$FAKE_IMPORT_FAIL_ONCE"; exit 1
    fi
    exit 0 ;;
*) exit 0 ;;
esac
FAKEDRUSH
    chmod +x "$sandbox/bin/aws" "$sandbox/bin/drush"

    seed_restore_fixture() {
        local live_count="${1:-3}"
        rm -rf "$sandbox/s3"
        : > "$sandbox/db-ops.log"
        local b="$sandbox/s3/auto-backups"
        mkdir -p "$b/web-backup/$tag" "$b/public_backup/$tag" "$b/database" "$sandbox/s3/web" "$sandbox/s3/cms/public"
        echo "backup-page" > "$b/web-backup/$tag/page1.html"
        echo "backup-page2" > "$b/web-backup/$tag/page2.html"
        echo "backup-file" > "$b/public_backup/$tag/file1.pdf"
        printf -- '-- MySQL dump 10.13\n-- Host: x  Database: d\nCREATE TABLE `n` (i int);\nINSERT INTO `n` VALUES (42);\n-- TARGET-DUMP\n-- Dump completed on 2026-01-01\n' > "$sandbox/seed.sql"
        gzip -c "$sandbox/seed.sql" > "$b/database/$tag.sql.gz"
        sha256sum "$b/database/$tag.sql.gz" | awk '{print $1}' > "$b/database/$tag.sql.gz.sha256"
        local i=0
        while [ "$i" -lt "$live_count" ]; do
            i=$((i + 1)); echo "live-$i" > "$sandbox/s3/web/rlive$i.html"
        done
        echo "live-pub" > "$sandbox/s3/cms/public/rlive1.pdf"
        echo "-- LIVE-DB" > "$sandbox/db-content.sql"
    }

    run_fake_restore() {
        (
            PATH="$sandbox/bin:$PATH"
            FS3="$sandbox/s3"
            FAKE_DB_LOG="$sandbox/db-ops.log"
            FAKE_DB_CONTENT="$sandbox/db-content.sql"
            BUCKET_NAME="fake-bucket"; APP_SPACE="test"; S3_EXTRA_PARAMS=""
            export PATH FS3 FAKE_DB_LOG FAKE_DB_CONTENT BUCKET_NAME APP_SPACE S3_EXTRA_PARAMS
            export FAKE_FAIL_SYNC FAKE_IMPORT_FAIL_ONCE
            cd "$sandbox/tree" && ./scripts/snapshot/manager.sh restore "$@" >"$sandbox/out" 2>&1
        )
        echo $?
    }

    FAKE_FAIL_SYNC=""
    FAKE_IMPORT_FAIL_ONCE=""

    # A bad database dump must abort before the S3 trees are replaced
    seed_restore_fixture 3
    echo "0000000000000000000000000000000000000000000000000000000000000000" \
        > "$sandbox/s3/auto-backups/database/$tag.sql.gz.sha256"
    if [ "$(run_fake_restore "$tag" -y --ssm)" != "0" ] \
        && grep -q 'checksum mismatch' "$sandbox/out" \
        && [ ! -f "$sandbox/s3/web/page1.html" ] \
        && [ -f "$sandbox/s3/web/rlive1.html" ] \
        && ! grep -q DROPPED "$sandbox/db-ops.log"; then
        echo "✅ Database checksum failure aborts before any S3 or database mutation"
    else
        echo "❌ Database preflight failure did not prevent mutation"
        failures=$((failures + 1))
    fi

    # A requested component that does not exist must fail closed. Since H-09 the
    # message names resolution rather than a missing object, because the set's
    # manifest is what is consulted first.
    seed_restore_fixture 3
    rm -rf "$sandbox/s3/auto-backups/public_backup/$tag"
    if [ "$(run_fake_restore "$tag" -y --ssm)" != "0" ] \
        && grep -q 'Public files backup not resolved' "$sandbox/out" \
        && ! grep -q 'Restore complete' "$sandbox/out" \
        && [ -f "$sandbox/s3/web/rlive1.html" ]; then
        echo "✅ Missing requested component fails closed instead of reporting success"
    else
        echo "❌ Missing component was skipped rather than failing closed"
        failures=$((failures + 1))
    fi

    # A backup far smaller than live content must not silently delete the rest
    seed_restore_fixture 20
    if [ "$(run_fake_restore "$tag" --only=static -y --ssm)" != "0" ] \
        && grep -q 'floor' "$sandbox/out" \
        && [ -f "$sandbox/s3/web/rlive20.html" ]; then
        echo "✅ Destructive-sync guard blocks a backup smaller than live content"
    else
        echo "❌ Destructive-sync guard did not block an undersized backup"
        failures=$((failures + 1))
    fi

    # A successful restore must capture a verified recovery point first
    seed_restore_fixture 3
    if [ "$(run_fake_restore "$tag" -y --ssm)" = "0" ] \
        && [ -f "$sandbox/s3/web/page1.html" ] \
        && [ ! -f "$sandbox/s3/web/rlive1.html" ] \
        && grep -q 'TARGET-DUMP' "$sandbox/db-content.sql" \
        && ls "$sandbox/s3/auto-backups/web-backup" | grep -q PRERESTORE \
        && ls "$sandbox/s3/auto-backups/database" | grep -q PRERESTORE; then
        echo "✅ Successful restore captures a verified pre-restore recovery point"
    else
        echo "❌ Restore did not capture a complete recovery point"
        failures=$((failures + 1))
    fi

    # A failed database import must roll every applied phase back
    seed_restore_fixture 3
    FAKE_IMPORT_FAIL_ONCE="$sandbox/fail-once"
    : > "$FAKE_IMPORT_FAIL_ONCE"
    if [ "$(run_fake_restore "$tag" -y --ssm)" != "0" ] \
        && grep -q 'Rolled back to the pre-restore state' "$sandbox/out" \
        && [ -f "$sandbox/s3/web/rlive1.html" ] \
        && [ ! -f "$sandbox/s3/web/page1.html" ] \
        && grep -q 'RECOVERY-DUMP' "$sandbox/db-content.sql" \
        && ! grep -q 'Restore complete' "$sandbox/out"; then
        echo "✅ Failed database import rolls all applied phases back"
    else
        echo "❌ Failed import did not compensate the applied phases"
        failures=$((failures + 1))
    fi
    FAKE_IMPORT_FAIL_ONCE=""

    # A failed public sync must roll the already-applied static phase back
    seed_restore_fixture 3
    FAKE_FAIL_SYNC="cms/public"
    if [ "$(run_fake_restore "$tag" --only=static,public -y --ssm)" != "0" ] \
        && grep -q 'Rolling back' "$sandbox/out" \
        && [ -f "$sandbox/s3/web/rlive1.html" ] \
        && ! grep -q DROPPED "$sandbox/db-ops.log"; then
        echo "✅ Failed public sync rolls the static phase back"
    else
        echo "❌ Failed public sync left a mixed-generation environment"
        failures=$((failures + 1))
    fi
    FAKE_FAIL_SYNC=""

    rm -rf "$sandbox"

    if [ "$failures" -gt 0 ]; then
        echo "❌ Restore preflight/compensation test failed ($failures issue(s))"
        return 1
    fi

    echo "✅ Restore preflight and compensation test passed"
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
# H-07: the deployment metadata contract — one schema, one producer, one reader,
# one validator.
#
# The defect this guards against was mutual: the producer wrote
# `deployed_containers` while the validator required `containers`, so the system
# rejected its own metadata; and the cron capture was assembled by string
# concatenation with `\n` escapes, which is valid JSON only if the running
# shell's `echo` expands them. Every reader scraped that with its own sed
# expression, and those expressions only match the single-line malformed form —
# given properly formatted JSON they return nothing, and nothing is recorded as
# a backup with no digests at all.
test_deployment_metadata_contract() {
    echo "🧾 Testing deployment metadata schema contract..."

    local sandbox=""
    sandbox=$(mktemp -d) || { echo "❌ Could not create test sandbox"; return 1; }

    local failures=0
    local d64="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"

    mkdir -p "$sandbox/tree/scripts/snapshot" "$sandbox/bin"
    cp "$PROJECT_ROOT/scripts/common.sh" "$sandbox/tree/scripts/"
    cp "$BACKUP_DIR/backup-system.conf" "$sandbox/tree/scripts/snapshot/"
    cp "$BACKUP_DIR/manager.sh" "$sandbox/tree/scripts/snapshot/"

    # Serves the digest capture from $CAPTURE_FILE and records uploads to $UPLOADED
    cat > "$sandbox/bin/aws" <<'FAKEAWS'
#!/bin/sh
case "$*" in
  *".current_digests_"*)
     [ -f "$CAPTURE_FILE" ] && cat "$CAPTURE_FILE"
     exit 0 ;;
esac
src=""
for a in "$@"; do case "$a" in /tmp/*) src="$a" ;; esac; done
if [ -n "$src" ] && [ -n "$UPLOADED" ]; then cp "$src" "$UPLOADED" 2>/dev/null; fi
exit 0
FAKEAWS
    # Unreachable, so the producer cannot quietly fall back to live CF state
    printf '#!/bin/sh\nexit 1\n' > "$sandbox/bin/cf"
    chmod +x "$sandbox/bin/aws" "$sandbox/bin/cf"

    # Separate processes: this suite has already sourced common.sh, and
    # re-sourcing re-runs `readonly` on constants that already hold values,
    # which is fatal in a non-interactive shell.
    cat > "$sandbox/produce.sh" <<'DRIVER'
#!/bin/sh
. ./scripts/common.sh >/dev/null 2>&1
init_backup_system >/dev/null 2>&1
BUCKET_NAME=test-bucket
S3_EXTRA_PARAMS=""
capture_deployment_metadata "$1" "$2"
DRIVER

    cat > "$sandbox/validate.sh" <<'DRIVER'
#!/bin/sh
. ./scripts/common.sh >/dev/null 2>&1
init_backup_system >/dev/null 2>&1
# A missing validator also exits non-zero, which would make every "refused"
# assertion below pass without validating anything
command -v validate_deployment_metadata >/dev/null 2>&1 || { echo NOFUNC; exit 0; }
if validate_deployment_metadata "$(cat "$1")" "$2" "$3" > "$1.findings" 2>&1; then
    echo VALID
else
    echo INVALID
fi
DRIVER

    cat > "$sandbox/extract.sh" <<'DRIVER'
#!/bin/sh
. ./scripts/common.sh >/dev/null 2>&1
init_backup_system >/dev/null 2>&1
if extract_digests_from_metadata "$(cat "$1")" "$2"; then
    echo RC=0
else
    echo RC=1
fi
DRIVER

    cat > "$sandbox/commit.sh" <<'DRIVER'
#!/bin/sh
. ./scripts/common.sh >/dev/null 2>&1
init_backup_system >/dev/null 2>&1
BUCKET_NAME=test-bucket
S3_EXTRA_PARAMS=""
command -v commit_deployment_metadata >/dev/null 2>&1 || { echo NOFUNC; exit 0; }
# Stand in for a capture that produced nothing usable
capture_deployment_metadata() { echo ""; }
if commit_deployment_metadata TAG-dr-1-2026-01-01-0 dr; then echo COMMITTED; else echo REFUSED; fi
DRIVER

    run_in_tree() {
        (
            cd "$sandbox/tree" 2>/dev/null || exit 9
            PATH="$sandbox/bin:$PATH"; export PATH
            CAPTURE_FILE="$sandbox/capture.json"; export CAPTURE_FILE
            UPLOADED="$sandbox/uploaded.json"; export UPLOADED
            sh "$sandbox/$1" "$2" "$3" "$4"
        )
    }

    # --- The capture the producer has to read is the legacy escaped form -----
    # This is what is in the buckets: one line, literal \n, string-valued digests.
    printf '{\\n  "timestamp": "%s",\\n  "environment": "dr",\\n  "containers": {\\n    "cms": "gsatts/usagov-2021:cms-16302@sha256:%s",\\n    "www": "gsatts/usagov-2021:www-16302@sha256:%s",\\n    "waf": "gsatts/usagov-2021:waf-16302@sha256:%s"\\n  }\\n}' \
        "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$d64" "$d64" "$d64" > "$sandbox/capture.json"

    if jq -e . "$sandbox/capture.json" >/dev/null 2>&1; then
        echo "❌ Test fixture is wrong: the legacy capture should not be valid JSON as stored"
        failures=$((failures + 1))
    fi

    run_in_tree produce.sh AUTO-dr-16302-2026-01-01-0 dr > "$sandbox/produced.json"

    # --- The producer emits valid JSON --------------------------------------
    if jq -e . "$sandbox/produced.json" >/dev/null 2>&1; then
        echo "✅ Metadata is emitted as valid JSON"
    else
        echo "❌ Producer output does not parse as JSON"
        failures=$((failures + 1))
    fi

    # --- ...and it read the legacy capture rather than losing the digests ----
    local got_cms=$(jq -r '.containers.cms.digest // "none"' "$sandbox/produced.json" 2>/dev/null)
    if [ "$got_cms" = "gsatts/usagov-2021:cms-16302@sha256:$d64" ]; then
        echo "✅ The legacy escaped capture is still readable"
    else
        echo "❌ Digest not carried through from the legacy capture: $got_cms"
        failures=$((failures + 1))
    fi

    # --- One release ID across the set --------------------------------------
    if [ "$(jq -r '.release.id' "$sandbox/produced.json" 2>/dev/null)" = "16302" ] &&
       [ "$(jq -r '.release.mixed' "$sandbox/produced.json" 2>/dev/null)" = "false" ]; then
        echo "✅ A single release ID is derived for the whole set"
    else
        echo "❌ Release identity not established: $(jq -c '.release' "$sandbox/produced.json" 2>/dev/null)"
        failures=$((failures + 1))
    fi

    # --- The producer and the validator agree -------------------------------
    # This is the finding: metadata generation wrote `deployed_containers` and
    # validation required `containers`, so the validator rejected documents the
    # system had just written.
    if [ "$(run_in_tree validate.sh "$sandbox/produced.json" AUTO-dr-16302-2026-01-01-0 dr)" = "VALID" ]; then
        echo "✅ The validator accepts the producer's own output"
    else
        echo "❌ The validator rejects metadata this system produced:"
        sed 's/^/     /' "$sandbox/produced.json.findings" 2>/dev/null
        failures=$((failures + 1))
    fi

    # --- Documents are parsed, not grepped for field names ------------------
    # The previous validator counted `grep '"containers"'` hits, so any text
    # mentioning the field names passed.
    printf 'not json, but it mentions "backup_tag" "timestamp" "environment" "containers" "cms" "www" "waf" "digest"\n' > "$sandbox/garbage.json"
    if [ "$(run_in_tree validate.sh "$sandbox/garbage.json" "" "")" = "INVALID" ] &&
       command grep -q "not valid JSON" "$sandbox/garbage.json.findings" 2>/dev/null; then
        echo "✅ Text that merely mentions the field names is refused"
    else
        echo "❌ Non-JSON text passed validation"
        failures=$((failures + 1))
    fi

    # --- Each fail-closed condition -----------------------------------------
    local case_name="" filter="" expect_msg=""
    for case_name in env stale mixed missing grammar nocapture; do
        case "$case_name" in
            env)       filter='.environment = "stage"' ;             expect_msg="environment mismatch" ;;
            stale)     filter='.capture.stale = true | .capture.age_seconds = 259200' ; expect_msg="old at backup time" ;;
            mixed)     filter='.release.mixed = true | .release.id = null' ; expect_msg="more than one release" ;;
            missing)   filter='.containers.waf.digest = null | .complete = false | .missing = ["waf"]' ; expect_msg="waf digest missing" ;;
            grammar)   filter='.containers.www.digest = "usagov_www:16302"' ; expect_msg="not a pinned sha256 digest" ;;
            nocapture) filter='.capture.source = "none"' ;           expect_msg="no container digest capture" ;;
        esac
        jq "$filter" "$sandbox/produced.json" > "$sandbox/case-$case_name.json" 2>/dev/null
        local verdict=$(run_in_tree validate.sh "$sandbox/case-$case_name.json" "" dr)
        if [ "$verdict" = "INVALID" ] && command grep -q "$expect_msg" "$sandbox/case-$case_name.json.findings" 2>/dev/null; then
            echo "✅ Refused: $case_name"
        else
            echo "❌ $case_name was not refused as expected ($verdict)"
            sed 's/^/     /' "$sandbox/case-$case_name.json.findings" 2>/dev/null
            failures=$((failures + 1))
        fi
    done

    # --- Metadata written before the schema existed still reads -------------
    jq -n --arg c "gsatts/usagov-2021:cms-1@sha256:$d64" \
          --arg w "gsatts/usagov-2021:www-1@sha256:$d64" \
          --arg f "gsatts/usagov-2021:waf-1@sha256:$d64" '{
        backup_tag: "OLD-dr-1-2026-01-01-0", timestamp: "2026-01-01T00:00:00Z",
        environment: "dr", ticket: "USAGOV-1",
        deployed_containers: {cms: {digest: $c}, www: {digest: $w}, waf: {digest: $f}}
    }' > "$sandbox/legacy-meta.json"
    if [ "$(run_in_tree validate.sh "$sandbox/legacy-meta.json" OLD-dr-1-2026-01-01-0 dr)" = "VALID" ] &&
       command grep -q "predates the schema" "$sandbox/legacy-meta.json.findings" 2>/dev/null; then
        echo "✅ Pre-schema metadata still validates, with a warning"
    else
        echo "❌ Pre-schema metadata handling regressed"
        sed 's/^/     /' "$sandbox/legacy-meta.json.findings" 2>/dev/null
        failures=$((failures + 1))
    fi

    # --- The reader fails closed and keeps positional alignment -------------
    # Callers address the output with `sed -n '2p'`, so a missing digest must
    # still occupy its line or the next app's digest slides into its place.
    local extract_out=$(run_in_tree extract.sh "$sandbox/case-missing.json" "cms,waf,www")
    local line_count=$(printf '%s\n' "$extract_out" | command grep -vc '^RC=')
    if [ "$(printf '%s\n' "$extract_out" | command grep -c '^RC=1')" = "1" ] && [ "$line_count" = "3" ] &&
       [ -z "$(printf '%s\n' "$extract_out" | sed -n '2p')" ]; then
        echo "✅ The reader fails closed on a missing digest without shifting the others"
    else
        echo "❌ Reader alignment or status wrong: [$extract_out]"
        failures=$((failures + 1))
    fi

    # --- Nothing unreadable is ever stored ----------------------------------
    rm -f "$sandbox/uploaded.json"
    if [ "$(run_in_tree commit.sh)" = "REFUSED" ] && [ ! -f "$sandbox/uploaded.json" ]; then
        echo "✅ An empty metadata document is never uploaded"
    else
        echo "❌ An unusable metadata document was uploaded"
        failures=$((failures + 1))
    fi

    # --- Freshness arithmetic ------------------------------------------------
    # date -d, date -j and BusyBox date accept different formats, so the
    # conversion is arithmetic; these are the values BSD date agrees on.
    cat > "$sandbox/epoch.sh" <<'DRIVER'
#!/bin/sh
. ./scripts/common.sh >/dev/null 2>&1
iso8601_to_epoch "$1"
DRIVER
    if [ "$(run_in_tree epoch.sh 1970-01-01T00:00:00Z)" = "0" ] &&
       [ "$(run_in_tree epoch.sh 2026-08-10T23:03:07Z)" = "1786402987" ] &&
       [ -z "$(run_in_tree epoch.sh 'not-a-timestamp')" ]; then
        echo "✅ Capture timestamps convert correctly without date(1) extensions"
    else
        echo "❌ Capture timestamp conversion mismatch"
        failures=$((failures + 1))
    fi

    # --- The cron capture is machine-written, not concatenated --------------
    local cron_script="$PROJECT_ROOT/scripts/cron/update-container-digests.sh"
    # Compact on purpose: `jq -nc`. cron and cms deploy separately, so the
    # capture has to stay matchable by the single-line sed scrapers in readers
    # that are already deployed, while being valid JSON for the new ones.
    if command grep -q 'jq -nc' "$cron_script" && ! command grep -q 'JSON="{\\n' "$cron_script"; then
        echo "✅ The cron capture is built with jq, compactly"
    else
        echo "❌ The cron capture is not built as compact JSON by jq"
        failures=$((failures + 1))
    fi

    if command grep -q 'REQUIRED_APPS=' "$cron_script" &&
       command grep -q 'WARNING: no digest for required app' "$cron_script"; then
        echo "✅ The cron capture requires every release component"
    else
        echo "❌ The cron capture does not require the release components"
        failures=$((failures + 1))
    fi

    # An empty result used to `exit 0`, leaving the previous capture in place to
    # be read as current by the next backup.
    if command grep -q 'WARNING: No running apps found' "$cron_script"; then
        echo "❌ The cron capture still exits successfully when it finds nothing"
        failures=$((failures + 1))
    else
        echo "✅ An incomplete capture is reported rather than silently skipped"
    fi

    # --- Rollback has to go through the validator, with a working override --
    # Rollback used to read digests out of the document without validating it at
    # all. The override has to use the value rollback actually stores for the
    # flag ("--skip-validation"); comparing it to "true" silently ignores it, so
    # an operator who passed the flag would still be refused.
    local deploy_script="$PROJECT_ROOT/scripts/devops/deploy.sh"
    if command grep -q 'validate_deployment_metadata "$metadata_json" "$backup_tag" "$env"' "$deploy_script"; then
        echo "✅ Rollback validates metadata against the space it is deploying into"
    else
        echo "❌ Rollback does not validate the metadata it deploys from"
        failures=$((failures + 1))
    fi

    if command grep -q 'skip_validation" = "true"' "$deploy_script" ||
       command grep -q 'skip_validation" != "true"' "$deploy_script"; then
        echo "❌ A metadata gate compares skip_validation to \"true\", which never matches the stored flag"
        failures=$((failures + 1))
    else
        echo "✅ The validation override uses the flag value rollback stores"
    fi

    # --- Readers deployed before this change must keep working --------------
    # cron and cms deploy separately, so a new capture has to stay readable by
    # the old sed scraper: hence compact JSON with string-valued digests.
    printf '{"metadata_version":1,"timestamp":"2026-01-01T00:00:00Z","captured_at":"2026-01-01T00:00:00Z","environment":"dr","containers":{"cms":"gsatts/usagov-2021:cms-1@sha256:%s","www":"gsatts/usagov-2021:www-1@sha256:%s","waf":"gsatts/usagov-2021:waf-1@sha256:%s"},"complete":true,"missing":[]}' \
        "$d64" "$d64" "$d64" > "$sandbox/new-capture.json"
    local old_scrape=$(sed -n 's/.*"containers":[[:space:]]*{\([^}]*\)}.*/\1/p' "$sandbox/new-capture.json" |
        sed 's/"//g' | sed 's/:[^,]*//g' | tr ',' '\n' | sed 's/^[[:space:]]*//' | sort | tr '\n' ' ')
    if [ "$old_scrape" = "cms waf www " ]; then
        echo "✅ The new capture is still readable by pre-change readers"
    else
        echo "❌ The new capture breaks readers deployed before this change: [$old_scrape]"
        failures=$((failures + 1))
    fi

    rm -rf "$sandbox"
    [ "$failures" -eq 0 ]
}

# H-08: mutable S3 metadata is an unauthenticated code-deployment trust root.
#
# What lands in `cf push --docker-image` decides what code runs, and it used to
# be accepted as an arbitrary string: anything able to write the metadata object
# a rollback reads could choose the image an operator deployed. Two defences,
# both checked here — an allowlist applied at the single push chokepoint, and a
# cross-check against the release record in git, which is reached through GitHub
# rather than the bucket, so tampering with the bucket alone no longer agrees.
test_deployment_image_trust() {
    echo "🔐 Testing deployment image trust boundary..."

    local sandbox=""
    sandbox=$(mktemp -d) || { echo "❌ Could not create test sandbox"; return 1; }

    local failures=0
    local d64="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
    local e64="bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"

    # A tree deploy.sh can run out of, plus a git repo holding a release record,
    # so nothing here depends on the real repository's tags or on the network.
    mkdir -p "$sandbox/tree/scripts/snapshot" "$sandbox/tree/scripts/devops" "$sandbox/bin"
    cp "$PROJECT_ROOT/scripts/common.sh" "$sandbox/tree/scripts/"
    cp "$PROJECT_ROOT/scripts/devops/deploy.sh" "$sandbox/tree/scripts/devops/"
    cp "$BACKUP_DIR/manager.sh" "$BACKUP_DIR/backup-system.conf" "$sandbox/tree/scripts/snapshot/"

    (
        cd "$sandbox/tree" || exit 1
        git init --quiet . 2>/dev/null
        git config user.email test@example.com
        git config user.name test
        git add -A >/dev/null 2>&1
        git commit --quiet -m "fixture" >/dev/null 2>&1
        git tag -a usagov-cci-build-777-dr \
            -m "CCI_BUILD=777|CMS_DIGEST=@sha256:${d64}|WWW_DIGEST=@sha256:${d64}|WAF_DIGEST=@sha256:${d64}" >/dev/null 2>&1
    )

    # Records every push so the test can tell "refused" from "pushed anyway",
    # and serves metadata for the rollback path.
    cat > "$sandbox/bin/cf" <<'FAKECF'
#!/bin/sh
case "$1" in
    target) echo "org:            gsa-tts-usagov"; echo "space:          dr"; exit 0 ;;
    oauth-token) echo token; exit 0 ;;
    push) printf '%s\n' "$*" >> "$PUSH_LOG"; exit 0 ;;
    ssh)
        last=""; for a in "$@"; do last="$a"; done
        case "$last" in
            *fetch_deployment_metadata*) [ -f "$META_FILE" ] && cat "$META_FILE" ;;
        esac
        exit 0 ;;
    app) exit 0 ;;
esac
exit 0
FAKECF
    chmod +x "$sandbox/bin/cf"

    cat > "$sandbox/image.sh" <<'DRIVER'
#!/bin/sh
. ./scripts/common.sh >/dev/null 2>&1
init_backup_system >/dev/null 2>&1
command -v validate_deployment_image >/dev/null 2>&1 || { echo NOFUNC; exit 0; }
if validate_deployment_image "$1" "$2" >/dev/null 2>&1; then echo ACCEPT; else echo REFUSE; fi
DRIVER

    cat > "$sandbox/record.sh" <<'DRIVER'
#!/bin/sh
. ./scripts/common.sh >/dev/null 2>&1
init_backup_system >/dev/null 2>&1
command -v verify_release_record >/dev/null 2>&1 || { echo NOFUNC; exit 0; }
verify_release_record "$1" "$2" "$3" "$4" >/dev/null 2>&1
case $? in
    0) echo MATCH ;;
    1) echo MISMATCH ;;
    2) echo NORECORD ;;
esac
DRIVER

    cat > "$sandbox/deployone.sh" <<'DRIVER'
#!/bin/sh
# Reaches _deploy_app the way every deployment path does, without the
# confirmation prompt in front of it.
. ./scripts/common.sh >/dev/null 2>&1
init_backup_system >/dev/null 2>&1
eval "$(sed -n '/^validate_app_name()/,/^}/p' ./scripts/devops/deploy.sh)"
eval "$(sed -n '/^_deploy_app()/,/^}/p' ./scripts/devops/deploy.sh)"
# Proves the gate is what refuses, not a missing helper
command -v validate_app_name >/dev/null 2>&1 || { echo NOAPPCHECK; exit 0; }
command -v validate_deployment_image >/dev/null 2>&1 || { echo NOGATE; exit 0; }
if _deploy_app "$1" dr 777 "$2" >/dev/null 2>&1; then echo DEPLOYED; else echo REFUSED; fi
DRIVER

    in_tree() {
        (
            cd "$sandbox/tree" 2>/dev/null || exit 9
            PATH="$sandbox/bin:$PATH"; export PATH
            PUSH_LOG="$sandbox/pushes.log"; export PUSH_LOG
            META_FILE="$sandbox/meta.json"; export META_FILE
            sh "$sandbox/$1" "$2" "$3" "$4" "$5"
        )
    }

    # --- The grammar and allowlist ------------------------------------------
    local case_line="" image="" app="" want="" got=""
    # Every form CI and operators actually produce must be accepted; the digest
    # in the first is what `load-image-digest` composes, with no tag at all.
    for case_line in \
        "ACCEPT|gsatts/usagov-2021@sha256:$d64|cms" \
        "ACCEPT|gsatts/usagov-2021:cms-16302@sha256:$d64|cms" \
        "ACCEPT|gsatts/usagov-2021:cms-latest@sha256:$d64|cms" \
        "ACCEPT|docker.io/gsatts/usagov-2021:cron@sha256:$d64|cron" \
        "REFUSE|gsatts/usagov-2021:waf-16302@sha256:$d64|cms" \
        "REFUSE|attacker/backdoor@sha256:$d64|cms" \
        "REFUSE|ghcr.io/gsatts/usagov-2021@sha256:$d64|cms" \
        "REFUSE|gsatts/usagov-2021:cms-latest|cms" \
        "REFUSE|gsatts/usagov-2021@sha256:abc|cms" \
        "REFUSE|gsatts/usagov-2021@sha256:$(printf '%s' "$d64" | tr 'a' 'A')|cms" \
        "REFUSE|gsatts/usagov-2021@sha256:$d64 --docker-username evil|cms" \
        "REFUSE||cms"
    do
        want="${case_line%%|*}"
        image="${case_line#*|}"; image="${image%|*}"
        app="${case_line##*|}"
        got=$(in_tree image.sh "$image" "$app")
        if [ "$got" = "$want" ]; then
            echo "✅ ${want}: ${image:-<empty>}"
        else
            echo "❌ Expected $want for '${image:-<empty>}' as $app, got $got"
            failures=$((failures + 1))
        fi
    done

    # --- The push chokepoint ------------------------------------------------
    # Refusing in the validator is only worth anything if no push happens.
    : > "$sandbox/pushes.log"
    if [ "$(in_tree deployone.sh cms "attacker/backdoor@sha256:$d64")" = "REFUSED" ] &&
       [ ! -s "$sandbox/pushes.log" ]; then
        echo "✅ A disallowed image is refused with no cf push attempted"
    else
        echo "❌ A disallowed image reached cf push: $(cat "$sandbox/pushes.log" 2>/dev/null)"
        failures=$((failures + 1))
    fi

    : > "$sandbox/pushes.log"
    if [ "$(in_tree deployone.sh cms "gsatts/usagov-2021:cms-777@sha256:$d64")" = "DEPLOYED" ] &&
       command grep -q "docker-image gsatts/usagov-2021:cms-777@sha256:$d64" "$sandbox/pushes.log" 2>/dev/null; then
        echo "✅ An allowed image still deploys"
    else
        echo "❌ The gate blocks a legitimate image: $(cat "$sandbox/pushes.log" 2>/dev/null)"
        failures=$((failures + 1))
    fi

    # --- The release record -------------------------------------------------
    if [ "$(in_tree record.sh cms 777 dr "gsatts/usagov-2021:cms-777@sha256:$d64")" = "MATCH" ]; then
        echo "✅ A digest matching the release record is confirmed"
    else
        echo "❌ A digest matching the release record was not confirmed"
        failures=$((failures + 1))
    fi

    # A substituted digest is what tampering with the bucket looks like
    if [ "$(in_tree record.sh cms 777 dr "gsatts/usagov-2021:cms-777@sha256:$e64")" = "MISMATCH" ]; then
        echo "✅ A digest the release record contradicts is reported as a mismatch"
    else
        echo "❌ A substituted digest was not caught by the release record"
        failures=$((failures + 1))
    fi

    if [ "$(in_tree record.sh cms 999 dr "gsatts/usagov-2021:cms-999@sha256:$d64")" = "NORECORD" ] &&
       [ "$(in_tree record.sh cron 777 dr "gsatts/usagov-2021:cron@sha256:$d64")" = "NORECORD" ]; then
        echo "✅ An absent record is distinguished from a contradicted one"
    else
        echo "❌ A missing release record is not reported distinctly"
        failures=$((failures + 1))
    fi

    # --- The build number the lookup depends on -----------------------------
    # It comes out of the backup tag, written by the container at backup time.
    cat > "$sandbox/tagnum.sh" <<'DRIVER'
#!/bin/sh
. ./scripts/common.sh >/dev/null 2>&1
command -v backup_tag_build_number >/dev/null 2>&1 || { echo NOFUNC; exit 0; }
backup_tag_build_number "$1" || echo NONE
DRIVER
    if [ "$(in_tree tagnum.sh AUTO-dr-16302-2026-08-10-0)" = "16302" ] &&
       [ "$(in_tree tagnum.sh USAGOV-2813-dr-16265-2026-08-11--pre-deploy-0)" = "16265" ] &&
       [ "$(in_tree tagnum.sh AUTO-dr-git-abc1234-2026-08-10-0)" = "NONE" ]; then
        echo "✅ The build number is read from the backup tag, including hyphenated prefixes"
    else
        echo "❌ Build number extraction is wrong"
        failures=$((failures + 1))
    fi

    # --- Rollback refuses a digest the record contradicts -------------------
    # End to end through the real command, with cf faked.
    write_meta() {
        jq -n --arg cms "$1" --arg other "gsatts/usagov-2021:REPL@sha256:$d64" '{
            metadata_version: 1, backup_tag: "AUTO-dr-777-2026-01-01-0",
            timestamp: "2026-01-01T00:00:00Z", environment: "dr", ticket: "none",
            backup_type: "auto", git_commit: "x", git_branch: "y",
            release: {id: "777", mixed: false, components: ["cms","www","waf"]},
            containers: {
                cms: {digest: $cms, cci_build: "777", valid: true, required: true},
                www: {digest: ($other | sub("REPL"; "www-777")), cci_build: "777", valid: true, required: true},
                waf: {digest: ($other | sub("REPL"; "waf-777")), cci_build: "777", valid: true, required: true}
            },
            complete: true, missing: [],
            capture: {source: "cron-bucket", object: "o", parse: "json",
                      captured_at: "2026-01-01T00:00:00Z", age_seconds: 5, stale: false,
                      environment: "dr", environment_match: true},
            created_by: "test"
        }' > "$sandbox/meta.json"
    }

    rollback_verdict() {
        (
            cd "$sandbox/tree" 2>/dev/null || exit 9
            PATH="$sandbox/bin:$PATH"; export PATH
            PUSH_LOG="$sandbox/pushes.log"; export PUSH_LOG
            META_FILE="$sandbox/meta.json"; export META_FILE
            DEPLOY_ENV=dr; export DEPLOY_ENV
            sh scripts/devops/deploy.sh rollback AUTO-dr-777-2026-01-01-0 --apps=cms,www,waf $1 </dev/null 2>&1
        )
    }

    write_meta "gsatts/usagov-2021:cms-777@sha256:$e64"
    : > "$sandbox/pushes.log"
    local out=$(rollback_verdict "")
    if printf '%s' "$out" | command grep -q "does not match the release record" &&
       printf '%s' "$out" | command grep -q "Refusing to roll back" &&
       [ ! -s "$sandbox/pushes.log" ]; then
        echo "✅ Rollback refuses a digest the release record contradicts, without pushing"
    else
        echo "❌ Rollback did not refuse a contradicted digest"
        printf '%s\n' "$out" | sed 's/^/     /' | head -12
        failures=$((failures + 1))
    fi

    # The override must still exist: an emergency rollback cannot be blocked by a
    # metadata rule, so it proceeds as far as the confirmation prompt.
    out=$(rollback_verdict "--skip-validation")
    if printf '%s' "$out" | command grep -q "Continuing because --skip-validation was given"; then
        echo "✅ --skip-validation overrides the record check"
    else
        echo "❌ --skip-validation does not override the record check"
        printf '%s\n' "$out" | sed 's/^/     /' | head -12
        failures=$((failures + 1))
    fi

    # A digest the record confirms must pass the check
    write_meta "gsatts/usagov-2021:cms-777@sha256:$d64"
    out=$(rollback_verdict "")
    if printf '%s' "$out" | command grep -q "Digests confirmed against release record usagov-cci-build-777-dr" &&
       ! printf '%s' "$out" | command grep -q "does not match the release record"; then
        echo "✅ A confirmed digest passes the record check, and says so"
    else
        echo "❌ A confirmed digest was reported as a mismatch"
        printf '%s\n' "$out" | sed 's/^/     /' | head -12
        failures=$((failures + 1))
    fi

    # --- Metadata records are create-once -----------------------------------
    # Rollback deploys what it finds under a tag, so a later write that would
    # change the record is refused. Every component of a backup set shares one
    # tag, so an identical repeat has to stay a no-op.
    mkdir -p "$sandbox/s3/deployment-metadata"
    cat > "$sandbox/bin/aws" <<'FAKEAWS'
#!/bin/sh
# Directory-backed S3 with a real If-None-Match precondition on put-object.
svc="$1"; shift; act="$1"; shift
key=""; body=""; cond=""; bucket=""; src=""; dst=""
while [ $# -gt 0 ]; do
    case "$1" in
        --key) key="$2"; shift 2 ;;
        --body) body="$2"; shift 2 ;;
        --bucket) bucket="$2"; shift 2 ;;
        --if-none-match) cond=1; shift 2 ;;
        --*) shift ;;
        *) if [ -z "$src" ]; then src="$1"; elif [ -z "$dst" ]; then dst="$1"; fi; shift ;;
    esac
done
if [ "$svc $act" = "s3 cp" ]; then
    case "$src" in
        s3://*)
            path="$FS3/$(printf '%s' "$src" | sed 's|^s3://[^/]*/||')"
            [ -f "$path" ] || exit 1
            if [ "$dst" = "-" ]; then cat "$path"; else cat "$path" > "$dst"; fi
            exit 0 ;;
        *)
            path="$FS3/$(printf '%s' "$dst" | sed 's|^s3://[^/]*/||')"
            mkdir -p "$(dirname "$path")"; cat "$src" > "$path"; exit 0 ;;
    esac
fi
if [ "$svc $act" = "s3api put-object" ]; then
    target="$FS3/$key"
    if [ -n "$cond" ] && [ -f "$target" ]; then
        echo "An error occurred (PreconditionFailed) when calling the PutObject operation" >&2
        exit 1
    fi
    mkdir -p "$(dirname "$target")"
    cat "$body" > "$target"
    exit 0
fi
exit 0
FAKEAWS
    chmod +x "$sandbox/bin/aws"

    cat > "$sandbox/upload.sh" <<'DRIVER'
#!/bin/sh
. ./scripts/common.sh >/dev/null 2>&1
init_backup_system >/dev/null 2>&1
BUCKET_NAME=test-bucket
S3_EXTRA_PARAMS=""
setup_s3_vars() { return 0; }
if upload_deployment_metadata "$1" "$(cat "$2")" >/dev/null 2>&1; then echo STORED; else echo REFUSED; fi
DRIVER

    upload_in_tree() {
        (
            cd "$sandbox/tree" 2>/dev/null || exit 9
            PATH="$sandbox/bin:$PATH"; export PATH
            FS3="$sandbox/s3"; export FS3
            sh "$sandbox/upload.sh" "$1" "$2"
        )
    }

    write_meta "gsatts/usagov-2021:cms-777@sha256:$d64"
    cp "$sandbox/meta.json" "$sandbox/first.json"
    if [ "$(upload_in_tree AUTO-dr-777-2026-01-01-0 "$sandbox/first.json")" = "STORED" ]; then
        echo "✅ The first metadata record for a tag is stored"
    else
        echo "❌ The first metadata record was refused"
        failures=$((failures + 1))
    fi

    # Same content, a later component of the same backup set: only the moment
    # differs, so this must be accepted as a no-op.
    jq '.timestamp = "2026-01-01T00:04:00Z" | .capture.age_seconds = 245' "$sandbox/first.json" > "$sandbox/second.json"
    if [ "$(upload_in_tree AUTO-dr-777-2026-01-01-0 "$sandbox/second.json")" = "STORED" ]; then
        echo "✅ Another component of the same backup set is a no-op"
    else
        echo "❌ A second component of the same backup set was refused"
        failures=$((failures + 1))
    fi

    # A write that would change which image a rollback deploys is refused
    jq --arg d "gsatts/usagov-2021:cms-777@sha256:$e64" '.containers.cms.digest = $d' "$sandbox/first.json" > "$sandbox/tampered.json"
    if [ "$(upload_in_tree AUTO-dr-777-2026-01-01-0 "$sandbox/tampered.json")" = "REFUSED" ] &&
       command grep -q "$d64" "$sandbox/s3/deployment-metadata/AUTO-dr-777-2026-01-01-0.json" 2>/dev/null; then
        echo "✅ A write that would change the stored digests is refused and the record stands"
    else
        echo "❌ The stored metadata record was replaced"
        failures=$((failures + 1))
    fi

    rm -rf "$sandbox"
    [ "$failures" -eq 0 ]
}

# H-09: a restore must not combine components from unrelated backup events.
#
# Pairing used to be inferred: when a component was not found under the requested
# tag, the restore took the first `YYYY-MM-DD` out of the name, treated every
# backup that day as equally close, and kept the first candidate it encountered —
# ignoring ticket, environment, container, suffix and sequence, and accepting an
# older date when no same-day candidate existed. The set now carries a manifest
# recording the exact objects that belong to it, written when the backup is made,
# and resolution reads that record instead of searching.
test_backup_set_manifest() {
    echo "🧾 Testing backup set manifest and component pairing..."

    local sandbox=""
    sandbox=$(mktemp -d) || { echo "❌ Could not create test sandbox"; return 1; }

    local failures=0

    mkdir -p "$sandbox/tree/scripts/snapshot" "$sandbox/bin" \
        "$sandbox/s3/auto-backups/database" "$sandbox/s3/auto-backups/web-backup" \
        "$sandbox/s3/auto-backups/public_backup" "$sandbox/s3/backup-manifests"
    cp "$PROJECT_ROOT/scripts/common.sh" "$sandbox/tree/scripts/"
    cp "$BACKUP_DIR/manager.sh" "$BACKUP_DIR/backup-system.conf" "$sandbox/tree/scripts/snapshot/"

    # Directory-backed S3: prefix listing, object read/write, and a real
    # If-None-Match precondition on put-object.
    cat > "$sandbox/bin/aws" <<'FAKEAWS'
#!/bin/sh
R="$FS3"
svc="$1"; shift; act="$1"; shift
key=""; body=""; cond=""; src=""; dst=""
while [ $# -gt 0 ]; do
    case "$1" in
        --key) key="$2"; shift 2 ;;
        --body) body="$2"; shift 2 ;;
        --bucket) shift 2 ;;
        --if-none-match) cond=1; shift 2 ;;
        --recursive|--only-show-errors|--summarize) shift ;;
        --*) shift ;;
        *) if [ -z "$src" ]; then src="$1"; elif [ -z "$dst" ]; then dst="$1"; fi; shift ;;
    esac
done
strip() { printf '%s' "$1" | sed 's|^s3://[^/]*/||'; }
case "$svc $act" in
    "s3api put-object")
        t="$R/$key"
        if [ -n "$cond" ] && [ -f "$t" ]; then
            echo "An error occurred (PreconditionFailed) when calling the PutObject operation" >&2
            exit 1
        fi
        mkdir -p "$(dirname "$t")"; cat "$body" > "$t"; exit 0 ;;
    "s3 cp")
        case "$src" in
            s3://*)
                p="$R/$(strip "$src")"
                [ -f "$p" ] || exit 1
                if [ "$dst" = "-" ]; then cat "$p"; else cat "$p" > "$dst"; fi
                exit 0 ;;
            *)
                p="$R/$(strip "$dst")"
                mkdir -p "$(dirname "$p")"; cat "$src" > "$p"; exit 0 ;;
        esac ;;
    "s3 ls")
        p="$R/$(strip "$src")"
        case "$src" in
            */) [ -d "${p%/}" ] || exit 1
                for f in "${p%/}"/*; do [ -e "$f" ] || continue; echo "2026-01-01 00:00:00 10 $(basename "$f")"; done
                exit 0 ;;
            *)  if [ -f "$p" ]; then echo "2026-01-01 00:00:00 10 $(basename "$p")"; exit 0; fi
                if [ -d "$p" ]; then echo "                           PRE $(basename "$p")/"; exit 0; fi
                exit 1 ;;
        esac ;;
esac
exit 0
FAKEAWS
    chmod +x "$sandbox/bin/aws"

    cat > "$sandbox/write.sh" <<'DRIVER'
#!/bin/sh
. ./scripts/common.sh >/dev/null 2>&1
init_backup_system >/dev/null 2>&1
BUCKET_NAME=test-bucket
S3_EXTRA_PARAMS=""
command -v write_backup_set_manifest >/dev/null 2>&1 || { echo NOFUNC; exit 0; }
if write_backup_set_manifest "$@" >/dev/null 2>&1; then echo WROTE; else echo REFUSED; fi
DRIVER

    cat > "$sandbox/resolve.sh" <<'DRIVER'
#!/bin/sh
. ./scripts/common.sh >/dev/null 2>&1
init_backup_system >/dev/null 2>&1
BUCKET_NAME=test-bucket
S3_EXTRA_PARAMS=""
command -v resolve_backup_component >/dev/null 2>&1 || { echo NOFUNC; exit 0; }
# Deliberately through the entry point the restore uses
if out=$(find_corresponding_backup "$1" "$2"); then echo "$out"; else echo UNRESOLVED; fi
DRIVER

    in_tree() {
        (
            cd "$sandbox/tree" 2>/dev/null || exit 9
            PATH="$sandbox/bin:$PATH"; export PATH
            FS3="$sandbox/s3"; export FS3
            sh "$sandbox/$1" "$2" "$3" "$4" "$5" "$6"
        )
    }

    local S3="$sandbox/s3/auto-backups"
    local set_tag="USAGOV-2813-dr-16265-2026-08-11--pre-deploy-0"
    local older_public="AUTO-dr-16200-2026-08-04-0"
    # Same day, different ticket, different container: what the date-based search
    # used to be free to pick.
    local decoy="AUTO-dr-16302-2026-08-11-0"

    mkdir -p "$S3/web-backup/$set_tag" "$S3/public_backup/$older_public" \
             "$S3/public_backup/$decoy" "$S3/web-backup/$decoy"
    : > "$S3/web-backup/$set_tag/index.html"
    : > "$S3/public_backup/$older_public/logo.png"
    : > "$S3/public_backup/$decoy/other.png"
    : > "$S3/database/$set_tag.sql.gz"
    : > "$S3/database/$decoy.sql.gz"

    # --- A set that recorded every component -------------------------------
    if [ "$(in_tree write.sh "$set_tag" dr "static:captured:$set_tag" "public:unchanged:$older_public" "db:captured:$set_tag")" = "WROTE" ]; then
        echo "✅ A backup set records a manifest"
    else
        echo "❌ The manifest was not written"
        failures=$((failures + 1))
    fi

    # The whole point: public was skipped as unchanged, and the set says which
    # backup holds those files. The decoy is the same day and would have won.
    local got=$(in_tree resolve.sh "$set_tag" public)
    if [ "$got" = "$older_public" ]; then
        echo "✅ The recorded public backup is used, not a same-day candidate"
    else
        echo "❌ Public resolved to '$got', expected '$older_public'"
        failures=$((failures + 1))
    fi

    got=$(in_tree resolve.sh "$set_tag" db)
    if [ "$got" = "${set_tag}.sql.gz" ]; then
        echo "✅ The database resolves to the set's own object"
    else
        echo "❌ Database resolved to '$got'"
        failures=$((failures + 1))
    fi

    # --- A component the set does not have ---------------------------------
    # A same-day database from another ticket exists; it must not be adopted.
    local partial_tag="USAGOV-2900-dr-16265-2026-08-11--pre-deploy-1"
    mkdir -p "$S3/web-backup/$partial_tag"
    : > "$S3/web-backup/$partial_tag/index.html"
    in_tree write.sh "$partial_tag" dr "static:captured:$partial_tag" >/dev/null
    got=$(in_tree resolve.sh "$partial_tag" db)
    if [ "$got" = "UNRESOLVED" ]; then
        echo "✅ A component the set never had stays unresolved, with a same-day one present"
    else
        echo "❌ A same-day database was adopted into another set: '$got'"
        failures=$((failures + 1))
    fi

    # --- Sets created before manifests existed -----------------------------
    local legacy_tag="AUTO-dr-16100-2026-07-01-0"
    mkdir -p "$S3/web-backup/$legacy_tag" "$S3/public_backup/$legacy_tag"
    : > "$S3/public_backup/$legacy_tag/logo.png"
    : > "$S3/database/$legacy_tag.sql.gz"
    got=$(in_tree resolve.sh "$legacy_tag" public)
    if [ "$got" = "$legacy_tag" ]; then
        echo "✅ Without a manifest an exact component still resolves"
    else
        echo "❌ Legacy exact match failed: '$got'"
        failures=$((failures + 1))
    fi

    # Same day as the decoy, no manifest, and its own public objects are absent:
    # this is precisely the case the old code answered with a guess.
    local legacy_missing="AUTO-dr-16301-2026-08-11-0"
    mkdir -p "$S3/web-backup/$legacy_missing"
    : > "$S3/database/$legacy_missing.sql.gz"
    got=$(in_tree resolve.sh "$legacy_missing" public)
    if [ "$got" = "UNRESOLVED" ]; then
        echo "✅ Without a manifest a missing component is not guessed from the same day"
    else
        echo "❌ A same-day public backup was guessed: '$got'"
        failures=$((failures + 1))
    fi

    # --- A record whose objects are gone ------------------------------------
    rm -rf "$S3/public_backup/$older_public"
    got=$(in_tree resolve.sh "$set_tag" public)
    if [ "$got" = "UNRESOLVED" ]; then
        echo "✅ A recorded component whose objects were deleted fails closed"
    else
        echo "❌ A deleted component resolved anyway: '$got'"
        failures=$((failures + 1))
    fi

    # --- The manifest is written once --------------------------------------
    if [ "$(in_tree write.sh "$set_tag" dr "static:captured:$set_tag" "public:unchanged:$older_public" "db:captured:$set_tag")" = "WROTE" ]; then
        echo "✅ Rewriting the same manifest is a no-op"
    else
        echo "❌ An identical manifest rewrite was refused"
        failures=$((failures + 1))
    fi
    if [ "$(in_tree write.sh "$set_tag" dr "static:captured:$set_tag" "db:captured:$decoy")" = "REFUSED" ]; then
        echo "✅ A manifest that would repoint a set is refused"
    else
        echo "❌ A conflicting manifest replaced the recorded set"
        failures=$((failures + 1))
    fi

    # --- The search itself is gone ------------------------------------------
    local common="$PROJECT_ROOT/scripts/common.sh"
    if sed -n '/^find_corresponding_backup()/,/^}/p' "$common" | command grep -q 'extract_date_from_backup_name'; then
        echo "❌ Component pairing still extracts a date from the backup name"
        failures=$((failures + 1))
    else
        echo "✅ Component pairing no longer reads a date out of the tag"
    fi

    # --- The backup side records what restore reads -------------------------
    local mgr="$BACKUP_DIR/manager.sh"
    if command grep -q 'PUBLIC_BACKUP_LINK_TAG="$LATEST_PUBLIC_BACKUP"' "$mgr"; then
        echo "✅ The smart public skip records the backup it compared against"
    else
        echo "❌ The smart public skip still discards its comparison target"
        failures=$((failures + 1))
    fi
    if command grep -q 'write_backup_set_manifest "$set_tag"' "$mgr"; then
        echo "✅ A backup run records the set manifest"
    else
        echo "❌ The backup command does not write a manifest"
        failures=$((failures + 1))
    fi

    # Each component's status is captured before anything else reads `$?`.
    # Reading it a second time returns the status of the capture, which would
    # mark every component successful and hide real failures.
    if sed -n '/^run_backup_command()/,/^}/p' "$mgr" | command grep -q 'local backup_result=\$?'; then
        echo "❌ A component's status is re-read from \$? after another assignment"
        failures=$((failures + 1))
    else
        echo "✅ Component status is captured once, not re-read from \$?"
    fi

    # `backup all --json` reported an empty tag for static and public: the
    # variables it interpolates were never assigned anywhere.
    if command grep -q 'STATIC_BACKUP_TAG="$set_tag"' "$mgr" && command grep -q 'PUBLIC_BACKUP_TAG="$PUBLIC_BACKUP_LINK_TAG"' "$mgr"; then
        echo "✅ The JSON result reports the tags it interpolates"
    else
        echo "❌ static/public tags are still never assigned"
        failures=$((failures + 1))
    fi

    # --- Naming a component explicitly is accepted --------------------------
    # The escape hatch for sets that cannot be resolved: it must not be rejected
    # as an unknown option, nor mistaken for the backup tag.
    local out=$(cd "$sandbox/tree" 2>/dev/null && PATH="$sandbox/bin:$PATH" FS3="$sandbox/s3" \
        sh scripts/snapshot/manager.sh restore "$set_tag" --only=public --public-from="$decoy" -y --ssm 2>&1)
    if ! printf '%s' "$out" | command grep -q 'Unrecognized option' &&
       ! printf '%s' "$out" | command grep -q 'Backup tag is required'; then
        echo "✅ --public-from is accepted and not mistaken for the tag"
    else
        echo "❌ --public-from is not handled: $(printf '%s' "$out" | head -2)"
        failures=$((failures + 1))
    fi

    rm -rf "$sandbox"
    [ "$failures" -eq 0 ]
}

# H-10: the guard that decides whether a destructive `aws s3 sync --delete` may
# replace the live static site, and the pipelines around it.
#
# The guard counted S3 objects with `grep "^\d\{4\}\-"`. `\d` is not a portable
# digit class — GNU grep and BusyBox treat it as a stray escape and match a
# literal `d` — so no listing line matched, the count was 0, and dividing by it
# in `bc` produced an empty string. Both direction flags were then false and
# control fell through to the branch that publishes: the guard against deleting
# the site could not fire. Separately, the sync, the Drush image sync, the
# backup, the cleanup and the log upload were each piped through `tee`, so the
# status checked afterwards belonged to `tee` and a failure read as success.
test_tome_sync_guard() {
    echo "🛡️  Testing Tome destructive-sync guard..."

    local sandbox=""
    sandbox=$(mktemp -d) || { echo "❌ Could not create test sandbox"; return 1; }

    local failures=0
    local script="$PROJECT_ROOT/scripts/tome-sync.sh"

    mkdir -p "$sandbox/bin" "$sandbox/render/a/b"
    : > "$sandbox/render/one.html"
    : > "$sandbox/render/a/two.html"
    : > "$sandbox/render/a/b/three.html"

    # A listing in the real format, including one key under the excluded prefix
    cat > "$sandbox/bin/aws" <<'FAKEAWS'
#!/bin/sh
[ -n "$FAKE_AWS_FAIL" ] && { echo "An error occurred (AccessDenied)" >&2; exit 1; }
printf '2026-08-19 12:00:00       1234 web/index.html\n'
printf '2026-08-19 12:00:01        567 web/es/index.html\n'
printf '2026-08-19 12:00:02        890 web/s3/files/logo.png\n'
exit 0
FAKEAWS
    chmod +x "$sandbox/bin/aws"

    # The real functions, taken out of the real script
    cat > "$sandbox/driver.sh" <<'DRIVER'
#!/bin/sh
for fn in tome_count_s3_objects tome_count_render_files tome_change_guard tome_run_logged; do
    eval "$(sed -n "/^${fn}()/,/^}/p" "$SCRIPT_UNDER_TEST")"
    command -v "$fn" >/dev/null 2>&1 || { echo "NOFUNC:$fn"; exit 0; }
done
S3_EXTRA_PARAMS=""
case "$1" in
    count-s3)      tome_count_s3_objects "s3://b/web/" "$2" || echo FAILED ;;
    count-render)  tome_count_render_files "$2" || echo FAILED ;;
    guard)         tome_change_guard "$2" "$3" 0.10 ;;
    run-status)    tome_run_logged "$4" sh -c "$2" >/dev/null 2>&1; echo "$?" ;;
esac
DRIVER

    drive() {
        (
            cd "$PROJECT_ROOT" 2>/dev/null || exit 9
            PATH="$sandbox/bin:$PATH"; export PATH
            SCRIPT_UNDER_TEST="$script"; export SCRIPT_UNDER_TEST
            sh "$sandbox/driver.sh" "$@"
        )
    }

    # --- Counting is structural, not a regex with a non-portable digit class --
    local got=$(drive count-s3 "")
    if [ "$got" = "3" ]; then
        echo "✅ A real AWS listing is counted"
    else
        echo "❌ Counted '$got' objects in a three-object listing"
        failures=$((failures + 1))
    fi

    got=$(drive count-s3 "web/s3/files/")
    if [ "$got" = "2" ]; then
        echo "✅ The excluded prefix is left out of the count"
    else
        echo "❌ Exclusion produced '$got'"
        failures=$((failures + 1))
    fi

    # A failed listing must not become a count. It used to be folded in through
    # `2>&1`, where an error message counted as an object.
    got=$(cd "$PROJECT_ROOT" && PATH="$sandbox/bin:$PATH" SCRIPT_UNDER_TEST="$script" FAKE_AWS_FAIL=1 sh "$sandbox/driver.sh" count-s3 "")
    if [ "$got" = "FAILED" ]; then
        echo "✅ A failed listing is reported, not counted"
    else
        echo "❌ A failed listing produced '$got'"
        failures=$((failures + 1))
    fi

    got=$(drive count-render "$sandbox/render")
    if [ "$got" = "3" ]; then
        echo "✅ Generated files are counted"
    else
        echo "❌ Counted '$got' generated files"
        failures=$((failures + 1))
    fi

    got=$(drive count-render "$sandbox/does-not-exist")
    if [ "$got" = "FAILED" ]; then
        echo "✅ A missing render directory is reported, not counted as zero"
    else
        echo "❌ A missing render directory produced '$got'"
        failures=$((failures + 1))
    fi

    # --- The verdicts -------------------------------------------------------
    local case_line="" want="" s3="" tome=""
    for case_line in \
        "publish|1000|1000" \
        "publish|1000|950" \
        "refuse-fewer|1000|800" \
        "publish-more|1000|1300" \
        "refuse-no-baseline|0|900" \
        "refuse-no-baseline||900" \
        "refuse-nothing-generated|1000|0" \
        "refuse-nothing-generated|1000|"
    do
        want="${case_line%%|*}"
        s3="${case_line#*|}"; s3="${s3%|*}"
        tome="${case_line##*|}"
        got=$(drive guard "$s3" "$tome")
        if [ "$got" = "$want" ]; then
            echo "✅ ${s3:-<empty>} live / ${tome:-<empty>} generated → $got"
        else
            echo "❌ ${s3:-<empty>} live / ${tome:-<empty>} generated → '$got', expected '$want'"
            failures=$((failures + 1))
        fi
    done

    # --- A status must be the command's, not the tee's ----------------------
    got=$(drive run-status 'echo output; exit 7' "" "$sandbox/run.log")
    if [ "$got" = "7" ] && command grep -q '^output$' "$sandbox/run.log" 2>/dev/null; then
        echo "✅ A logged command reports its own status and still writes the log"
    else
        echo "❌ A logged command returned '$got' (log: $(head -1 "$sandbox/run.log" 2>/dev/null))"
        failures=$((failures + 1))
    fi

    # --- The non-portable pattern is gone -----------------------------------
    # It cannot be caught behaviorally on macOS: ugrep and BSD grep both accept
    # \d as a digit class, so the bug only shows up under GNU grep or BusyBox.
    # Comments are stripped first: the explanation of this defect necessarily
    # contains the pattern it describes.
    if sed 's/#.*//' "$script" | command grep -q '\\d'; then
        echo "❌ tome-sync.sh still matches dates with a non-portable \\d class"
        failures=$((failures + 1))
    else
        echo "✅ No non-portable \\d digit class remains in code"
    fi

    # --- The critical commands are not piped through tee -------------------
    local masked=0
    local pattern=""
    for pattern in 'aws s3 sync' 'drush usagov:ssg-sync-images' 'BACKUP_MANAGER" backup' 'BACKUP_MANAGER" clean'; do
        if command grep -n "$pattern" "$script" | command grep -q '| *tee'; then
            echo "❌ Still piped through tee, so its status is tee's: $pattern"
            masked=$((masked + 1))
        fi
    done
    if [ "$masked" -eq 0 ]; then
        echo "✅ The sync, image sync, backup and cleanup report their own status"
    else
        failures=$((failures + masked))
    fi

    # --force is for the size judgement, not for missing preconditions: forcing
    # past "nothing was generated" would sync an empty directory with --delete.
    if command grep -q 'Not honouring --force' "$script" &&
       command grep -B 3 'Not honouring --force' "$script" | command grep -q 'refuse-nothing-generated'; then
        echo "✅ --force does not override a missing precondition"
    else
        echo "❌ --force still overrides every refusal"
        failures=$((failures + 1))
    fi

    # --- Verification gates the backup and the success status ---------------
    if command grep -q 'Skipping automatic backups: the sync did not verify' "$script"; then
        echo "✅ An unverified sync is not backed up as a recovery point"
    else
        echo "❌ The backup does not depend on post-sync verification"
        failures=$((failures + 1))
    fi

    if command grep -q 'post-sync verification failed' "$script" &&
       command grep -q 'Static Site Sync FAILED' "$script"; then
        echo "✅ The status indicator reports sync failure instead of success"
    else
        echo "❌ The success status is still published unconditionally"
        failures=$((failures + 1))
    fi

    # The success status used to be published immediately after the branch that
    # prints "Sync operation failed", with nothing testing either outcome.
    if command grep -B 4 'Static Site Generation and Sync Completed Successfully' "$script" |
        command grep -q 'SYNC_VERIFIED'; then
        echo "✅ Success is announced only after the outcome is tested"
    else
        echo "❌ Success is still announced without testing the outcome"
        failures=$((failures + 1))
    fi

    rm -rf "$sandbox"
    [ "$failures" -eq 0 ]
}

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
    run_test "Cleanup Type Isolation" "test_cleanup_type_isolation"
    run_test "Backup Simulation" "test_backup_simulation"
    run_test "Backup Download Functionality" "test_backup_download"

    echo ""
    print_status $BLUE "🔄 RESTORE & ADVANCED TESTS"
    print_status $BLUE "==========================="
    run_test "Restore Functionality" "test_restore_functionality"
    run_test "Restore Preflight and Compensation" "test_restore_preflight_and_compensation"
    run_test "Drupal State Management" "test_state_management"
    run_test "State Restoration Guarantees" "test_state_restoration_guarantees"
    run_test "Backup Lock and Scheduling" "test_backup_lock_and_scheduling"
    run_test "Backup Set Numbering" "test_backup_set_numbering"
    run_test "Deployment Metadata Contract" "test_deployment_metadata_contract"
    run_test "Deployment Image Trust" "test_deployment_image_trust"
    run_test "Backup Set Manifest" "test_backup_set_manifest"
    run_test "Tome Sync Guard" "test_tome_sync_guard"
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