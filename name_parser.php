<?php

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class NameParser
{
	private $honorifics = [
		'mr', 'mrs', 'ms', 'miss', 'dr', 'prof', 'professor', 'rev', 'reverend',
		'hon', 'honorable', 'sir', 'dame', 'lord', 'lady', 'capt', 'captain',
		'lt', 'lieutenant', 'maj', 'major', 'col', 'colonel', 'gen', 'general',
		'adm', 'admiral', 'sgt', 'sergeant', 'cpl', 'corporal', 'pvt', 'private'
	];
	
	private $suffixes = [
		'jr', 'jr.', 'junior', 'sr', 'sr.', 'senior', 'ii', 'iii', 'iv', 'v',
		'esq', 'esq.', 'esquire', 'phd', 'ph.d', 'ph.d.', 'md', 'm.d', 'm.d.',
		'dds', 'd.d.s', 'd.d.s.', 'jd', 'j.d', 'j.d.', 'cpa', 'c.p.a', 'c.p.a.',
		'rn', 'r.n', 'r.n.', 'pe', 'p.e', 'p.e.', 'dvm', 'd.v.m', 'd.v.m.'
	];

	private $namePrefixes = [
		// European prefixes (case variations will be handled in code)
		'de', 'del', 'della', 'de la', 'de las', 'de los', 'da', 'das', 'do', 'dos',
		'di', 'du', 'le', 'la', 'les', 'van', 'van der', 'van den', 'von', 'von der',
		// Celtic prefixes
		'mc', 'mac', 'o\'', 'ó',
		// Religious/Geographic prefixes  
		'st', 'st.', 'saint', 'san', 'santa', 'santo',
		// Compound patterns (these need special handling)
		'ponce de', 'abreu de', 'sandoval de', 'martinez de', 'garcia de'
	];

	private $compoundFirstNames = [
		// Traditional female compound first names
		'mary jo', 'mary jane', 'mary ann', 'mary anne', 'mary beth', 'mary kay', 'mary lou', 'mary sue',
		'anna mae', 'anna lee', 'anna beth', 'anna marie', 'anna grace',
		'sarah jane', 'sarah beth', 'sarah anne', 'sarah grace',
		'leigh anne', 'leigh ann', 'leigh marie',
		'amy jo', 'amy lynn', 'amy sue',
		'lisa marie', 'lisa ann', 'lisa jane',
		'linda sue', 'linda kay', 'linda marie',
		'betty jo', 'betty sue', 'betty ann',
		'carol ann', 'carol lynn', 'carol sue',
		'donna marie', 'donna lynn', 'donna kay',
		'jean marie', 'jean ann', 'jean louise',
		'jo ann', 'jo anne', 'jo lynn',
		'sue ann', 'sue ellen', 'sue marie',
		// Traditional male compound first names
		'john paul', 'john michael', 'john david', 'john robert',
		'james michael', 'james robert', 'james william',
		'robert james', 'robert john', 'robert michael',
		'william james', 'william john', 'william robert',
		'billy joe', 'billy bob', 'billy ray',
		'bobby joe', 'bobby ray', 'bobby lee',
		'tommy lee', 'tommy joe', 'tommy ray',
		'jimmy lee', 'jimmy joe', 'jimmy ray',
		// Modern compound names
		'austin james', 'austin lee', 'austin michael',
		'hunter james', 'hunter lee', 'hunter michael',
		'tyler james', 'tyler lee', 'tyler michael'
	];

	public function parseName($fullName)
	{
		// Initialize result array
		$result = [
			'honorific' => '',
			'first_name' => '',
			'last_name' => '',
			'suffix' => ''
		];

		// Clean and normalize the name
		$name = trim($fullName);
		$name = preg_replace('/\s+/', ' ', $name); // Replace multiple spaces with single space
		
		if (empty($name)) {
			return $result;
		}

		// Handle special cases first
		$name = $this->cleanSpecialCases($name);

		// Split name into parts
		$parts = explode(' ', $name);
		$parts = array_filter($parts); // Remove empty elements
		$parts = array_values($parts); // Re-index array

		if (empty($parts)) {
			return $result;
		}

		$startIndex = 0;
		$endIndex = count($parts) - 1;

		// Check for honorific at the beginning
		if ($startIndex <= $endIndex) {
			$firstPart = strtolower(str_replace(['.', ','], '', $parts[$startIndex]));
			if (in_array($firstPart, $this->honorifics)) {
				$result['honorific'] = $parts[$startIndex];
				$startIndex++;
			}
		}

		// Check for suffix at the end
		if ($startIndex <= $endIndex) {
			$lastPart = strtolower(str_replace(['.', ','], '', $parts[$endIndex]));
			if (in_array($lastPart, $this->suffixes)) {
				$result['suffix'] = $parts[$endIndex];
				$endIndex--;
			}
		}

		// Extract first and last names from remaining parts
		if ($startIndex <= $endIndex) {
			$remainingParts = array_slice($parts, $startIndex, $endIndex - $startIndex + 1);
			
			if (count($remainingParts) == 1) {
				// Only one name part remaining - treat as first name
				$result['first_name'] = $this->cleanCommasFromNameParts($remainingParts[0]);
			} elseif (count($remainingParts) == 2) {
				// Two parts - first and last name
				$result['first_name'] = $this->cleanCommasFromNameParts($remainingParts[0]);
				$result['last_name'] = $this->cleanCommasFromNameParts($remainingParts[1]);
			} else {
				// More than two parts - need to handle name prefixes intelligently
				$splitNames = $this->intelligentNameSplit($remainingParts);
				$result['first_name'] = $this->cleanCommasFromNameParts($splitNames['first']);
				$result['last_name'] = $this->cleanCommasFromNameParts($splitNames['last']);
			}
		}

		return $result;
	}

	/**
	 * Clean special cases that cause parsing issues
	 */
	private function cleanSpecialCases($name)
	{
		// Remove nicknames in quotes (e.g., John "Johnny" Smith -> John Smith)
		$name = preg_replace('/\s*[""].*?["\"]\s*/', ' ', $name);
		$name = preg_replace('/\s*".*?"\s*/', ' ', $name);
		
		// Remove graduation years (e.g., John Smith '22 -> John Smith)
		// Handle multiple graduation years like '18 MSES '24 or '23, M'24
		$name = preg_replace("/\s*'[0-9]{2,4}[A-Z]*\s*/", ' ', $name);
		$name = preg_replace("/\s*[A-Z]+'[0-9]{2,4}\s*/", ' ', $name);
		$name = preg_replace("/\s*,\s*[A-Z]+'[0-9]{2,4}\s*/", ' ', $name);
		
		// Comprehensive credential removal
		// First, handle credentials that come after commas
		if (strpos($name, ',') !== false) {
			$parts = explode(',', $name);
			$mainName = trim($parts[0]);
			
			// Extended list of credentials to recognize
			$credentialPatterns = [
				'/^[A-Z]{2,6}$/',  // Any 2-6 letter uppercase acronym
				'/^[A-Z]{2,6}\.?$/', // Same with optional period
				'/^(MS|MA|BA|BS|DDS|JD|LLB|MBA|MD|PhD|PharmD|DVM|RN|LAT|ATC|CES|PES|MSAT|MSES|NRAEMT|LMT)\.?$/i',
				'/^(ATC\/LAT|LAT\/ATC)$/i' // Slash-separated credentials
			];
			
			$hasCredentials = false;
			for ($i = 1; $i < count($parts); $i++) {
				$part = trim($parts[$i]);
				if (empty($part)) continue;
				
				foreach ($credentialPatterns as $pattern) {
					if (preg_match($pattern, $part)) {
						$hasCredentials = true;
						break 2;
					}
				}
			}
			
			// If it looks like credentials, just use the main name
			if ($hasCredentials) {
				$name = $mainName;
			}
		}
		
		// Handle space-separated credentials (without commas)
		// Look for patterns like "John Smith MS ATC" or "Jane Doe MSES LAT"
		$credentialRegex = '/\s+(MS|MA|BA|BS|DDS|JD|LLB|MBA|MD|PhD|PharmD|DVM|RN|LAT|ATC|CES|PES|MSAT|MSES|NRAEMT|LMT|ATC\/LAT|LAT\/ATC)(\s+(MS|MA|BA|BS|DDS|JD|LLB|MBA|MD|PhD|PharmD|DVM|RN|LAT|ATC|CES|PES|MSAT|MSES|NRAEMT|LMT|ATC\/LAT|LAT\/ATC))*\s*$/i';
		$name = preg_replace($credentialRegex, '', $name);
		
		// Handle single trailing credentials (like "John Smith MS" or "Jane Doe MSES")
		$singleCredentialRegex = '/\s+(MS|MA|BA|BS|DDS|JD|LLB|MBA|MD|PhD|PharmD|DVM|RN|LAT|ATC|CES|PES|MSAT|MSES|NRAEMT|LMT|ATC\/LAT|LAT\/ATC)\s*$/i';
		$name = preg_replace($singleCredentialRegex, '', $name);
		
		// Remove standalone single letters that are likely degree abbreviations (M, B, D, etc.)
		$name = preg_replace('/\s+[A-Z]\s*$/', '', $name);
		
		// Remove parenthetical information (e.g., John Smith (Class of 2022) -> John Smith)
		$name = preg_replace('/\s*\([^)]*\)\s*/', ' ', $name);
		
		// Remove bracketed information (e.g., John Smith [Alumni] -> John Smith)
		$name = preg_replace('/\s*\[[^\]]*\]\s*/', ' ', $name);
		
		// Remove year patterns that might be graduation years (4-digit years not in parentheses)
		$name = preg_replace('/\s*\b(19|20)\d{2}\b\s*/', ' ', $name);
		
		// Clean up any remaining commas that might be at the end of words
		$name = preg_replace('/,\s*$/', '', $name);
		$name = preg_replace('/\s*,\s*/', ' ', $name);
		
		// Clean up multiple spaces
		$name = preg_replace('/\s+/', ' ', $name);
		
		return trim($name);
	}

	/**
	 * Additional cleaning to ensure no commas remain in first/last names
	 */
	private function cleanCommasFromNameParts($namePart)
	{
		if (empty($namePart)) {
			return '';
		}
		
		// Remove any trailing commas
		$namePart = preg_replace('/,+\s*$/', '', $namePart);
		
		// Remove any leading commas
		$namePart = preg_replace('/^\s*,+/', '', $namePart);
		
		// Remove commas between words (but preserve hyphenated names and apostrophes)
		$namePart = preg_replace('/\s*,\s*/', ' ', $namePart);
		
		return trim($namePart);
	}

	/**
	 * Intelligently split names with multiple parts, handling name prefixes correctly
	 */
	private function intelligentNameSplit($parts)
	{
		$result = ['first' => '', 'last' => ''];
		
		if (empty($parts)) {
			return $result;
		}
		
		$partsCount = count($parts);
		
		// For exactly 3 parts, check for compound first/last name patterns
		if ($partsCount === 3) {
			$compoundSplit = $this->detectCompoundNames($parts);
			if ($compoundSplit !== null) {
				return $compoundSplit;
			}
		}
		
		// Join all parts to work with the full remaining name
		$fullRemainingName = implode(' ', $parts);
		$lowerName = strtolower($fullRemainingName);
		
		// Sort prefixes by length (longest first) to match "de la" before "de"
		$sortedPrefixes = $this->namePrefixes;
		usort($sortedPrefixes, function($a, $b) {
			return strlen($b) - strlen($a);
		});
		
		// Look for name prefixes and determine the split point
		$splitPoint = null;
		$foundPrefix = false;
		
		foreach ($sortedPrefixes as $prefix) {
			$prefixLower = strtolower($prefix);
			$prefixPattern = '/\b' . preg_quote($prefixLower, '/') . '\b/';
			
			if (preg_match($prefixPattern, $lowerName, $matches, PREG_OFFSET_CAPTURE)) {
				$prefixPosition = $matches[0][1];
				
				// Find which word index this prefix starts at
				$beforePrefix = substr($lowerName, 0, $prefixPosition);
				$wordsBefore = empty(trim($beforePrefix)) ? 0 : count(preg_split('/\s+/', trim($beforePrefix)));
				
				// Set split point to just before the prefix
				$splitPoint = $wordsBefore;
				$foundPrefix = true;
				break;
			}
		}
		
		// Handle special compound cases (these override general prefix matching)
		$compoundPatterns = [
			'/\b(ponce)\s+(de)\b/i' => 1,       // "Gabe Ponce De Leon" -> first: "Gabe", last: "Ponce De Leon"
			'/\b(abreu)\s+(de)\b/i' => 1,       // "Maira Abreu de Campos" -> first: "Maira", last: "Abreu de Campos" 
			'/\b(sandoval)\s+(de)\b/i' => 1,    // "Jose Sandoval De Leon" -> first: "Jose", last: "Sandoval De Leon"
			'/\b(martinez)\s+(de)\b/i' => 1,    // Similar pattern
			'/\b(garcia)\s+(de)\b/i' => 1,      // Similar pattern
			'/\b(furlaneto)\s+(de)\b/i' => 1    // "Matt Furlaneto De Oliveira" -> first: "Matt", last: "Furlaneto De Oliveira"
		];
		
		foreach ($compoundPatterns as $pattern => $firstNameWords) {
			if (preg_match($pattern, $lowerName)) {
				$splitPoint = $firstNameWords;
				$foundPrefix = true;
				break;
			}
		}
		
		// If no prefix found, use traditional logic
		if (!$foundPrefix) {
			if ($partsCount == 3) {
				// For 3 parts, assume "First Middle Last" unless we found a prefix
				$splitPoint = 2; // First and middle go to first name, last goes to last name
			} else {
				// For more than 3 parts, first word is first name, rest is last name
				$splitPoint = 1;
			}
		}
		
		// Ensure splitPoint is valid
		$splitPoint = max(1, min($splitPoint, $partsCount - 1));
		
		// Split the name
		$firstParts = array_slice($parts, 0, $splitPoint);
		$lastParts = array_slice($parts, $splitPoint);
		
		$result['first'] = implode(' ', $firstParts);
		$result['last'] = implode(' ', $lastParts);
		
		return $result;
	}

	/**
	 * Detect compound first names vs compound last names for 3-part names
	 */
	private function detectCompoundNames($parts)
	{
		if (count($parts) !== 3) {
			return null;
		}
		
		$possibleCompoundFirst = strtolower($parts[0] . ' ' . $parts[1]);
		$fullName = implode(' ', $parts);
		
		// Check if it's a known compound first name
		$isCompoundFirst = in_array($possibleCompoundFirst, $this->compoundFirstNames);
		
		// Check if the pattern suggests a compound last name
		$suggestsCompoundLast = $this->suggestsCompoundLastName($fullName);
		
		if ($isCompoundFirst && !$suggestsCompoundLast) {
			// Clearly a compound first name
			return ['first' => $parts[0] . ' ' . $parts[1], 'last' => $parts[2]];
		} elseif (!$isCompoundFirst && $suggestsCompoundLast) {
			// Clearly a compound last name
			return ['first' => $parts[0], 'last' => $parts[1] . ' ' . $parts[2]];
		} elseif ($isCompoundFirst && $suggestsCompoundLast) {
			// Conflict - prioritize compound first names for common patterns
			if ($this->isVeryCommonCompoundFirst($possibleCompoundFirst)) {
				return ['first' => $parts[0] . ' ' . $parts[1], 'last' => $parts[2]];
			} else {
				return ['first' => $parts[0], 'last' => $parts[1] . ' ' . $parts[2]];
			}
		}
		
		// No clear pattern detected, return null to use default logic
		return null;
	}
	
	/**
	 * Check if the name pattern suggests a compound last name
	 */
	private function suggestsCompoundLastName($fullName)
	{
		// Remove any titles/credentials first
		$cleanName = preg_replace('/^(Dr\.|Mrs\.|Mr\.|Ms\.|Prof\.|Rev\.|Captain)\s+/i', '', $fullName);
		$cleanName = preg_replace('/\s+(Jr\.|Sr\.|III|IV|V|PhD|MD)\.?$/i', '', $cleanName);
		$cleanName = trim($cleanName);
		
		// Patterns that suggest compound surnames
		$patterns = [
			// Italian-style endings
			'/^[A-Z][a-z]+ [A-Z][a-z]+o [A-Z][a-z]+$/',     // "Laura Marzano Kemper"
			'/^[A-Z][a-z]+ [A-Z][a-z]+i [A-Z][a-z]+$/',     // "Maria Rossi Smith"
			'/^[A-Z][a-z]+ [A-Z][a-z]+a [A-Z][a-z]+$/',     // "Sofia Bella Jones"
			
			// German/Dutch-style endings
			'/^[A-Z][a-z]+ [A-Z][a-z]+ke [A-Z][a-z]+$/',    // "Maribeth Boeke Ganzell"
			'/^[A-Z][a-z]+ [A-Z][a-z]+er [A-Z][a-z]+$/',    // "Sarah Mueller Johnson"
			'/^[A-Z][a-z]+ [A-Z][a-z]+en [A-Z][a-z]+$/',    // "John Hansen Wilson"
			
			// British-style compound surnames
			'/^[A-Z][a-z]+ [A-Z][a-z]+brook [A-Z][a-z]+$/', // "Anne Westbrook Gay"
			'/^[A-Z][a-z]+ [A-Z][a-z]+field [A-Z][a-z]+$/', // "Jane Whitfield Brown"
			'/^[A-Z][a-z]+ [A-Z][a-z]+wood [A-Z][a-z]+$/',  // "Mary Blackwood Smith"
			'/^[A-Z][a-z]+ [A-Z][a-z]+ton [A-Z][a-z]+$/',   // "John Hamilton Davis"
			'/^[A-Z][a-z]+ [A-Z][a-z]+land [A-Z][a-z]+$/',  // "Sarah Sutherland Jones"
			'/^[A-Z][a-z]+ [A-Z][a-z]+burg [A-Z][a-z]+$/',  // "John Goldberg Smith"
			
			// Pattern: Long middle name + short last name often suggests compound surname
			'/^[A-Z][a-z]+ [A-Z][a-z]{5,} [A-Z][a-z]{2,4}$/',
			
			// Specific surname patterns
			'/^[A-Z][a-z]+ [A-Z][a-z]+son [A-Z][a-z]+$/',   // "Mary Johnson Smith"
			'/^[A-Z][a-z]+ [A-Z][a-z]+man [A-Z][a-z]+$/',   // "John Freeman Davis"
		];
		
		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $cleanName)) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Check if this is a very common compound first name that should take priority
	 */
	private function isVeryCommonCompoundFirst($compoundFirst)
	{
		$veryCommon = [
			'mary jane', 'mary jo', 'mary ann', 'mary anne', 'mary beth',
			'john paul', 'john michael', 'billy ray', 'bobby joe',
			'leigh anne', 'anna marie', 'sarah jane'
		];
		
		return in_array($compoundFirst, $veryCommon);
	}

	public function processExcelFile($inputFile, $outputFile)
	{
		try {
			echo "Loading spreadsheet: $inputFile\n";
			
			// Load the spreadsheet
			$spreadsheet = IOFactory::load($inputFile);
			$worksheet = $spreadsheet->getActiveSheet();
			
			// Get the highest row number
			$highestRow = $worksheet->getHighestRow();
			echo "Found $highestRow rows to process\n";
			
			// Create new spreadsheet for output
			$newSpreadsheet = new Spreadsheet();
			$newWorksheet = $newSpreadsheet->getActiveSheet();
			
			// Set headers
			$newWorksheet->setCellValue('A1', 'full_name');
			$newWorksheet->setCellValue('B1', 'honorific');
			$newWorksheet->setCellValue('C1', 'first_name');
			$newWorksheet->setCellValue('D1', 'last_name');
			$newWorksheet->setCellValue('E1', 'suffix');
			
			$outputRow = 2; // Start from row 2 (after headers)
			$processedCount = 0;
			
			// Process each row
			for ($row = 1; $row <= $highestRow; $row++) {
				$fullName = $worksheet->getCell('A' . $row)->getValue();
				
				// Skip header row or empty cells
				if ($row == 1 && strtolower(trim($fullName)) == 'full_name') {
					continue;
				}
				
				if (empty(trim($fullName))) {
					continue;
				}
				
				// Parse the name
				$parsedName = $this->parseName($fullName);
				
				// Write to new spreadsheet
				$newWorksheet->setCellValue('A' . $outputRow, $fullName);
				$newWorksheet->setCellValue('B' . $outputRow, $parsedName['honorific']);
				$newWorksheet->setCellValue('C' . $outputRow, $parsedName['first_name']);
				$newWorksheet->setCellValue('D' . $outputRow, $parsedName['last_name']);
				$newWorksheet->setCellValue('E' . $outputRow, $parsedName['suffix']);
				
				$outputRow++;
				$processedCount++;
				
				// Progress indicator for large files
				if ($processedCount % 1000 == 0) {
					echo "Processed $processedCount names...\n";
				}
			}
			
			echo "Saving output file: $outputFile\n";
			
			// Save the new spreadsheet
			$writer = new Xlsx($newSpreadsheet);
			$writer->save($outputFile);
			
			echo "Processing complete!\n";
			echo "Input file: $inputFile\n";
			echo "Output file: $outputFile\n";
			echo "Total rows processed: $processedCount\n";
			
			return true;
			
		} catch (Exception $e) {
			echo "Error processing file: " . $e->getMessage() . "\n";
			echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
			return false;
		}
	}

	/**
	 * Alternative method to process CSV files directly
	 */
	public function processCsvFile($inputFile, $outputFile)
	{
		try {
			$inputHandle = fopen($inputFile, 'r');
			$outputHandle = fopen($outputFile, 'w');
			
			if (!$inputHandle || !$outputHandle) {
				throw new Exception("Could not open input or output file");
			}
			
			// Write CSV headers
			fputcsv($outputHandle, ['full_name', 'honorific', 'first_name', 'last_name', 'suffix']);
			
			$rowCount = 0;
			$processedCount = 0;
			
			while (($row = fgetcsv($inputHandle)) !== false) {
				$rowCount++;
				
				// Skip header row
				if ($rowCount == 1 && isset($row[0]) && strtolower($row[0]) == 'full_name') {
					continue;
				}
				
				if (empty($row[0]) || empty(trim($row[0]))) {
					continue;
				}
				
				$fullName = trim($row[0]);
				$parsedName = $this->parseName($fullName);
				
				fputcsv($outputHandle, [
					$fullName,
					$parsedName['honorific'],
					$parsedName['first_name'],
					$parsedName['last_name'],
					$parsedName['suffix']
				]);
				
				$processedCount++;
				
				// Progress indicator
				if ($processedCount % 1000 == 0) {
					echo "Processed $processedCount names...\n";
				}
			}
			
			fclose($inputHandle);
			fclose($outputHandle);
			
			echo "CSV processing complete! Output saved to: $outputFile\n";
			echo "Total rows processed: $processedCount\n";
			
			return true;
			
		} catch (Exception $e) {
			echo "Error processing CSV file: " . $e->getMessage() . "\n";
			return false;
		}
	}

	/**
	 * Process array of names and return parsed results
	 */
	public function processNamesArray($names)
	{
		$results = [];
		
		foreach ($names as $fullName) {
			if (empty(trim($fullName))) {
				continue;
			}
			
			$parsedName = $this->parseName(trim($fullName));
			$parsedName['original_name'] = $fullName;
			$results[] = $parsedName;
		}
		
		return $results;
	}
}

// Main execution
if (php_sapi_name() === 'cli') {
	echo "Name Parser Script\n";
	echo "==================\n\n";
	
	$parser = new NameParser();
	
	// Check if input file was provided as command line argument
	if ($argc > 1) {
		$inputFile = $argv[1];
		$outputFile = isset($argv[2]) ? $argv[2] : 'parsed_names.xlsx';
	} else {
		// Default file names
		$inputFile = 'full_names.xlsx';
		$outputFile = 'parsed_names.xlsx';
	}
	
	// Check if input file exists
	if (!file_exists($inputFile)) {
		echo "Error: Input file '$inputFile' not found.\n";
		echo "Please make sure the file exists in the current directory.\n";
		echo "\nUsage: php name_parser.php [input_file] [output_file]\n";
		echo "Example: php name_parser.php full_names.xlsx parsed_names.xlsx\n";
		exit(1);
	}
	
	echo "Input file: $inputFile\n";
	echo "Output file: $outputFile\n\n";
	
	// Process the file
	$success = $parser->processExcelFile($inputFile, $outputFile);
	
	if ($success) {
		echo "\n✅ Processing completed successfully!\n";
	} else {
		echo "\n❌ Processing failed. Please check the error messages above.\n";
		exit(1);
	}
} else {
	echo "<p>This script can also be run from the command line.</p>";
	echo "<p class='console-container'>Usage: <span class='console'>php name_parser.php [input_file] [output_file]</span><br>";
	echo "Example: <span class='console'>php name_parser.php full_names.xlsx parsed_names.xlsx</span></p>";
	echo "<p>Back to <a href='https://www.collegesportsdirectory.com/wp-admin/admin.php?page=csd-manager'>WordPress Admin: College Sports Directory Manager</a></p>";
}

?>