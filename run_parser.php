<?php

require_once 'database_name_parser.php';

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
				echo "Usage: php run_parser.php [operation] [parameters]\n\n";
				echo "Operations:\n";
				echo "  init              - Initialize database (add columns, create backup)\n";
				echo "  full [batch_size] - Process all records (default batch size: 1000)\n";
				echo "  new [since_date]  - Process new records since date (YYYY-MM-DD HH:MM:SS)\n";
				echo "  test [limit]      - Test parser on sample records (default: 10)\n";
				echo "  stats             - Show parsing statistics\n";
				echo "  backup            - Create database backup\n";
				echo "  help              - Show this help message\n\n";
				echo "Examples:\n";
				echo "  php run_parser.php init\n";
				echo "  php run_parser.php full 500\n";
				echo "  php run_parser.php new '2024-01-01 00:00:00'\n";
				echo "  php run_parser.php test 5\n";
				break;
		}
	} catch (Exception $e) {
		echo "Error: " . $e->getMessage() . "\n";
		exit(1);
	}
}

?>