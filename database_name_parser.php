<?php

require_once 'vendor/autoload.php';

// Include the clean NameParser class directly
require_once 'name_parser_class.php';

use Dotenv\Dotenv;

class DatabaseNameParser extends NameParser
{
	private $pdo;
	private $logFile;
	
	public function __construct()
	{
		// Set up logging first, before any methods that call log()
		$this->logFile = '/home/collegesportsdir/public_html/name-parser/logs/name_parser_' . date('Y-m-d') . '.log';
		$this->ensureLogDirectory();
		
		// Now we can safely call methods that use logging
		$this->loadEnvironment();
		$this->connectToDatabase();
	}
	
	/**
	 * Load environment variables from config file
	 */
	private function loadEnvironment()
	{
		try {
			$dotenv = Dotenv::createImmutable('/home/collegesportsdir/config');
			$dotenv->load();
			$this->log("Environment variables loaded successfully");
		} catch (Exception $e) {
			die("Error loading environment variables: " . $e->getMessage() . "\n");
		}
	}
	
	/**
	 * Establish database connection
	 */
	private function connectToDatabase()
	{
		try {
			$host = $_ENV['DB_HOST'];
			$dbname = $_ENV['DB_NAME'];
			$username = $_ENV['DB_USER'];
			$password = $_ENV['DB_PASSWORD'];
			
			$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
			$options = [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => false,
			];
			
			$this->pdo = new PDO($dsn, $username, $password, $options);
			$this->log("Database connection established successfully");
		} catch (PDOException $e) {
			die("Database connection failed: " . $e->getMessage() . "\n");
		}
	}
	
	/**
	 * Ensure log directory exists
	 */
	private function ensureLogDirectory()
	{
		$logDir = dirname($this->logFile);
		if (!file_exists($logDir)) {
			mkdir($logDir, 0755, true);
		}
	}
	
	/**
	 * Log messages to file and console
	 */
	private function log($message, $level = 'INFO')
	{
		$timestamp = date('Y-m-d H:i:s');
		$logMessage = "[{$timestamp}] [{$level}] {$message}\n";
		
		// Write to log file
		file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
		
		// Also output to console if running via CLI
		if (php_sapi_name() === 'cli') {
			echo $logMessage;
		}
	}
	
	/**
	 * Create database backup before making changes with automatic cleanup
	 */
	public function createBackup($keepCount = 10)
	{
		try {
			$backupDir = '/home/collegesportsdir/public_html/name-parser/backups';
			if (!file_exists($backupDir)) {
				mkdir($backupDir, 0755, true);
			}
			
			$timestamp = date('Y-m-d_H-i-s');
			$backupFile = "{$backupDir}/csd_staff_backup_{$timestamp}.sql";
			
			$dbName = $_ENV['DB_NAME'];
			$dbUser = $_ENV['DB_USER'];
			$dbPass = $_ENV['DB_PASSWORD'];
			$dbHost = $_ENV['DB_HOST'];
			
			$this->log("Creating backup: {$backupFile}");
			$command = "mysqldump -h{$dbHost} -u{$dbUser} -p{$dbPass} {$dbName} csd_staff > {$backupFile}";
			
			exec($command, $output, $returnCode);
			
			if ($returnCode === 0) {
				$this->log("Database backup created successfully: {$backupFile}");
				
				// Clean up old backups, keeping only the most recent $keepCount
				$this->cleanupOldBackups($backupDir, $keepCount);
				
				return $backupFile;
			} else {
				throw new Exception("Backup command failed with return code: {$returnCode}");
			}
		} catch (Exception $e) {
			$this->log("Backup creation failed: " . $e->getMessage(), 'ERROR');
			throw $e;
		}
	}
	
	/**
	 * Clean up old backup files, keeping only the most recent ones
	 */
	private function cleanupOldBackups($backupDir, $keepCount)
	{
		try {
			$backupFiles = glob($backupDir . '/csd_staff_backup_*.sql');
			
			if (count($backupFiles) <= $keepCount) {
				$this->log("Only " . count($backupFiles) . " backups exist, no cleanup needed");
				return;
			}
			
			// Sort by modification time (newest first)
			usort($backupFiles, function($a, $b) {
				return filemtime($b) - filemtime($a);
			});
			
			// Remove old backups beyond the keep count
			$filesToDelete = array_slice($backupFiles, $keepCount);
			
			foreach ($filesToDelete as $file) {
				if (unlink($file)) {
					$this->log("Deleted old backup: " . basename($file));
				} else {
					$this->log("Failed to delete old backup: " . basename($file), 'WARNING');
				}
			}
			
			$this->log("Backup cleanup complete. Kept " . min(count($backupFiles), $keepCount) . " most recent backups");
			
		} catch (Exception $e) {
			$this->log("Backup cleanup failed: " . $e->getMessage(), 'WARNING');
		}
	}
	
	/**
	 * Add new columns to the database table if they don't exist
	 */
	public function addNameColumns()
	{
		try {
			$this->log("Checking and adding name parsing columns...");
			
			// Check which columns already exist
			$stmt = $this->pdo->query("DESCRIBE csd_staff");
			$existingColumns = array_column($stmt->fetchAll(), 'Field');
			
			$columnsToAdd = ['honorific', 'first_name', 'last_name', 'suffix'];
			$addedColumns = [];
			
			foreach ($columnsToAdd as $column) {
				if (!in_array($column, $existingColumns)) {
					$sql = "ALTER TABLE csd_staff ADD COLUMN {$column} VARCHAR(100) DEFAULT NULL";
					$this->pdo->exec($sql);
					$addedColumns[] = $column;
					$this->log("Added column: {$column}");
				}
			}
			
			if (empty($addedColumns)) {
				$this->log("All name parsing columns already exist");
			} else {
				$this->log("Successfully added columns: " . implode(', ', $addedColumns));
			}
			
			return $addedColumns;
		} catch (PDOException $e) {
			$this->log("Failed to add columns: " . $e->getMessage(), 'ERROR');
			throw $e;
		}
	}
	
	/**
	 * Process all existing records in the database
	 */
	public function processAllRecords($batchSize = 1000)
	{
		try {
			$this->log("Starting full database processing...");
			
			// Always create a backup before processing
			$this->log("Creating automatic backup before processing...");
			$this->createBackup(10); // Keep last 10 backups
			
			// Get total count
			$countStmt = $this->pdo->query("SELECT COUNT(*) as total FROM csd_staff WHERE full_name IS NOT NULL AND full_name != ''");
			$totalRecords = $countStmt->fetch()['total'];
			
			$this->log("Found {$totalRecords} records to process");
			
			$processed = 0;
			$updated = 0;
			$errors = 0;
			$offset = 0;
			
			while ($offset < $totalRecords) {
				$this->log("Processing batch starting at offset {$offset}");
				
				// Get batch of records
				$stmt = $this->pdo->prepare(
					"SELECT id, full_name FROM csd_staff 
					 WHERE full_name IS NOT NULL AND full_name != '' 
					 ORDER BY id 
					 LIMIT ? OFFSET ?"
				);
				$stmt->execute([$batchSize, $offset]);
				$records = $stmt->fetchAll();
				
				if (empty($records)) {
					$this->log("No more records found, breaking");
					break;
				}
				
				$this->log("Retrieved " . count($records) . " records for processing");
				
				// Process batch
				foreach ($records as $index => $record) {
					try {
						// Add progress logging every 10 records
						if (($processed % 10) == 0) {
							$this->log("Processing record {$processed}: ID {$record['id']}, Name: {$record['full_name']}");
						}
						
						$parsedName = $this->parseName($record['full_name']);
						
						// Log the parsed result for debugging
						if (($processed % 50) == 0) {
							$this->log("Sample parsed name: " . json_encode($parsedName));
						}
						
						$updateStmt = $this->pdo->prepare(
							"UPDATE csd_staff SET 
							 honorific = ?, 
							 first_name = ?, 
							 last_name = ?, 
							 suffix = ?
							 WHERE id = ?"
						);
						
						$result = $updateStmt->execute([
							$parsedName['honorific'],
							$parsedName['first_name'],
							$parsedName['last_name'],
							$parsedName['suffix'],
							$record['id']
						]);
						
						if ($result) {
							$updated++;
						}
						
						$processed++;
						
					} catch (Exception $e) {
						$this->log("Error processing record ID {$record['id']} ('{$record['full_name']}'): " . $e->getMessage(), 'ERROR');
						$errors++;
					}
				}
				
				$offset += $batchSize;
				$percentComplete = round(($processed / $totalRecords) * 100, 2);
				$this->log("Progress: {$processed}/{$totalRecords} ({$percentComplete}%) - Updated: {$updated}, Errors: {$errors}");
			}
			
			$this->log("Full processing complete. Total processed: {$processed}, Updated: {$updated}, Errors: {$errors}");
			
			return [
				'processed' => $processed,
				'updated' => $updated,
				'errors' => $errors
			];
			
		} catch (Exception $e) {
			$this->log("Full processing failed: " . $e->getMessage(), 'ERROR');
			throw $e;
		}
	}
	
	/**
	 * Process only new records added since last run
	 */
	public function processNewRecords($sinceDate = null)
	{
		try {
			if ($sinceDate === null) {
				$sinceDate = $this->getLastRunDate();
			}
			
			$this->log("Processing new records since: {$sinceDate}");
			
			// Get new records
			$stmt = $this->pdo->prepare(
				"SELECT id, full_name FROM csd_staff 
				 WHERE full_name IS NOT NULL 
				 AND full_name != '' 
				 AND date_created >= ?
				 AND (first_name IS NULL OR first_name = '')
				 ORDER BY id"
			);
			$stmt->execute([$sinceDate]);
			$records = $stmt->fetchAll();
			
			if (empty($records)) {
				$this->log("No new records to process");
				return ['processed' => 0, 'updated' => 0, 'errors' => 0];
			}
			
			$this->log("Found " . count($records) . " new records to process");
			
			// Create backup before processing new records
			$this->log("Creating automatic backup before processing new records...");
			$this->createBackup(10); // Keep last 10 backups
			
			$processed = 0;
			$updated = 0;
			$errors = 0;
			
			foreach ($records as $record) {
				try {
					$parsedName = $this->parseName($record['full_name']);
					
					$updateStmt = $this->pdo->prepare(
						"UPDATE csd_staff SET 
						 honorific = ?, 
						 first_name = ?, 
						 last_name = ?, 
						 suffix = ?
						 WHERE id = ?"
					);
					
					$result = $updateStmt->execute([
						$parsedName['honorific'],
						$parsedName['first_name'],
						$parsedName['last_name'],
						$parsedName['suffix'],
						$record['id']
					]);
					
					if ($result) {
						$updated++;
					}
					
					$processed++;
					
				} catch (Exception $e) {
					$this->log("Error processing record ID {$record['id']}: " . $e->getMessage(), 'ERROR');
					$errors++;
				}
			}
			
			// Update last run timestamp
			$this->updateLastRunDate();
			
			$this->log("New records processing complete. Processed: {$processed}, Updated: {$updated}, Errors: {$errors}");
			
			return [
				'processed' => $processed,
				'updated' => $updated,
				'errors' => $errors
			];
			
		} catch (Exception $e) {
			$this->log("New records processing failed: " . $e->getMessage(), 'ERROR');
			throw $e;
		}
	}
	
	/**
	 * Get the date of the last successful run
	 */
	private function getLastRunDate()
	{
		$statusFile = '/home/collegesportsdir/public_html/name-parser/last_run.txt';
		
		if (file_exists($statusFile)) {
			$lastRun = file_get_contents($statusFile);
			return trim($lastRun);
		}
		
		// Default to yesterday if no previous run
		return date('Y-m-d H:i:s', strtotime('-1 day'));
	}
	
	/**
	 * Update the last run timestamp
	 */
	private function updateLastRunDate()
	{
		$statusFile = '/home/collegesportsdir/public_html/name-parser/last_run.txt';
		file_put_contents($statusFile, date('Y-m-d H:i:s'));
	}
	
	/**
	 * Get parsing statistics
	 */
	public function getStatistics()
	{
		try {
			$stats = [];
			
			// Total records
			$stmt = $this->pdo->query("SELECT COUNT(*) as total FROM csd_staff");
			$stats['total_records'] = $stmt->fetch()['total'];
			
			// Records with full names
			$stmt = $this->pdo->query("SELECT COUNT(*) as total FROM csd_staff WHERE full_name IS NOT NULL AND full_name != ''");
			$stats['records_with_names'] = $stmt->fetch()['total'];
			
			// Records already parsed
			$stmt = $this->pdo->query("SELECT COUNT(*) as total FROM csd_staff WHERE first_name IS NOT NULL AND first_name != ''");
			$stats['records_parsed'] = $stmt->fetch()['total'];
			
			// Records needing parsing
			$stats['records_needing_parsing'] = $stats['records_with_names'] - $stats['records_parsed'];
			
			// Parsing completion percentage
			$stats['completion_percentage'] = $stats['records_with_names'] > 0 
				? round(($stats['records_parsed'] / $stats['records_with_names']) * 100, 2) 
				: 0;
			
			return $stats;
			
		} catch (Exception $e) {
			$this->log("Error getting statistics: " . $e->getMessage(), 'ERROR');
			throw $e;
		}
	}
	
	/**
	 * Test the parser on a sample of records
	 */
	public function testParser($limit = 10)
	{
		try {
			$this->log("Testing parser on {$limit} sample records...");
			
			$stmt = $this->pdo->prepare(
				"SELECT id, full_name FROM csd_staff 
				 WHERE full_name IS NOT NULL AND full_name != '' 
				 ORDER BY RAND() 
				 LIMIT ?"
			);
			$stmt->execute([$limit]);
			$records = $stmt->fetchAll();
			
			$this->log("Retrieved " . count($records) . " records from database");
			
			$results = [];
			
			foreach ($records as $index => $record) {
				$this->log("Testing record " . ($index + 1) . ": ID {$record['id']}, Name: '{$record['full_name']}'");
				
				try {
					$parsedName = $this->parseName($record['full_name']);
					$this->log("Parsed successfully: " . json_encode($parsedName));
					
					$results[] = [
						'id' => $record['id'],
						'original' => $record['full_name'],
						'parsed' => $parsedName
					];
				} catch (Exception $e) {
					$this->log("Error parsing '{$record['full_name']}': " . $e->getMessage(), 'ERROR');
					$results[] = [
						'id' => $record['id'],
						'original' => $record['full_name'],
						'parsed' => ['error' => $e->getMessage()]
					];
				}
			}
			
			$this->log("Test completed successfully with " . count($results) . " results");
			return $results;
			
		} catch (Exception $e) {
			$this->log("Test failed: " . $e->getMessage(), 'ERROR');
			throw $e;
		}
	}
}

// Main execution for command line usage
if (php_sapi_name() === 'cli') {
	echo "Database Name Parser\n";
	echo "===================\n\n";
	
	$parser = new DatabaseNameParser();
	
	// Parse command line arguments
	$operation = isset($argv[1]) ? $argv[1] : 'help';
	
	try {
		switch ($operation) {
			case 'init':
				echo "Initializing database for name parsing...\n";
				$parser->createBackup();
				$parser->addNameColumns();
				echo "Initialization complete!\n";
				break;
				
			case 'full':
				echo "Processing all records in database...\n";
				$batchSize = isset($argv[2]) ? intval($argv[2]) : 1000;
				$stats = $parser->processAllRecords($batchSize);
				echo "Full processing complete!\n";
				echo "Processed: {$stats['processed']}, Updated: {$stats['updated']}, Errors: {$stats['errors']}\n";
				break;
				
			case 'new':
				echo "Processing new records...\n";
				$sinceDate = isset($argv[2]) ? $argv[2] : null;
				$stats = $parser->processNewRecords($sinceDate);
				echo "New records processing complete!\n";
				echo "Processed: {$stats['processed']}, Updated: {$stats['updated']}, Errors: {$stats['errors']}\n";
				break;
				
			case 'test':
				echo "Testing parser...\n";
				$limit = isset($argv[2]) ? intval($argv[2]) : 10;
				$results = $parser->testParser($limit);
				
				foreach ($results as $result) {
					echo "\nID: {$result['id']}\n";
					echo "Original: {$result['original']}\n";
					echo "Parsed: " . json_encode($result['parsed'], JSON_PRETTY_PRINT) . "\n";
					echo str_repeat('-', 50) . "\n";
				}
				break;
				
			case 'stats':
				echo "Getting parser statistics...\n";
				$stats = $parser->getStatistics();
				echo "Statistics:\n";
				echo "- Total records: {$stats['total_records']}\n";
				echo "- Records with names: {$stats['records_with_names']}\n";
				echo "- Records parsed: {$stats['records_parsed']}\n";
				echo "- Records needing parsing: {$stats['records_needing_parsing']}\n";
				echo "- Completion: {$stats['completion_percentage']}%\n";
				break;
				
			case 'backup':
				echo "Creating database backup...\n";
				$backupFile = $parser->createBackup();
				echo "Backup created: {$backupFile}\n";
				break;
				
			case 'help':
			default:
				echo "Usage: php database_name_parser.php [operation] [parameters]\n\n";
				echo "Operations:\n";
				echo "  init              - Initialize database (add columns, create backup)\n";
				echo "  full [batch_size] - Process all records (default batch size: 1000)\n";
				echo "  new [since_date]  - Process new records since date (YYYY-MM-DD HH:MM:SS)\n";
				echo "  test [limit]      - Test parser on sample records (default: 10)\n";
				echo "  stats             - Show parsing statistics\n";
				echo "  backup            - Create database backup\n";
				echo "  help              - Show this help message\n\n";
				echo "Examples:\n";
				echo "  php database_name_parser.php init\n";
				echo "  php database_name_parser.php full 500\n";
				echo "  php database_name_parser.php new '2024-01-01 00:00:00'\n";
				echo "  php database_name_parser.php test 5\n";
				break;
		}
	} catch (Exception $e) {
		echo "Error: " . $e->getMessage() . "\n";
		exit(1);
	}
}

?>