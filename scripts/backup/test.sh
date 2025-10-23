#!/bin/sh

# Backup System Test Script

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Test tracking
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_TOTAL=0

print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Function to show usage
show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --help, -h              Show this help message"
    echo "  --force-static-backup   Force an immediate static site backup"
    echo "  --force-public-backup   Force an immediate public files backup"
    echo "  --force-db-backup       Force an immediate database backup"
    echo "  --force-all-backups     Force all three backup types"
    echo ""
    echo "Examples:"
    echo "  $0                           # Run full test suite"
    echo "  $0 --force-static-backup     # Test + force static site backup"
    echo "  $0 --force-all-backups       # Test + force all backup types"
}

# Function to run a test
run_test() {
    local test_name="$1"
    local test_command="$2"

    TESTS_TOTAL=`expr $TESTS_TOTAL + 1`
    echo ""
    print_status $BLUE "🧪 TEST $TESTS_TOTAL: $test_name"
    echo "----------------------------------------"

    if eval "$test_command"; then
        print_status $GREEN "✅ PASSED: $test_name"
        TESTS_PASSED=`expr $TESTS_PASSED + 1`
        return 0
    else
        print_status $RED "❌ FAILED: $test_name"
        TESTS_FAILED=`expr $TESTS_FAILED + 1`
        return 1
    fi
}

# Function to check file exists and is readable
check_file() {
    local file_path="$1"
    local description="$2"

    if [ -f "$file_path" ] && [ -r "$file_path" ]; then
        echo "✅ Found $description: $file_path"
        return 0
    else
        echo "❌ Missing or unreadable $description: $file_path"
        return 1
    fi
}

# Function to check if a command exists
check_command() {
    local cmd="$1"
    if command -v "$cmd" >/dev/null 2>&1; then
        echo "✅ Command available: $cmd"
        return 0
    else
        echo "❌ Command not found: $cmd"
        return 1
    fi
}

# Function to simulate S3 environment variables
setup_test_env() {
    # Check if we're in a Cloud Foundry environment
    if [ -n "$VCAP_SERVICES" ]; then
        echo "✅ Cloud Foundry environment detected"
        export BUCKET_NAME=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket' 2>/dev/null)
        export AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region' 2>/dev/null)
        export AWS_ACCESS_KEY_ID=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.access_key_id' 2>/dev/null)
        export AWS_SECRET_ACCESS_KEY=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.secret_access_key' 2>/dev/null)
        export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.hostname' 2>/dev/null)
        if [ -z "$AWS_ENDPOINT" ] || [ "$AWS_ENDPOINT" == "null" ]; then
            export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.endpoint' 2>/dev/null)
        fi

        APP_SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.space_name' 2>/dev/null)
        export APP_SPACE=${APP_SPACE:-local}

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
    for pattern in "backup-system.conf" "ENABLE_STATIC_AUTO_BACKUPS" "ENABLE_PUBLIC_AUTO_BACKUPS" "ENABLE_SMART_PUBLIC_BACKUP" "Creating static backup" "web-backup" "public_backup" "BACKUP_PREFIX"; do
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
    for component in "backup-system.conf" "ENABLE_DB_BACKUPS" "DB_BACKUP_PREFIX"; do
        if grep -q "$component" "$db_script"; then
            echo "✅ Automatic backup manager includes: $component"
        else
            echo "❌ Automatic backup manager missing: $component"
            return 1
        fi
    done

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

# Function to force static site backup
force_static_backup() {
    print_status $BLUE "============================================"
    print_status $BLUE "🌐 FORCING STATIC SITE BACKUP"
    print_status $BLUE "============================================"

    # Check if backup manager exists
    if [ ! -f "$BACKUP_DIR/manager.sh" ]; then
        print_status $RED "❌ Error: Backup manager not found"
        return 1
    fi

    print_status $YELLOW "🔄 Running static site backup..."

    if "$BACKUP_DIR/manager.sh" backup static TEST forced; then
        print_status $GREEN "✅ Static site backup complete"
        return 0
    else
        print_status $RED "❌ Static site backup failed"
        return 1
    fi
}

# Function to force public files backup
force_public_backup() {
    print_status $BLUE "============================================"
    print_status $BLUE "📁 FORCING PUBLIC FILES BACKUP"
    print_status $BLUE "============================================"

    # Check if backup manager exists
    if [ ! -f "$BACKUP_DIR/manager.sh" ]; then
        print_status $RED "❌ Error: Backup manager not found"
        return 1
    fi

    print_status $YELLOW "🔄 Running public files backup..."

    if "$BACKUP_DIR/manager.sh" backup public TEST forced; then
        print_status $GREEN "✅ Public files backup complete"
        return 0
    else
        print_status $RED "❌ Public files backup failed"
        return 1
    fi
}

# Function to force database backup
force_db_backup() {
    print_status $BLUE "============================================"
    print_status $BLUE "💾 FORCING DATABASE BACKUP"
    print_status $BLUE "============================================"

    # Check if backup manager exists
    if [ ! -f "$BACKUP_DIR/manager.sh" ]; then
        print_status $RED "❌ Error: Backup manager not found"
        return 1
    fi

    print_status $YELLOW "🔄 Running database backup..."

    if "$BACKUP_DIR/manager.sh" backup db TEST forced; then
        print_status $GREEN "✅ Database backup complete"
        return 0
    else
        print_status $RED "❌ Database backup failed"
        return 1
    fi
}

# Function to force all backup types
force_all_backups() {
    print_status $BLUE "============================================"
    print_status $BLUE "📦 FORCING ALL BACKUP TYPES"
    print_status $BLUE "============================================"

    # Check if backup manager exists
    if [ ! -f "$BACKUP_DIR/manager.sh" ]; then
        print_status $RED "❌ Error: Backup manager not found"
        return 1
    fi

    print_status $YELLOW "🔄 Running all backup types..."

    if "$BACKUP_DIR/manager.sh" backup all TEST forced; then
        print_status $GREEN "✅ All backup types complete"
        return 0
    else
        print_status $RED "❌ All backup types operation failed"
        return 1
    fi
}

# Main execution
main() {
    # Parse command line arguments
    local run_tests=true
    local force_static=false
    local force_public=false
    local force_db=false
    local force_all=false

    while [ $# -gt 0 ]; do
        case $1 in
            --help|-h)
                show_usage
                exit 0
                ;;
            --force-static-backup)
                force_static=true
                shift
                ;;
            --force-public-backup)
                force_public=true
                shift
                ;;
            --force-db-backup)
                force_db=true
                shift
                ;;
            --force-all-backups)
                force_all=true
                shift
                ;;
            *)
                echo "Unknown option: $1"
                show_usage
                exit 1
                ;;
        esac
    done

    # If force_all is set, enable all individual force flags
    if [ "$force_all" = true ]; then
        force_static=true
        force_public=true
        force_db=true
    fi

    # Run the test suite first if any tests should run
    if [ "$run_tests" = true ]; then
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
        run_test "Database Backup System" "test_database_backup_system"
        run_test "Date Calculations" "test_date_calculations"
        run_test "Backup Naming Pattern" "test_backup_naming"
        run_test "Backup Simulation" "test_backup_simulation"
        run_test "Log Directory Access" "test_log_directory"

    # Test results summary
    echo ""
    print_status $BLUE "📊 Test Results"
    print_status $BLUE "=============="

    echo "📊 Total Tests: $TESTS_TOTAL"
    print_status $GREEN "✅ Tests Passed: $TESTS_PASSED"
    print_status $RED "❌ Tests Failed: $TESTS_FAILED"

        # Handle forced backups after tests complete
        local test_success=true
        if [ $TESTS_FAILED -eq 0 ]; then
            echo ""
            print_status $GREEN "🎉 ALL TESTS PASSED! The backup system is ready for use."
        else
            echo ""
            print_status $RED "❌ SOME TESTS FAILED. Please fix the issues above before using the backup system."
            test_success=false
        fi
    fi

    # Execute forced backups if requested
    local backup_results=0
    local total_forced=0

    if [ "$force_all" = true ]; then
        echo ""
        force_all_backups
        backup_results=$?
        total_forced=3
    else
        if [ "$force_static" = true ]; then
            echo ""
            force_static_backup
            if [ $? -eq 0 ]; then
                backup_results=$((backup_results + 1))
            fi
            total_forced=$((total_forced + 1))
        fi

        if [ "$force_public" = true ]; then
            echo ""
            force_public_backup
            if [ $? -eq 0 ]; then
                backup_results=$((backup_results + 1))
            fi
            total_forced=$((total_forced + 1))
        fi

        if [ "$force_db" = true ]; then
            echo ""
            force_db_backup
            if [ $? -eq 0 ]; then
                backup_results=$((backup_results + 1))
            fi
            total_forced=$((total_forced + 1))
        fi
    fi

    # Final summary
    if [ $total_forced -gt 0 ]; then
        echo ""
        print_status $BLUE "============================================"
        print_status $BLUE "📋 FINAL SUMMARY"
        print_status $BLUE "============================================"

        if [ "$run_tests" = true ]; then
            echo "Tests: $TESTS_PASSED passed, $TESTS_FAILED failed"
        fi

        if [ "$force_all" = true ]; then
            if [ $backup_results -eq 0 ]; then
                echo "Forced Backups: All 3 types complete"
            else
                echo "Forced Backups: Some backups failed"
            fi
        else
            echo "Forced Backups: $backup_results of $total_forced complete"
        fi

        # Return appropriate exit code
        if [ "$run_tests" = true ] && [ $TESTS_FAILED -gt 0 ]; then
            return 1
        elif [ $total_forced -gt 0 ] && [ "$force_all" = true ] && [ $backup_results -ne 0 ]; then
            return 1
        elif [ $total_forced -gt 0 ] && [ "$force_all" = false ] && [ $backup_results -ne $total_forced ]; then
            return 1
        else
            return 0
        fi
    else
        # Only tests were run
        if [ "$test_success" = true ]; then
            echo ""
            print_status $YELLOW "👉 Next steps:"
            echo "1. Run a Tome sync to test automatic backups in action"
            echo "2. Monitor the logs for backup creation messages"
            echo "3. Use './manager.sh list' to see created backups"
            echo "4. Use './test.sh --help' to see forced backup options"
            return 0
        else
            echo ""
            print_status $YELLOW "🔧 Common fixes:"
            echo "1. Ensure all dependencies are installed (aws cli, jq, etc.)"
            echo "2. Verify AWS credentials and S3 access"
            echo "3. Check file permissions on script files"
            return 1
        fi
    fi
}

# Find the script directory and project root
SCRIPT_PATH=$(dirname "$0")
if [ "$(basename "$SCRIPT_PATH")" = "backup" ]; then
    # Running from scripts/backup directory
    PROJECT_ROOT="$(cd "$SCRIPT_PATH/../.." && pwd)"
    BACKUP_DIR="$SCRIPT_PATH"
elif [ -d "scripts/backup" ]; then
    # Running from project root
    PROJECT_ROOT="$(pwd)"
    BACKUP_DIR="$PROJECT_ROOT/scripts/backup"
else
    # Try to find the project root by looking for scripts/backup
    current_dir="$(pwd)"
    while [ "$current_dir" != "/" ]; do
        if [ -d "$current_dir/scripts/backup" ]; then
            PROJECT_ROOT="$current_dir"
            BACKUP_DIR="$current_dir/scripts/backup"
            break
        fi
        current_dir=$(dirname "$current_dir")
    done

    if [ -z "$PROJECT_ROOT" ]; then
        print_status $RED "❌ Error: Cannot find scripts/backup directory. Please run from project root or scripts/backup directory."
        print_status $YELLOW "💡 Usage: cd /path/to/usagov-2021 && scripts/backup/test.sh"
        exit 1
    fi
fi

# Check if we can find tome-sync.sh
if [ ! -f "$PROJECT_ROOT/scripts/tome-sync.sh" ]; then
    print_status $RED "❌ Error: Cannot find required files (tome-sync.sh not found at $PROJECT_ROOT/scripts/tome-sync.sh)"
    print_status $YELLOW "💡 Please ensure you're running from the project root or scripts/backup directory"
    exit 1
fi

# Run main function
main "$@"