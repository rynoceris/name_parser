<?php

require_once 'vendor/autoload.php';
require_once 'name_parser_class.php';

use Dotenv\Dotenv;

echo "Step 1: Loading environment...\n";
try {
	$dotenv = Dotenv::createImmutable('/home/collegesportsdir/config');
	$dotenv->load();
	echo "✓ Environment loaded\n";
} catch (Exception $e) {
	echo "✗ Environment failed: " . $e->getMessage() . "\n";
	exit(1);
}

echo "Step 2: Testing database connection...\n";
try {
	$host = $_ENV['DB_HOST'];
	$dbname = $_ENV['DB_NAME'];
	$username = $_ENV['DB_USER'];
	$password = $_ENV['DB_PASSWORD'];
	
	$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
	$pdo = new PDO($dsn, $username, $password, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);
	echo "✓ Database connected\n";
} catch (Exception $e) {
	echo "✗ Database failed: " . $e->getMessage() . "\n";
	exit(1);
}

echo "Step 3: Testing log directory creation...\n";
$logFile = '/home/collegesportsdir/public_html/name-parser/logs/test_' . date('Y-m-d') . '.log';
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
	mkdir($logDir, 0755, true);
}
echo "✓ Log directory ready\n";

echo "Step 4: Creating DatabaseNameParser object...\n";
require_once 'database_name_parser.php';

try {
	$parser = new DatabaseNameParser();
	echo "✓ Parser object created\n";
} catch (Exception $e) {
	echo "✗ Parser creation failed: " . $e->getMessage() . "\n";
	exit(1);
}

echo "Step 5: Testing a simple method...\n";
try {
	$stats = $parser->getStatistics();
	echo "✓ Statistics retrieved\n";
	echo "- Total records: {$stats['total_records']}\n";
} catch (Exception $e) {
	echo "✗ Statistics failed: " . $e->getMessage() . "\n";
	exit(1);
}

echo "All tests passed!\n";
?>