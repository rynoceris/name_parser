<?php
/**
 * Daily Name Parser Cron Job
 * 
 * This script is designed to be run daily via cron to parse any new
 * staff members that have been added since the last run.
 * 
 * Add to crontab with:
 * 0 2 * * * /usr/bin/php /home/collegesportsdir/public_html/name-parser/cron_name_parser.php >> /home/collegesportsdir/public_html/name-parser/logs/cron.log 2>&1
 */

// Set error reporting for production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/collegesportsdir/public_html/name-parser/logs/cron_errors.log');

// Set time limit for potentially long-running process
set_time_limit(300); // 5 minutes

// Change to script directory
chdir('/home/collegesportsdir/public_html/name-parser');

// Include required files
require_once 'database_name_parser.php';

/**
 * Send email notification for significant events
 */
function sendNotification($subject, $message, $priority = 'normal')
{
	// Email configuration - adjust as needed
	$to = 'admin@collegesportsdirectory.com'; // Change to your admin email
	$from = 'noreply@collegesportsdirectory.com';
	
	$headers = [
		"From: {$from}",
		"Reply-To: {$from}",
		"X-Mailer: PHP/" . phpversion(),
		"Content-Type: text/plain; charset=UTF-8"
	];
	
	if ($priority === 'high') {
		$headers[] = "X-Priority: 1";
		$headers[] = "X-MSMail-Priority: High";
	}
	
	$fullMessage = "Name Parser Cron Job Report\n";
	$fullMessage .= "Time: " . date('Y-m-d H:i:s') . "\n";
	$fullMessage .= "Server: " . gethostname() . "\n\n";
	$fullMessage .= $message;
	
	// Only send email if mail function is available and configured
	if (function_exists('mail')) {
		@mail($to, $subject, $fullMessage, implode("\r\n", $headers));
	}
}

/**
 * Write to cron-specific log file
 */
function cronLog($message, $level = 'INFO')
{
	$logFile = '/home/collegesportsdir/public_html/name-parser/logs/cron.log';
	$logDir = dirname($logFile);
	
	if (!file_exists($logDir)) {
		mkdir($logDir, 0755, true);
	}
	
	$timestamp = date('Y-m-d H:i:s');
	$logMessage = "[{$timestamp}] [{$level}] {$message}\n";
	
	file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
	echo $logMessage; // Also output to stdout for cron logging
}

// Start execution
cronLog("=== Daily Name Parser Cron Job Started ===");

try {
	// Initialize the parser
	$parser = new DatabaseNameParser();
	
	// Get current statistics
	$statsBefore = $parser->getStatistics();
	cronLog("Statistics before processing:");
	cronLog("- Total records: {$statsBefore['total_records']}");
	cronLog("- Records with names: {$statsBefore['records_with_names']}");
	cronLog("- Records already parsed: {$statsBefore['records_parsed']}");
	cronLog("- Completion: {$statsBefore['completion_percentage']}%");
	
	// Process new records
	cronLog("Processing new records...");
	$results = $parser->processNewRecords();
	
	// Log results
	cronLog("Processing completed:");
	cronLog("- Processed: {$results['processed']}");
	cronLog("- Updated: {$results['updated']}");
	cronLog("- Errors: {$results['errors']}");
	
	// Get updated statistics
	$statsAfter = $parser->getStatistics();
	
	// Determine if notification is needed
	$needsNotification = false;
	$notificationMessage = "";
	$notificationPriority = 'normal';
	
	if ($results['errors'] > 0) {
		$needsNotification = true;
		$notificationPriority = 'high';
		$notificationMessage = "ERRORS DETECTED: {$results['errors']} records failed to process.\n\n";
	}
	
	if ($results['processed'] > 0) {
		$needsNotification = true;
		$notificationMessage .= "Successfully processed {$results['processed']} new staff records.\n";
		$notificationMessage .= "Updated: {$results['updated']}\n\n";
	}
	
	if ($results['processed'] > 100) {
		$needsNotification = true;
		$notificationPriority = 'high';
		$notificationMessage .= "Large batch detected: {$results['processed']} records processed. Please verify data integrity.\n\n";
	}
	
	// Add statistics to notification
	if ($needsNotification) {
		$notificationMessage .= "Current Statistics:\n";
		$notificationMessage .= "- Total records: {$statsAfter['total_records']}\n";
		$notificationMessage .= "- Records with names: {$statsAfter['records_with_names']}\n";
		$notificationMessage .= "- Records parsed: {$statsAfter['records_parsed']}\n";
		$notificationMessage .= "- Completion: {$statsAfter['completion_percentage']}%\n\n";
		
		if ($results['errors'] > 0) {
			$notificationMessage .= "Please check the error logs at:\n";
			$notificationMessage .= "/home/collegesportsdir/public_html/name-parser/logs/\n";
		}
		
		sendNotification("Name Parser Daily Report", $notificationMessage, $notificationPriority);
		cronLog("Notification sent");
	}
	
	// Clean up old log files (keep last 30 days)
	$logDir = '/home/collegesportsdir/public_html/name-parser/logs';
	if (is_dir($logDir)) {
		$files = glob($logDir . '/name_parser_*.log');
		$cutoffDate = strtotime('-30 days');
		
		foreach ($files as $file) {
			if (filemtime($file) < $cutoffDate) {
				unlink($file);
				cronLog("Cleaned up old log file: " . basename($file));
			}
		}
	}
	
	cronLog("=== Daily Name Parser Cron Job Completed Successfully ===");
	
} catch (Exception $e) {
	$errorMessage = "Fatal error in name parser cron job: " . $e->getMessage();
	cronLog($errorMessage, 'ERROR');
	
	// Send error notification
	$errorNotification = "The daily name parser cron job encountered a fatal error:\n\n";
	$errorNotification .= $e->getMessage() . "\n\n";
	$errorNotification .= "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
	$errorNotification .= "Please check the server and logs immediately.";
	
	sendNotification("URGENT: Name Parser Cron Job Failed", $errorNotification, 'high');
	
	exit(1);
}

?>