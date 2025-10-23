#!/bin/sh

# Setup Backup Cron Jobs
# Configures cron jobs for the unified backup manager

# Load common utilities
SCRIPT_DIR=$(dirname "$0")
. "$SCRIPT_DIR/common.sh"

# Initialize backup system (sets PROJECT_ROOT, BACKUP_DIR, CONFIG_FILE and loads config)
init_backup_system

# Set defaults
DB_BACKUP_TIME=${DB_BACKUP_TIME:-"19:00"}
ENABLE_DB_BACKUPS=${ENABLE_DB_BACKUPS:-true}

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

    print_status $GREEN "✅ Cron job setup complete"
}

remove_cron() {
    print_status $YELLOW "Removing backup cron jobs..."
    crontab -l 2>/dev/null | grep -v "backup/manager.sh" | crontab -
    print_status $GREEN "✅ Backup cron jobs removed"
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

    print_status $GREEN "✅ Automatic backup manager found"

    # Test database backup
    print_status $YELLOW "Testing database backup..."
    if "$BACKUP_DIR/manager.sh" backup db; then
        print_status $GREEN "✅ Database backup test successful"
    else
        print_status $RED "❌ Database backup test failed"
        return 1
    fi

    print_status $GREEN "🎉 All tests passed!"
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