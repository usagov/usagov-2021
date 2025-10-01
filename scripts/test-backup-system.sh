#!/bin/sh

# Tome Backup System Test Script
# This script validates all aspects of the automatic backup system

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results tracking
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_TOTAL=0

# Function to print colored output
print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Function to run a test
run_test() {
    local test_name="$1"
    local test_command="$2"

    TESTS_TOTAL=$((TESTS_TOTAL + 1))
    echo ""
    print_status $BLUE "TEST $TESTS_TOTAL: $test_name"
    echo "----------------------------------------"

    if eval "$test_command"; then
        print_status $GREEN "✓ PASSED: $test_name"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        return 0
    else
        print_status $RED "✗ FAILED: $test_name"
        TESTS_FAILED=$((TESTS_FAILED + 1))
        return 1
    fi
}

# Function to check file exists and is readable
check_file() {
    local file_path="$1"
    local description="$2"

    if [ -f "$file_path" ] && [ -r "$file_path" ]; then
        echo "✓ Found $description: $file_path"
        return 0
    else
        echo "✗ Missing or unreadable $description: $file_path"
        return 1
    fi
}

# Function to check if a command exists
check_command() {
    local cmd="$1"
    if command -v "$cmd" >/dev/null 2>&1; then
        echo "✓ Command available: $cmd"
        return 0
    else
        echo "✗ Command not found: $cmd"
        return 1
    fi
}

# Function to simulate S3 environment variables
setup_test_env() {
    # Check if we're in a Cloud Foundry environment
    if [ -n "$VCAP_SERVICES" ]; then
        echo "✓ Cloud Foundry environment detected"
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
        echo "✓ Non-CF environment - checking for AWS credentials"
        if [ -z "$BUCKET_NAME" ]; then
            echo "⚠ BUCKET_NAME not set - some tests may fail"
        fi
    fi
}

# Function to test configuration loading
test_config_loading() {
    local config_file="./scripts/tome-backup.conf"

    check_file "$config_file" "backup configuration file" || return 1

    # Source the config
    source "$config_file"

    # Check required variables are set
    local required_vars="BACKUP_RETENTION_DAYS ENABLE_AUTO_BACKUPS ENABLE_AUTO_CLEANUP BACKUP_PREFIX ENABLE_SMART_PUBLIC_BACKUP"
    for var in $required_vars; do
        if [ -n "${!var}" ]; then
            echo "✓ Config variable $var = ${!var}"
        else
            echo "✗ Config variable $var is not set"
            return 1
        fi
    done

    return 0
}

# Function to test script files existence and permissions
test_script_files() {
    # Check tome-sync.sh
    check_file "./scripts/tome-sync.sh" "script file" || return 1
    if [ -x "./scripts/tome-sync.sh" ]; then
        echo "✓ Script is executable: ./scripts/tome-sync.sh"
    else
        echo "✗ Script is not executable: ./scripts/tome-sync.sh"
        return 1
    fi

    # Check tome-backup-manager.sh
    check_file "./scripts/tome-backup-manager.sh" "script file" || return 1
    if [ -x "./scripts/tome-backup-manager.sh" ]; then
        echo "✓ Script is executable: ./scripts/tome-backup-manager.sh"
    else
        echo "✗ Script is not executable: ./scripts/tome-backup-manager.sh"
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
                        echo "✓ Using 'md5' instead of 'md5sum'"
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
        echo "✗ Missing required commands:$missing_commands"
        return 1
    fi
}

# Function to test AWS connectivity
test_aws_connectivity() {
    if [ -z "$BUCKET_NAME" ]; then
        echo "⚠ BUCKET_NAME not set - skipping AWS connectivity test"
        return 0
    fi

    echo "Testing AWS S3 connectivity to bucket: $BUCKET_NAME"

    # Test basic S3 access
    if aws s3 ls "s3://$BUCKET_NAME/" $S3_EXTRA_PARAMS >/dev/null 2>&1; then
        echo "✓ Successfully connected to S3 bucket"
    else
        echo "✗ Failed to connect to S3 bucket"
        return 1
    fi

    # Test specific backup directories
    for dir in web-backup public_backup; do
        aws s3 ls "s3://$BUCKET_NAME/$dir/" $S3_EXTRA_PARAMS >/dev/null 2>&1
        if [ $? -eq 0 ]; then
            echo "✓ Backup directory exists: $dir"
        else
            echo "✓ Backup directory will be created when needed: $dir"
        fi
    done

    return 0
}

# Function to test backup integration in tome-sync.sh
test_backup_integration() {
    local tome_sync_script="./scripts/tome-sync.sh"

    # Check for backup-related code in tome-sync.sh
    for pattern in "tome-backup.conf" "ENABLE_AUTO_BACKUPS" "ENABLE_SMART_PUBLIC_BACKUP" "Creating automatic backups" "web-backup" "public_backup" "BACKUP_PREFIX"; do
        if grep -q "$pattern" "$tome_sync_script"; then
            echo "✓ Found backup integration: $pattern"
        else
            echo "✗ Missing backup integration: $pattern"
            return 1
        fi
    done

    return 0
}

# Function to test backup manager functionality
test_backup_manager() {
    local manager_script="./scripts/tome-backup-manager.sh"

    # Test help functionality (backup manager shows usage and exits with code 1, which is expected)
    "$manager_script" >/dev/null 2>&1
    local exit_code=$?
    if [ $exit_code -eq 1 ] || [ $exit_code -eq 0 ]; then
        echo "✓ Backup manager script runs and shows usage correctly"
    else
        echo "✗ Backup manager script has execution errors (exit code: $exit_code)"
        return 1
    fi

    # Check for required functions in the script
    for func in "list_backups" "list_old_backups" "clean_old_backups" "backup_info" "restore_backup"; do
        if grep -q "$func" "$manager_script"; then
            echo "✓ Found backup manager function: $func"
        else
            echo "✗ Missing backup manager function: $func"
            return 1
        fi
    done

    return 0
}

# Function to test date calculations (important for cleanup)
test_date_calculations() {
    echo "Testing date calculation compatibility..."

    # Test both Linux and macOS date formats
    local test_days=7
    local cutoff_date_linux=$(date -u -d "${test_days} days ago" '+%Y_%m_%d' 2>/dev/null)
    local cutoff_date_macos=$(date -u -v-${test_days}d '+%Y_%m_%d' 2>/dev/null)

    if [ -n "$cutoff_date_linux" ]; then
        echo "✓ Linux date format works: $cutoff_date_linux"
        return 0
    elif [ -n "$cutoff_date_macos" ]; then
        echo "✓ macOS date format works: $cutoff_date_macos"
        return 0
    else
        echo "✗ Neither date format works"
        return 1
    fi
}

# Function to test backup naming pattern
test_backup_naming() {
    source "./scripts/tome-backup.conf"

    local test_space="test"
    local test_timestamp="2024_03_15_14_30_00"
    local expected_pattern="${BACKUP_PREFIX}-${test_space}-${test_timestamp}"

    echo "Testing backup naming pattern: $expected_pattern"

    # Test pattern matching (used in cleanup)
    if echo "$expected_pattern" | grep -q "${BACKUP_PREFIX}-[^/]*-[0-9_]*"; then
        echo "✓ Backup naming pattern matches cleanup regex"
    else
        echo "✗ Backup naming pattern doesn't match cleanup regex"
        return 1
    fi

    # Test date extraction
    local extracted_date=$(echo "$expected_pattern" | grep -o "${BACKUP_PREFIX}-[^/]*-[0-9_]*" | tail -c 11 | head -c 10)
    if [ "$extracted_date" = "2024_03_15" ]; then
        echo "✓ Date extraction works correctly: $extracted_date"
    else
        echo "✗ Date extraction failed: $extracted_date"
        return 1
    fi

    return 0
}

# Function to simulate a backup scenario (dry run)
test_backup_simulation() {
    if [ -z "$BUCKET_NAME" ]; then
        echo "⚠ BUCKET_NAME not set - skipping backup simulation"
        return 0
    fi

    echo "Simulating backup process (dry run)..."

    # Test if we can list current web and public directories
    aws s3 ls "s3://$BUCKET_NAME/web/" $S3_EXTRA_PARAMS >/dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "✓ Can access current static site directory"
    else
        echo "⚠ Cannot access static site directory (may not exist yet)"
    fi

    aws s3 ls "s3://$BUCKET_NAME/cms/public/" $S3_EXTRA_PARAMS >/dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "✓ Can access current public files directory"
    else
        echo "⚠ Cannot access public files directory (may not exist yet)"
    fi

    # Test if we can create a test file and copy it (actual backup simulation)
    local test_file="/tmp/backup_test_$(date +%s).txt"
    echo "backup test" > "$test_file"

    local test_backup_path="s3://$BUCKET_NAME/backup-test/test-backup/"
    aws s3 cp "$test_file" "$test_backup_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "✓ Can perform S3 copy operations (backup simulation successful)"
        # Clean up test file
        aws s3 rm "$test_backup_path$(basename "$test_file")" $S3_EXTRA_PARAMS >/dev/null 2>&1
        aws s3 rm "s3://$BUCKET_NAME/backup-test/" --recursive $S3_EXTRA_PARAMS >/dev/null 2>&1
    else
        echo "✗ Cannot perform S3 copy operations"
        return 1
    fi

    rm -f "$test_file"
    return 0
}

# Function to test log directory accessibility
test_log_directory() {
    local log_dir="/tmp/tome-log"

    if [ ! -d "$log_dir" ]; then
        echo "Creating log directory: $log_dir"
        mkdir -p "$log_dir" 2>/dev/null
    fi

    if [ -d "$log_dir" ] && [ -w "$log_dir" ]; then
        echo "✓ Log directory is accessible and writable: $log_dir"
        return 0
    else
        echo "✗ Log directory is not accessible or writable: $log_dir"
        return 1
    fi
}

# Main execution
main() {
    print_status $BLUE "============================================"
    print_status $BLUE "     Tome Backup System Test Suite"
    print_status $BLUE "============================================"

    echo ""
    print_status $YELLOW "Setting up test environment..."
    setup_test_env

    # Run all tests
    run_test "Configuration File Loading" "test_config_loading"
    run_test "Script Files and Permissions" "test_script_files"
    run_test "Required Dependencies" "test_dependencies"
    run_test "AWS Connectivity" "test_aws_connectivity"
    run_test "Backup Integration in tome-sync.sh" "test_backup_integration"
    run_test "Backup Manager Functionality" "test_backup_manager"
    run_test "Date Calculations" "test_date_calculations"
    run_test "Backup Naming Pattern" "test_backup_naming"
    run_test "Backup Simulation" "test_backup_simulation"
    run_test "Log Directory Access" "test_log_directory"

    # Test results summary
    echo ""
    print_status $BLUE "============================================"
    print_status $BLUE "              TEST RESULTS"
    print_status $BLUE "============================================"

    echo "Total Tests: $TESTS_TOTAL"
    print_status $GREEN "Tests Passed: $TESTS_PASSED"
    print_status $RED "Tests Failed: $TESTS_FAILED"

    if [ $TESTS_FAILED -eq 0 ]; then
        echo ""
        print_status $GREEN "🎉 ALL TESTS PASSED! The backup system is ready for use."
        echo ""
        print_status $YELLOW "Next steps:"
        echo "1. Run a Tome sync to test automatic backups in action"
        echo "2. Monitor the logs for backup creation messages"
        echo "3. Use './scripts/tome-backup-manager.sh list' to see created backups"
        return 0
    else
        echo ""
        print_status $RED "❌ SOME TESTS FAILED. Please fix the issues above before using the backup system."
        echo ""
        print_status $YELLOW "Common fixes:"
        echo "1. Ensure all dependencies are installed (aws cli, jq, etc.)"
        echo "2. Verify AWS credentials and S3 access"
        echo "3. Check file permissions on script files"
        return 1
    fi
}

# Check if script is being run from the correct directory
if [ ! -f "./scripts/tome-sync.sh" ]; then
    print_status $RED "Error: This script must be run from the project root directory"
    print_status $YELLOW "Usage: cd /path/to/usagov-2021 && ./scripts/test-backup-system.sh"
    exit 1
fi

# Run main function
main "$@"