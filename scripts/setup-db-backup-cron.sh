#!/bin/sh

# Database Backup Cron Setup

SCRIPT_PATH=$(dirname "$0")

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Load configuration
CONFIG_FILE="$SCRIPT_PATH/auto-backup-system.conf"
if [ -f "$CONFIG_FILE" ]; then
    . "$CONFIG_FILE"
else
    print_status $RED "ERROR: Configuration file not found: $CONFIG_FILE"
    exit 1
fi

# Set defaults
ENABLE_DB_BACKUPS=${ENABLE_DB_BACKUPS:-true}
DB_BACKUP_TIME=${DB_BACKUP_TIME:-"19:00"}

# Check if database backups are enabled
if [ "$ENABLE_DB_BACKUPS" != "true" ]; then
    print_status $YELLOW "Database backups are disabled in configuration"
    exit 0
fi

# Extract hour and minute from time
DB_HOUR=$(echo "$DB_BACKUP_TIME" | cut -d':' -f1)
DB_MINUTE=$(echo "$DB_BACKUP_TIME" | cut -d':' -f2)

# Validate time format
if [ -z "$DB_HOUR" ] || [ -z "$DB_MINUTE" ]; then
    print_status $RED "ERROR: Invalid time format in DB_BACKUP_TIME: $DB_BACKUP_TIME"
    print_status $YELLOW "Expected format: HH:MM (24-hour)"
    exit 1
fi

# Determine the absolute path to the backup script
DB_BACKUP_SCRIPT="$SCRIPT_PATH/db-backup-daily.sh"
if [ ! -f "$DB_BACKUP_SCRIPT" ]; then
    print_status $RED "ERROR: Database backup script not found: $DB_BACKUP_SCRIPT"
    exit 1
fi

# Get absolute path
DB_BACKUP_SCRIPT=$(cd "$(dirname "$DB_BACKUP_SCRIPT")" && pwd)/$(basename "$DB_BACKUP_SCRIPT")

print_status $YELLOW "Setting up DB backups at ${DB_BACKUP_TIME} EST"

# Check if we're in a Cloud Foundry environment
if [ -n "$VCAP_APPLICATION" ]; then
    print_status $YELLOW "Cloud Foundry detected"

    # Check if we're in the CMS container
    if [ -f "/etc/periodic/1min/generate-static-site" ]; then
        print_status $YELLOW "CMS container - integrating with existing cron"

        # Create database backup script in the periodic directory
        # Since we want daily backups at a specific time, we'll create a script
        # that checks the time and only runs at the specified hour/minute

        DB_CRON_SCRIPT="/etc/periodic/1min/database-backup"

        cat > "$DB_CRON_SCRIPT" << EOF
#!/bin/sh

# Database backup cron - runs at $DB_BACKUP_TIME EST

# Only primary instance runs backups
if [ "\${CF_INSTANCE_INDEX:-''}" != "0" ]; then
    exit 0
fi
# Convert EST time to UTC since Cloud Foundry containers run in UTC
# EST is UTC-5, EDT is UTC-4. We'll use a simple approach:
# Check if we're in EST (Nov-Mar) or EDT (Mar-Nov) period

# Get current time in UTC
CURRENT_HOUR_UTC=\$(date +%H)
CURRENT_MINUTE_UTC=\$(date +%M)

# Convert target EST time to UTC
# For simplicity, we'll assume EST (UTC-5) year-round
# EST 19:00 = UTC 00:00 next day (19 + 5 = 24 = 0)
TARGET_HOUR_EST=$DB_HOUR
TARGET_MINUTE_EST=$DB_MINUTE
TARGET_HOUR_UTC=\$((TARGET_HOUR_EST + 5))

# Handle day rollover (EST evening = UTC next day)
if [ \$TARGET_HOUR_UTC -ge 24 ]; then
    TARGET_HOUR_UTC=\$((TARGET_HOUR_UTC - 24))
fi

# Remove leading zeros to avoid octal interpretation
CURRENT_HOUR_UTC=\$(echo "\$CURRENT_HOUR_UTC" | sed 's/^0*//')
CURRENT_MINUTE_UTC=\$(echo "\$CURRENT_MINUTE_UTC" | sed 's/^0*//')
TARGET_HOUR_UTC=\$(echo "\$TARGET_HOUR_UTC" | sed 's/^0*//')
TARGET_MINUTE_UTC=\$(echo "\$TARGET_MINUTE_EST" | sed 's/^0*//')

# Set defaults if empty after removing zeros
CURRENT_HOUR_UTC=\${CURRENT_HOUR_UTC:-0}
CURRENT_MINUTE_UTC=\${CURRENT_MINUTE_UTC:-0}
TARGET_HOUR_UTC=\${TARGET_HOUR_UTC:-0}
TARGET_MINUTE_UTC=\${TARGET_MINUTE_UTC:-0}

if [ "\$CURRENT_HOUR_UTC" -eq "\$TARGET_HOUR_UTC" ] && [ "\$CURRENT_MINUTE_UTC" -eq "\$TARGET_MINUTE_UTC" ]; then
    # Ensure log directory exists
    mkdir -p /tmp/tome-log

    # Change to the correct directory
    cd /var/www

    # Run the database backup
    $DB_BACKUP_SCRIPT >> /tmp/tome-log/db-backup-cron.log 2>&1
fi
EOF

        chmod +x "$DB_CRON_SCRIPT"

        print_status $GREEN "Created database backup integration: $DB_CRON_SCRIPT"
        print_status $GREEN "Backup time: ${DB_BACKUP_TIME} EST"
        print_status $YELLOW "Database backups will run within the existing CMS container cron system"
        print_status $YELLOW "The backup will execute daily at ${DB_BACKUP_TIME} EST"

    else
        print_status $YELLOW "Generic Cloud Foundry environment - using standard cron setup"

        # Fallback to standard CF cron setup for non-CMS containers
        CRON_DIR="/var/www/scripts/cron"
        mkdir -p "$CRON_DIR"

        # Create cron job file
        CRON_JOB_FILE="$CRON_DIR/db-backup"
        cat > "$CRON_JOB_FILE" << EOF
#!/bin/sh
# Daily database backup cron job
# Runs at $DB_BACKUP_TIME EST

# Only the 1st instance should run backups
if [ "\${CF_INSTANCE_INDEX:-''}" != "0" ]; then
    exit 0
fi

# Change to the correct directory
cd /var/www

# Run the database backup
$DB_BACKUP_SCRIPT >> /tmp/tome-log/db-backup-cron.log 2>&1
EOF

        chmod +x "$CRON_JOB_FILE"

        # Create crontab entry
        CRONTAB_ENTRY="$DB_MINUTE $DB_HOUR * * * $CRON_JOB_FILE"

        print_status $GREEN "Created cron job file: $CRON_JOB_FILE"
        print_status $GREEN "Crontab entry: $CRONTAB_ENTRY"

        # Add to crontab if crontab command is available
        if command -v crontab >/dev/null 2>&1; then
            # Check if cron entry already exists
            if crontab -l 2>/dev/null | grep -q "db-backup"; then
                print_status $YELLOW "Database backup cron job already exists"
            else
                # Add the new cron job
                (crontab -l 2>/dev/null; echo "$CRONTAB_ENTRY") | crontab -
                print_status $GREEN "Added database backup to crontab"
            fi
        else
            print_status $YELLOW "crontab command not available - manual setup required"
            print_status $YELLOW "Please add this line to your crontab:"
            print_status $YELLOW "$CRONTAB_ENTRY"
        fi
    fi

else
    print_status $YELLOW "Local environment detected"

    # For local development, just show the crontab entry
    CRONTAB_ENTRY="$DB_MINUTE $DB_HOUR * * * cd $(pwd) && $DB_BACKUP_SCRIPT >> /tmp/tome-log/db-backup-cron.log 2>&1"

    print_status $YELLOW "To set up the cron job, add this line to your crontab:"
    print_status $GREEN "$CRONTAB_ENTRY"
    print_status $YELLOW ""
    print_status $YELLOW "Run 'crontab -e' and add the above line to enable daily backups"
fi

print_status $GREEN "Database backup cron setup completed!"
print_status $YELLOW ""
print_status $YELLOW "Note: Time is configured for EST. If your server is in a different timezone,"
print_status $YELLOW "you may need to adjust the DB_BACKUP_TIME setting in auto-backup-system.conf"