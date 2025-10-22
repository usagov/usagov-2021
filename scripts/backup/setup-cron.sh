#!/bin/sh

# Setup Backup Cron Jobs
# Configures cron jobs for the unified backup manager

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
        echo "ERROR: Cannot find scripts/backup directory. Please run from project root or scripts/backup directory."
        exit 1
    fi
fi

CONFIG_FILE="$BACKUP_DIR/backup-system.conf"

# Load configuration
if [ -f "$CONFIG_FILE" ]; then
    . "$CONFIG_FILE"
else
    echo "Error: Configuration file not found: $CONFIG_FILE"
    exit 1
fi

# Set defaults
DB_BACKUP_TIME=${DB_BACKUP_TIME:-"19:00"}
ENABLE_DB_BACKUPS=${ENABLE_DB_BACKUPS:-true}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

show_usage() {
    echo "Usage: $0 [command]"
    echo ""
    echo "Commands:"
    echo "  setup       Setup database backup cron job"
    echo "  remove      Remove backup cron jobs"
    echo "  status      Show current cron jobs"
    echo "  test        Test the backup system"
    echo ""
}

setup_cron() {
    if [ "$ENABLE_DB_BACKUPS" != "true" ]; then
        print_status $YELLOW "Database backups are disabled in configuration"
        return 1
    fi

    # Parse backup time
    hour=$(echo "$DB_BACKUP_TIME" | cut -d: -f1)
    minute=$(echo "$DB_BACKUP_TIME" | cut -d: -f2)

    # Convert EST to UTC (EST is UTC-5, simple conversion)
    utc_hour=$((hour + 5))
    if [ $utc_hour -ge 24 ]; then
        utc_hour=$((utc_hour - 24))
    fi

    print_status $GREEN "Setting up database backup cron job..."
    print_status $YELLOW "Schedule: $utc_hour:$minute UTC (${hour}:${minute} EST)"

    # Remove existing backup cron jobs first
    crontab -l 2>/dev/null | grep -v "backup/manager.sh" | crontab -

    # Add new cron job (using new command format)
    (crontab -l 2>/dev/null; echo "$minute $utc_hour * * * cd /var/www && $BACKUP_DIR/manager.sh backup db >/dev/null 2>&1") | crontab -
    
    print_status $GREEN "✓ Cron job setup complete"
}

remove_cron() {
    print_status $YELLOW "Removing backup cron jobs..."
    crontab -l 2>/dev/null | grep -v "backup/manager.sh" | crontab -
    print_status $GREEN "✓ Backup cron jobs removed"
}

show_status() {
    print_status $GREEN "Current backup cron jobs:"
    echo "========================="
    crontab -l 2>/dev/null | grep "backup/manager" || echo "No backup cron jobs found"
    echo ""
    print_status $GREEN "Configuration:"
    echo "  Database backups enabled: $ENABLE_DB_BACKUPS"
    echo "  Backup time: $DB_BACKUP_TIME EST"
}

test_backup() {
    print_status $YELLOW "Testing automatic backup manager..."

    if [ ! -x "$BACKUP_DIR/manager.sh" ]; then
        print_status $RED "Error: manager.sh not found or not executable at $BACKUP_DIR"
        exit 1
    fi

    print_status $GREEN "✓ Automatic backup manager found"

    # Test database backup
    print_status $YELLOW "Testing database backup..."
    if "$BACKUP_DIR/manager.sh" backup db; then
        print_status $GREEN "✓ Database backup test successful"
    else
        print_status $RED "✗ Database backup test failed"
        return 1
    fi

    print_status $GREEN "All tests passed!"
}

# Parse command
COMMAND=${1:-status}

case $COMMAND in
    setup)
        setup_cron
        ;;
    remove)
        remove_cron
        ;;
    status)
        show_status
        ;;
    test)
        test_backup
        ;;
    help|--help|-h)
        show_usage
        ;;
    *)
        print_status $RED "Unknown command: $COMMAND"
        show_usage
        exit 1
        ;;
esac