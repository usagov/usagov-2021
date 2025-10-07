#!/bin/sh

# Cron Setup Script for Database Backups
# Sets up daily database backups to run at 7pm EST

SCRIPT_PATH=$(dirname "$0")

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Load configuration
CONFIG_FILE="$SCRIPT_PATH/tome-backup.conf"
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

print_status $YELLOW "Setting up daily database backups..."
echo "Backup time: ${DB_BACKUP_TIME} EST (${DB_HOUR}:${DB_MINUTE})"
echo "Backup script: $DB_BACKUP_SCRIPT"

# Check if we're in a Cloud Foundry environment
if [ -n "$VCAP_APPLICATION" ]; then
    print_status $YELLOW "Cloud Foundry environment detected"
    
    # In CF with CMS container, we integrate with the existing Alpine cron system
    # The CMS container already has cron running with /etc/periodic/ structure
    
    # Check if we're in the CMS container (has the existing static site cron)
    if [ -f "/etc/periodic/1min/generate-static-site" ]; then
        print_status $YELLOW "CMS container detected - integrating with existing cron system"
        
        # Create database backup script in the periodic directory
        # Since we want daily backups at a specific time, we'll create a script
        # that checks the time and only runs at the specified hour/minute
        
        DB_CRON_SCRIPT="/etc/periodic/1min/database-backup"
        
        cat > "$DB_CRON_SCRIPT" << EOF
#!/bin/sh

# Database backup cron integration
# Runs within the existing CMS container cron system
# Only executes at the configured time: $DB_BACKUP_TIME EST

# Only the 1st instance within cloud formation should do backups
if [ "\${CF_INSTANCE_INDEX:-''}" != "0" ]; then
    exit 0
fi

# Check if it's time to run the backup
CURRENT_HOUR=\$(date +%H)
CURRENT_MINUTE=\$(date +%M)

# Remove leading zeros to avoid octal interpretation
CURRENT_HOUR=\$(echo "\$CURRENT_HOUR" | sed 's/^0*//')
CURRENT_MINUTE=\$(echo "\$CURRENT_MINUTE" | sed 's/^0*//')
TARGET_HOUR=$DB_HOUR
TARGET_MINUTE=$DB_MINUTE

# Remove leading zeros from target time too
TARGET_HOUR=\$(echo "\$TARGET_HOUR" | sed 's/^0*//')
TARGET_MINUTE=\$(echo "\$TARGET_MINUTE" | sed 's/^0*//')

# Set defaults if empty after removing zeros
CURRENT_HOUR=\${CURRENT_HOUR:-0}
CURRENT_MINUTE=\${CURRENT_MINUTE:-0}
TARGET_HOUR=\${TARGET_HOUR:-0}
TARGET_MINUTE=\${TARGET_MINUTE:-0}

if [ "\$CURRENT_HOUR" -eq "\$TARGET_HOUR" ] && [ "\$CURRENT_MINUTE" -eq "\$TARGET_MINUTE" ]; then
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
print_status $YELLOW "you may need to adjust the DB_BACKUP_TIME setting in tome-backup.conf"