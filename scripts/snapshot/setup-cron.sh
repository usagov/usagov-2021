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
    echo "  test        Test the exact cron command (simulates cron environment)"
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

    # Validate time format
    if [ -z "$hour" ] || [ -z "$minute" ] || [ "$hour" -lt 0 ] || [ "$hour" -gt 23 ] || [ "$minute" -lt 0 ] || [ "$minute" -gt 59 ]; then
        print_status $RED "❌ Invalid time format: $DB_BACKUP_TIME (use HH:MM format)"
        return 1
    fi

    # Convert Eastern Time to UTC (EST is UTC-5, EDT is UTC-4)
    # Note: This is a simplified conversion. For production, consider using a proper timezone library
    # In most cases, Cloud.gov servers run in UTC anyway, so this conversion may not be needed
    utc_hour=$((hour + 5))
    if [ $utc_hour -ge 24 ]; then
        utc_hour=$((utc_hour - 24))
    fi

    print_status $GREEN "Setting up database backup cron job..."
    print_status $YELLOW "⚠️ Time conversion: ${hour}:${minute} Eastern → ${utc_hour}:${minute} UTC"
    print_status $YELLOW "📝 Note: This assumes EST (UTC-5). Adjust manually for EDT if needed."

    # Remove existing backup cron jobs first
    crontab -l 2>/dev/null | grep -v "snapshot/manager.sh" | crontab -

    # Determine working directory for cron job
    CRON_WORK_DIR="/var/www"
    if [ -n "$VCAP_APPLICATION" ] && [ -d "/var/www" ]; then
        CRON_WORK_DIR="/var/www"
    else
        CRON_WORK_DIR="$PROJECT_ROOT"
    fi

    # Add new cron job (using new command format)
    (crontab -l 2>/dev/null; echo "$minute $utc_hour * * * cd $CRON_WORK_DIR && $BACKUP_DIR/manager.sh backup db >/dev/null 2>&1") | crontab -

    print_status $GREEN "✅ Cron job setup complete"
}

remove_cron() {
    print_status $YELLOW "Removing backup cron jobs..."
    crontab -l 2>/dev/null | grep -v "snapshot/manager.sh" | crontab -
    print_status $GREEN "✅ Backup cron jobs removed"
}

show_status() {
    print_status $GREEN "Current backup cron jobs:"
    echo "========================="
    crontab -l 2>/dev/null | grep "snapshot/manager" || echo "No backup cron jobs found"
    echo ""
    print_status $GREEN "Configuration:"
    echo "  Database backups enabled: $ENABLE_DB_BACKUPS"
    echo "  Backup time: $DB_BACKUP_TIME EST"
}

test_cron_command() {
    print_status $YELLOW "Testing cron command execution..."
    print_status $BLUE "This simulates the exact environment and command that cron will use"
    echo ""

    # Determine working directory (same as setup_cron)
    CRON_WORK_DIR="/var/www"
    if [ -n "$VCAP_APPLICATION" ] && [ -d "/var/www" ]; then
        CRON_WORK_DIR="/var/www"
    else
        CRON_WORK_DIR="$PROJECT_ROOT"
    fi

    # Get the actual cron command
    CRON_CMD=$(crontab -l 2>/dev/null | grep "snapshot/manager.sh backup db" | grep -v "^#")

    if [ -z "$CRON_CMD" ]; then
        print_status $RED "❌ No cron job found! Run 'setup-cron.sh setup' first."
        return 1
    fi

    print_status $GREEN "Found cron job:"
    echo "  $CRON_CMD"
    echo ""

    # Extract just the command part (everything after the time fields)
    # Cron format: minute hour day month weekday command
    JUST_COMMAND=$(echo "$CRON_CMD" | awk '{for(i=6;i<=NF;i++) printf "%s ", $i; print ""}')

    # Strip output redirects for testing so we can see errors
    JUST_COMMAND=$(echo "$JUST_COMMAND" | sed 's/ *>[^ ]* *2>&1 *$//' | sed 's/ *2>&1 *$//')

    print_status $YELLOW "Executing cron command in minimal environment..."
    print_status $BLUE "Command: $JUST_COMMAND"
    echo ""

    # Execute the command in a minimal environment similar to cron
    # Cron in Cloud Foundry has access to VCAP_* environment variables
    # But we need to include /var/www/vendor/bin for drush
    env -i \
        HOME="$HOME" \
        SHELL=/bin/sh \
        PATH=/var/www/vendor/bin:/usr/local/bin:/usr/bin:/bin \
        USER="$USER" \
        VCAP_SERVICES="$VCAP_SERVICES" \
        VCAP_APPLICATION="$VCAP_APPLICATION" \
        sh -c "$JUST_COMMAND"

    EXIT_CODE=$?

    echo ""
    if [ $EXIT_CODE -eq 0 ]; then
        print_status $GREEN "✅ Cron command test successful!"
        print_status $GREEN "The automated backup should work when cron triggers it."
    else
        print_status $RED "❌ Cron command test failed with exit code: $EXIT_CODE"
        print_status $YELLOW "Check the output above for errors."
        return 1
    fi
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
        test_cron_command
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