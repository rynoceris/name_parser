<?php
/**
 * Database Name Parser - Complete Setup Script
 * 
 * This script handles everything from installation to database setup
 */

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
	die("This script must be run from the command line.\n");
}

echo "Database Name Parser - Complete Setup\n";
echo "====================================\n\n";

// Change to script directory
chdir('/home/collegesportsdir/public_html/name-parser');

/**
 * Step 1: Check and install dependencies
 */
echo "Step 1: Checking dependencies...\n";

$needsInstall = false;

// Check Composer dependencies
if (!file_exists('vendor/autoload.php')) {
	echo "- Composer dependencies not found\n";
	$needsInstall = true;
} else {
	echo "✓ Composer dependencies found\n";
}

// Check environment file
if (!file_exists('/home/collegesportsdir/config/.env')) {
	echo "❌ Environment file not found at /home/collegesportsdir/config/.env\n";
	echo "Please ensure your .env file exists with database credentials.\n";
	exit(1);
} else {
	echo "✓ Environment file found\n";
}

// Check name parser file
if (!file_exists('name_parser.php')) {
	echo "❌ name_parser.php file not found\n";
	echo "Please ensure the original name parser file is in this directory.\n";
	exit(1);
} else {
	echo "✓ Original name parser found\n";
}

if ($needsInstall) {
	echo "\nInstalling dependencies...\n";
	
	$composerCommands = [
		'composer require vlucas/phpdotenv',
		'composer require phpoffice/phpspreadsheet'
	];
	
	foreach ($composerCommands as $command) {
		echo "Running: $command\n";
		system($command, $returnCode);
		if ($returnCode !== 0) {
			echo "Warning: Command may have failed, but continuing...\n";
		}
	}
	echo "✓ Dependencies installed\n";
}

// Create clean NameParser class file if needed
if (!file_exists('name_parser_class.php')) {
	echo "- Creating clean NameParser class file...\n";
	// Note: In real implementation, you'd copy the clean class content here
	// For now, we'll work with the original file and output suppression
	echo "✓ Will use original name_parser.php with output suppression\n";
}

// Create directories
$directories = ['logs', 'backups'];
foreach ($directories as $dir) {
	if (!file_exists($dir)) {
		mkdir($dir, 0755, true);
		echo "✓ Created directory: $dir\n";
	}
}

echo "\n";

/**
 * Step 2: Test database connection
 */
echo "Step 2: Testing database connection...\n";

try {
	// Load environment variables manually
	$envContent = file_get_contents('/home/collegesportsdir/config/.env');
	$envLines = explode("\n", $envContent);
	$envVars = [];
	
	foreach ($envLines as $line) {
		if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
			list($key, $value) = explode('=', $line, 2);
			$envVars[trim($key)] = trim($value);
		}
	}
	
	$host = $envVars['DB_HOST'] ?? 'localhost';
	$dbname = $envVars['DB_NAME'] ?? '';
	$username = $envVars['DB_USER'] ?? '';
	$password = $envVars['DB_PASSWORD'] ?? '';
	
	if (empty($dbname) || empty($username)) {
		throw new Exception("Database credentials not found in .env file");
	}
	
	$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
	$pdo = new PDO($dsn, $username, $password, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	]);
	
	// Test if csd_staff table exists
	$stmt = $pdo->query("SHOW TABLES LIKE 'csd_staff'");
	if ($stmt->rowCount() === 0) {
		throw new Exception("Table 'csd_staff' not found in database");
	}
	
	// Get table info
	$stmt = $pdo->query("SELECT COUNT(*) as total FROM csd_staff");
	$count = $stmt->fetch()['total'];
	
	$stmt = $pdo->query("SELECT COUNT(*) as with_names FROM csd_staff WHERE full_name IS NOT NULL AND full_name != ''");
	$withNames = $stmt->fetch()['with_names'];
	
	echo "✓ Database connection successful\n";
	echo "✓ Found csd_staff table with {$count} total records\n";
	echo "✓ {$withNames} records have full_name data\n\n";
	
} catch (Exception $e) {
	echo "❌ Database connection failed: " . $e->getMessage() . "\n";
	echo "Please check your database configuration and try again.\n";
	exit(1);
}

/**
 * Step 3: Load database parser and get current status
 */
echo "Step 3: Loading database parser...\n";

try {
	// Load the database parser
	if (!class_exists('NameParser')) {
		ob_start();
		require_once 'name_parser.php';
		ob_end_clean();
	}
	require_once 'database_name_parser.php';
	
	$parser = new DatabaseNameParser();
	$stats = $parser->getStatistics();
	
	echo "✓ Database parser loaded successfully\n";
	echo "\nCurrent Statistics:\n";
	echo "- Total records: {$stats['total_records']}\n";
	echo "- Records with names: {$stats['records_with_names']}\n";
	echo "- Records already parsed: {$stats['records_parsed']}\n";
	echo "- Records needing parsing: {$stats['records_needing_parsing']}\n";
	echo "- Completion: {$stats['completion_percentage']}%\n\n";
	
} catch (Exception $e) {
	echo "❌ Failed to load database parser: " . $e->getMessage() . "\n";
	exit(1);
}

/**
 * Step 4: Setup options
 */
echo "Step 4: Choose your setup option:\n";
echo "1. Full setup (backup + add columns + process all records)\n";
echo "2. Initialize only (backup + add columns)\n";
echo "3. Test parser on sample records\n";
echo "4. Process all records (if already initialized)\n";
echo "5. Process new records only\n";
echo "6. View current statistics\n";
echo "7. Exit\n\n";

echo "Please choose an option (1-7): ";
$handle = fopen("php://stdin", "r");
$choice = trim(fgets($handle));
fclose($handle);

try {
	switch ($choice) {
		case '1':
			echo "\nPerforming full setup...\n";
			
			// Create backup
			echo "Creating database backup...\n";
			$backupFile = $parser->createBackup();
			echo "✓ Backup created: $backupFile\n";
			
			// Add columns
			echo "Adding name parsing columns...\n";
			$addedColumns = $parser->addNameColumns();
			if (empty($addedColumns)) {
				echo "✓ All columns already exist\n";
			} else {
				echo "✓ Added columns: " . implode(', ', $addedColumns) . "\n";
			}
			
			// Ask about batch size
			echo "\nEnter batch size for processing (default 1000, smaller = slower but safer): ";
			$handle = fopen("php://stdin", "r");
			$batchSize = trim(fgets($handle));
			fclose($handle);
			
			if (empty($batchSize) || !is_numeric($batchSize)) {
				$batchSize = 1000;
			}
			
			echo "Processing all records with batch size $batchSize...\n";
			echo "This may take several minutes for large datasets...\n\n";
			
			$results = $parser->processAllRecords(intval($batchSize));
			
			echo "\n✅ Full setup completed!\n";
			echo "Results:\n";
			echo "- Processed: {$results['processed']}\n";
			echo "- Updated: {$results['updated']}\n";
			echo "- Errors: {$results['errors']}\n";
			break;
			
		case '2':
			echo "\nInitializing database...\n";
			
			// Create backup
			echo "Creating database backup...\n";
			$backupFile = $parser->createBackup();
			echo "✓ Backup created: $backupFile\n";
			
			// Add columns
			echo "Adding name parsing columns...\n";
			$addedColumns = $parser->addNameColumns();
			if (empty($addedColumns)) {
				echo "✓ All columns already exist\n";
			} else {
				echo "✓ Added columns: " . implode(', ', $addedColumns) . "\n";
			}
			
			echo "\n✅ Database initialized!\n";
			echo "You can now run option 4 to process all records, or use:\n";
			echo "php database_name_parser.php full\n";
			break;
			
		case '3':
			echo "\nTesting parser on sample records...\n";
			
			echo "Enter number of sample records to test (default 10): ";
			$handle = fopen("php://stdin", "r");
			$sampleSize = trim(fgets($handle));
			fclose($handle);
			
			if (empty($sampleSize) || !is_numeric($sampleSize)) {
				$sampleSize = 10;
			}
			
			$results = $parser->testParser(intval($sampleSize));
			
			echo "\nTest Results:\n";
			echo str_repeat('=', 80) . "\n";
			
			foreach ($results as $result) {
				echo "ID: {$result['id']}\n";
				echo "Original: {$result['original']}\n";
				echo "Honorific: '{$result['parsed']['honorific']}'\n";
				echo "First Name: '{$result['parsed']['first_name']}'\n";
				echo "Last Name: '{$result['parsed']['last_name']}'\n";
				echo "Suffix: '{$result['parsed']['suffix']}'\n";
				echo str_repeat('-', 40) . "\n";
			}
			break;
			
		case '4':
			echo "\nProcessing all records...\n";
			
			echo "Enter batch size (default 1000): ";
			$handle = fopen("php://stdin", "r");
			$batchSize = trim(fgets($handle));
			fclose($handle);
			
			if (empty($batchSize) || !is_numeric($batchSize)) {
				$batchSize = 1000;
			}
			
			echo "Processing with batch size $batchSize...\n";
			$results = $parser->processAllRecords(intval($batchSize));
			
			echo "\n✅ Processing completed!\n";
			echo "Results:\n";
			echo "- Processed: {$results['processed']}\n";
			echo "- Updated: {$results['updated']}\n";
			echo "- Errors: {$results['errors']}\n";
			break;
			
		case '5':
			echo "\nProcessing new records only...\n";
			
			echo "Enter date to process from (YYYY-MM-DD HH:MM:SS, or press Enter for since last run): ";
			$handle = fopen("php://stdin", "r");
			$sinceDate = trim(fgets($handle));
			fclose($handle);
			
			if (empty($sinceDate)) {
				$sinceDate = null;
			}
			
			$results = $parser->processNewRecords($sinceDate);
			
			echo "\n✅ New records processing completed!\n";
			echo "Results:\n";
			echo "- Processed: {$results['processed']}\n";
			echo "- Updated: {$results['updated']}\n";
			echo "- Errors: {$results['errors']}\n";
			break;
			
		case '6':
			$newStats = $parser->getStatistics();
			echo "\nCurrent Statistics:\n";
			echo "- Total records: {$newStats['total_records']}\n";
			echo "- Records with names: {$newStats['records_with_names']}\n";
			echo "- Records already parsed: {$newStats['records_parsed']}\n";
			echo "- Records needing parsing: {$newStats['records_needing_parsing']}\n";
			echo "- Completion: {$newStats['completion_percentage']}%\n";
			break;
			
		case '7':
			echo "Exiting setup.\n";
			exit(0);
			
		default:
			echo "Invalid choice. Exiting.\n";
			exit(1);
	}
	
	// Show cron job setup instructions for database operations
	if (in_array($choice, ['1', '2', '4'])) {
		echo "\n" . str_repeat('=', 60) . "\n";
		echo "CRON JOB SETUP (Optional)\n";
		echo str_repeat('=', 60) . "\n";
		echo "To automatically process new records daily, set up a cron job:\n\n";
		echo "1. Run: crontab -e\n";
		echo "2. Add this line for daily processing at 2:00 AM:\n\n";
		echo "0 2 * * * /usr/bin/php " . getcwd() . "/cron_name_parser.php >> " . getcwd() . "/logs/cron.log 2>&1\n\n";
		echo "3. Save and exit\n\n";
		echo "The cron job will:\n";
		echo "- Process only new records added since last run\n";
		echo "- Send email notifications for errors\n";
		echo "- Log all activity to files\n\n";
	}
	
	echo "\n" . str_repeat('=', 50) . "\n";
	echo "SETUP COMPLETED SUCCESSFULLY!\n";
	echo str_repeat('=', 50) . "\n\n";
	
	echo "Available commands for future use:\n";
	echo "- php database_name_parser.php init     (setup database)\n";
	echo "- php database_name_parser.php full     (process all records)\n";
	echo "- php database_name_parser.php new      (process new records only)\n";
	echo "- php database_name_parser.php test 10  (test on sample records)\n";
	echo "- php database_name_parser.php stats    (show statistics)\n";
	echo "- php database_name_parser.php backup   (create manual backup)\n\n";
	
	echo "Files and directories:\n";
	echo "- Logs: " . getcwd() . "/logs/\n";
	echo "- Backups: " . getcwd() . "/backups/\n";
	echo "- Cron job: " . getcwd() . "/cron_name_parser.php\n\n";
	
} catch (Exception $e) {
	echo "❌ Setup failed: " . $e->getMessage() . "\n";
	echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
	exit(1);
}

?>