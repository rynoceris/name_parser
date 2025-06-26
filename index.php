<!DOCTYPE html>
<html>
<head>
	<title>Name Parser</title>
	<style>
		body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
		.container { background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0; }
		.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
		.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
		.info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
		pre { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
		button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
		button:hover { background: #0056b3; }
		span.console { background-color: black; color: green; padding: 5px; }
		p.console-container { line-height: 2em; }
	</style>
</head>
<body>
	<h1>Excel Name Parser</h1>
	
	<?php
	require_once 'vendor/autoload.php';
	require_once 'name_parser.php';
	
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$inputFile = $_POST['input_file'] ?? 'full_names.xlsx';
		$outputFile = $_POST['output_file'] ?? 'parsed_names.xlsx';
		
		echo "<div class='container info'>";
		echo "<h3>Processing...</h3>";
		echo "<p>Input: $inputFile</p>";
		echo "<p>Output: $outputFile</p>";
		echo "</div>";
		
		// Capture output
		ob_start();
		
		$parser = new NameParser();
		
		if (!file_exists($inputFile)) {
			echo "<div class='container error'>";
			echo "<h3>Error</h3>";
			echo "<p>Input file '$inputFile' not found. Please upload it to the server first.</p>";
			echo "</div>";
		} else {
			$success = $parser->processExcelFile($inputFile, $outputFile);
			
			$output = ob_get_clean();
			
			if ($success) {
				echo "<div class='container success'>";
				echo "<h3>✅ Success!</h3>";
				echo "<p>File processed successfully!</p>";
				echo "<p><strong>Download:</strong> <a href='$outputFile' download>$outputFile</a></p>";
				echo "</div>";
			} else {
				echo "<div class='container error'>";
				echo "<h3>❌ Error</h3>";
				echo "<p>Processing failed. Check the details below.</p>";
				echo "</div>";
			}
			
			if (!empty($output)) {
				echo "<div class='container'>";
				echo "<h3>Processing Log</h3>";
				echo "<pre>" . htmlspecialchars($output) . "</pre>";
				echo "</div>";
			}
		}
	}
	?>
	
	<div class="container">
		<h2>Instructions</h2>
		<ol>
			<li>Upload your Excel file (with full_name column) to this server directory</li>
			<li>Enter the file names below</li>
			<li>Click "Process Names" to start parsing</li>
			<li>Download the processed file when complete</li>
		</ol>
	</div>
	
	<form method="POST" class="container">
		<h2>Process Names</h2>
		
		<p>
			<label>Input Excel File:</label><br>
			<input type="text" name="input_file" value="full_names.xlsx" style="width: 300px; padding: 5px;">
		</p>
		
		<p>
			<label>Output Excel File:</label><br>
			<input type="text" name="output_file" value="parsed_names.xlsx" style="width: 300px; padding: 5px;">
		</p>
		
		<p>
			<button type="submit">Process Names</button>
		</p>
	</form>
	
	<div class="container">
		<h3>Files in Current Directory</h3>
		<ul>
		<?php
		$files = glob('*.{xlsx,xls,csv}', GLOB_BRACE);
		if (empty($files)) {
			echo "<li>No Excel/CSV files found</li>";
		} else {
			foreach ($files as $file) {
				$size = filesize($file);
				$sizeFormatted = $size > 1024*1024 ? round($size/(1024*1024), 2) . ' MB' : round($size/1024, 2) . ' KB';
				echo "<li>$file ($sizeFormatted)</li>";
			}
		}
		?>
		</ul>
	</div>
</body>
</html>