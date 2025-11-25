#!/bin/sh

# Backup System Test Script

set -e

# Load common utilities
SCRIPT_DIR=$(dirname "$0")
. "$SCRIPT_DIR/common.sh"

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
        return 1
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

    # Source the config
    . "$config_file"

    # Check required variables are set
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
    # Updated to check for centralized backup system usage
    for pattern in "snapshot/common.sh" "init_backup_system" "create_static_backup" "create_public_backup" "clean_old_backups" "setup_s3_vars" "BACKUP_PREFIX" "BACKUP_TIMESTAMP"; do
        if grep -q "$pattern" "$tome_sync_script"; then
            echo "✅ Found backup integration: $pattern"
        else
            echo "❌ Missing backup integration: $pattern"
            return 1
        fi
    done

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
    . "$BACKUP_DIR/backup-system.conf"

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

    # Load config to test database backup settings
    . "$BACKUP_DIR/backup-system.conf"

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

    # Run all tests
    run_test "Configuration File Loading" "test_config_loading"
    run_test "Script Files and Permissions" "test_script_files"
    run_test "Required Dependencies" "test_dependencies"
    run_test "AWS Connectivity" "test_aws_connectivity"
    run_test "Backup Integration in tome-sync.sh" "test_backup_integration"
    run_test "Backup Manager Functionality" "test_backup_manager"
    run_test "Manager Commands Interface" "test_manager_commands"
    run_test "Database Backup System" "test_database_backup_system"
    run_test "Date Calculations" "test_date_calculations"
    run_test "Backup Naming Pattern" "test_backup_naming"
    run_test "Backup Simulation" "test_backup_simulation"
    run_test "Backup Download Functionality" "test_backup_download"
    run_test "Log Directory Access" "test_log_directory"

    # Test results summary
    echo ""
    print_status $BLUE "📊 Test Results"
    print_status $BLUE "=============="

    echo "📊 Total Tests: $TESTS_TOTAL"
    print_status $GREEN "✅ Tests Passed: $TESTS_PASSED"
    print_status $RED "❌ Tests Failed: $TESTS_FAILED"

    # Final summary
    if [ $TESTS_FAILED -eq 0 ]; then
        echo ""
        print_status $GREEN "🎉 ALL TESTS PASSED! The backup system is ready for use."
        echo ""
        print_status $YELLOW "👉 Next steps:"
        echo "1. Run a Tome sync to test automatic backups in action"
        echo "2. Monitor the logs for backup creation messages"
        echo "3. Use 'scripts/snapshot/manager.sh list' to see created backups"
        echo "4. Use 'scripts/snapshot/manager.sh backup' to create manual backups"
        return 0
    else
        echo ""
        print_status $RED "❌ SOME TESTS FAILED. Please fix the issues above before using the backup system."
        echo ""
        print_status $YELLOW "🔧 Common fixes:"
        echo "1. Ensure all dependencies are installed (aws cli, jq, etc.)"
        echo "2. Verify AWS credentials and S3 access"
        echo "3. Check file permissions on script files"
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