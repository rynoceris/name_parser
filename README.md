# Database Name Parser System

This system extends your existing name parsing functionality to work directly with your MariaDB database, automatically parsing the `full_name` field in the `csd_staff` table into separate components: `honorific`, `first_name`, `last_name`, and `suffix`.

## Features

- **Database Integration**: Direct integration with your existing MariaDB database
- **Batch Processing**: Efficiently processes large datasets in configurable batches
- **Incremental Updates**: Daily cron job processes only new records
- **Safety First**: Automatic database backups before any modifications
- **Comprehensive Logging**: Detailed logs for monitoring and debugging
- **Error Handling**: Robust error handling with email notifications
- **Statistics Tracking**: Monitor parsing progress and completion rates

## File Structure

```
/home/collegesportsdir/public_html/name-parser/
├── name_parser.php              # Your existing name parsing logic
├── database_name_parser.php     # Main database integration class
├── cron_name_parser.php         # Daily cron job script
├── setup.php                    # Interactive setup script
├── vendor/                      # Composer dependencies
├── logs/                        # Log files directory
├── backups/                     # Database backup directory
└── last_run.txt                 # Timestamp of last successful run
```

## Prerequisites

1. **PHP**: Version 7.4 or higher
2. **Composer**: For dependency management
3. **MariaDB/MySQL**: Database with `csd_staff` table
4. **Environment File**: Configuration at `/home/collegesportsdir/config/.env`

## Installation

### Step 1: Install Dependencies

```bash
cd /home/collegesportsdir/public_html/name-parser
composer require vlucas/phpdotenv
```

### Step 2: Verify Environment Configuration

Ensure your `.env` file at `/home/collegesportsdir/config/.env` contains:

```
DB_HOST=localhost
DB_NAME=collegesportsdir_live
DB_USER=collegesportsdir_live
DB_PASSWORD={your_password_here}
```

### Step 3: Run Interactive Setup

```bash
php setup.php
```

This will guide you through:
- Database connection testing
- Creating backups
- Adding required columns
- Initial data processing (optional)

## Usage

### Command Line Operations

#### Initialize Database (First Time Only)
```bash
php database_name_parser.php init
```

#### Process All Records
```bash
# Process all records with default batch size (1000)
php database_name_parser.php full

# Process with custom batch size
php database_name_parser.php full 500
```

#### Process New Records Only
```bash
# Process records added since last run
php database_name_parser.php new

# Process records since specific date
php database_name_parser.php new "2024-01-01 00:00:00"
```

#### Test Parser
```bash
# Test on 10 random records
php database_name_parser.php test

# Test on specific number of records
php database_name_parser.php test 25
```

#### View Statistics
```bash
php database_name_parser.php stats
```

#### Create Manual Backup
```bash
php database_name_parser.php backup
```

### Setting Up Cron Job

#### Option 1: Daily at 2:00 AM (Recommended)
```bash
crontab -e
```

Add this line:
```
0 2 * * * /usr/bin/php /home/collegesportsdir/public_html/name-parser/cron_name_parser.php >> /home/collegesportsdir/public_html/name-parser/logs/cron.log 2>&1
```

#### Option 2: Hourly (For Testing)
```
0 * * * * /usr/bin/php /home/collegesportsdir/public_html/name-parser/cron_name_parser.php >> /home/collegesportsdir/public_html/name-parser/logs/cron.log 2>&1
```

## Database Schema Changes

The system automatically adds these columns to your `csd_staff` table:

```sql
ALTER TABLE csd_staff ADD COLUMN honorific VARCHAR(100) DEFAULT NULL;
ALTER TABLE csd_staff ADD COLUMN first_name VARCHAR(100) DEFAULT NULL;
ALTER TABLE csd_staff ADD COLUMN last_name VARCHAR(100) DEFAULT NULL;
ALTER TABLE csd_staff ADD COLUMN suffix VARCHAR(100) DEFAULT NULL;
```

## Parsing Examples

| Original Name | Honorific | First Name | Last Name | Suffix |
|---------------|-----------|------------|-----------|---------|
| Dr. James D. Smith, Jr. | Dr. | James | Smith | Jr. |
| Mary Johnson | | Mary | Johnson | |
| Prof. Sarah van der Berg | Prof. | Sarah | van der Berg | |
| John Paul Williams III | | John Paul | Williams | III |

## Monitoring and Logs

### Log Files Location
- **Daily Logs**: `/home/collegesportsdir/public_html/name-parser/logs/name_parser_YYYY-MM-DD.log`
- **Cron Logs**: `/home/collegesportsdir/public_html/name-parser/logs/cron.log`
- **Error Logs**: `/home/collegesportsdir/public_html/name-parser/logs/cron_errors.log`

### Email Notifications

The cron job sends email notifications for:
- Processing errors
- Large batch processing (>100 records)
- Daily summary (if records were processed)

Update the email address in `cron_name_parser.php`:
```php
$to = 'your-admin-email@collegesportsdirectory.com';
```

## Performance Considerations

### Batch Processing
- Default batch size: 1000 records
- Adjust based on server capacity and memory
- Larger batches = faster processing but more memory usage

### Resource Usage
- Set appropriate time limits for large datasets
- Monitor memory usage during initial full processing
- Consider running during off-peak hours

## Troubleshooting

### Common Issues

#### 1. Database Connection Failed
**Error**: "Database connection failed"
**Solution**: 
- Verify `.env` file location and contents
- Check database credentials
- Ensure database server is running

#### 2. Permission Denied
**Error**: Permission denied for log files or backups
**Solution**:
```bash
# Create directories with proper permissions
mkdir -p /home/collegesportsdir/public_html/name-parser/logs
mkdir -p /home/collegesportsdir/public_html/name-parser/backups
chmod 755 /home/collegesportsdir/public_html/name-parser/logs
chmod 755 /home/collegesportsdir/public_html/name-parser/backups
```

#### 3. Composer Dependencies Missing
**Error**: "vendor/autoload.php not found"
**Solution**:
```bash
cd /home/collegesportsdir/public_html/name-parser
composer install
```

#### 4. Memory Limit Exceeded
**Error**: "Fatal error: Allowed memory size exhausted"
**Solution**: Reduce batch size or increase PHP memory limit:
```bash
php -d memory_limit=512M database_name_parser.php full 500
```

### Debugging

#### Enable Debug Mode
Add to the beginning of `database_name_parser.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

#### Check Parsing Results
```bash
# Test parser on sample data
php database_name_parser.php test 10

# Check database directly
mysql -u collegesportsdir_live -p collegesportsdir_live
SELECT id, full_name, honorific, first_name, last_name, suffix 
FROM csd_staff 
WHERE first_name IS NOT NULL 
LIMIT 10;
```

## Maintenance

### Regular Tasks

#### Weekly
- Review log files for errors
- Check parsing statistics
- Verify cron job is running

#### Monthly
- Clean up old log files (automated)
- Review parsing accuracy
- Update email notification settings if needed

#### As Needed
- Create manual backups before major changes
- Test parser on new data patterns
- Adjust batch sizes based on performance

### Backup Strategy

#### Automatic Backups
- Created before each full processing run
- Stored in `/home/collegesportsdir/public_html/name-parser/backups/`
- Named with timestamp: `csd_staff_backup_YYYY-MM-DD_HH-MM-SS.sql`

#### Manual Backup
```bash
php database_name_parser.php backup
```

#### Restore from Backup
```bash
mysql -u collegesportsdir_live -p collegesportsdir_live < /path/to/backup.sql
```

## Security Considerations

1. **File Permissions**: Ensure log and backup directories are not web-accessible
2. **Database Credentials**: Keep `.env` file secure and outside web root
3. **Log Rotation**: Old logs are automatically cleaned up (30-day retention)
4. **Error Messages**: Sensitive information is logged to files, not displayed

## Support

For issues or questions:

1. Check log files for specific error messages
2. Verify configuration and permissions
3. Test with small batches first
4. Review this documentation for common solutions

## Version History

- **v1.0**: Initial database integration system
  - Basic parsing functionality
  - Batch processing
  - Cron job support
  - Comprehensive logging
  - Safety backups