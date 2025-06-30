<?php

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class NameParser
{
	private $honorifics = [
		'mr', 'mrs', 'ms', 'miss', 'dr', 'prof', 'professor', 'rev', 'reverend',
		'hon', 'honorable', 'sir', 'dame', 'lord', 'capt', 'captain',
		'lt', 'lieutenant', 'maj', 'major', 'col', 'colonel', 'gen', 'general',
		'adm', 'admiral', 'sgt', 'sergeant', 'cpl', 'corporal', 'pvt', 'private',
		// Military compound ranks
		'lt. col.', 'lt col', 'maj. gen.', 'maj gen', 'brig. gen.', 'brig gen',
		// Religious compound honorifics
		'reverend dr.', 'reverend dr', 'rev dr.', 'rev dr', 'rev. dr.', 'rev. dr'
	];
	
	private $suffixes = [
		'jr', 'jr.', 'senior', 'sr', 'sr.', 'ii', 'iii', 'iv', 'v', 'vi',
		'esq', 'esq.', 'esquire', 'phd', 'ph.d', 'ph.d.', 'md', 'm.d', 'm.d.',
		'dds', 'd.d.s', 'd.d.s.', 'jd', 'j.d', 'j.d.', 'cpa', 'c.p.a', 'c.p.a.',
		'rn', 'r.n', 'r.n.', 'pe', 'p.e', 'p.e.', 'dvm', 'd.v.m', 'd.v.m.',
		'do', 'd.o', 'd.o.', 'edd', 'ed.d', 'ed.d.', 'psyd', 'psy.d', 'psy.d.',
		'mph', 'm.p.h', 'm.p.h.', 'mba', 'm.b.a', 'm.b.a.', 'ms', 'm.s', 'm.s.',
		'ma', 'm.a', 'm.a.', 'bs', 'b.s', 'b.s.', 'ba', 'b.a', 'b.a.',
		'od', 'o.d', 'o.d.', 'dc', 'd.c', 'd.c.', 'dat', 'd.a.t', 'd.a.t.',
		'lat', 'l.a.t', 'l.a.t.', 'atc', 'a.t.c', 'a.t.c.', 'pt', 'p.t', 'p.t.',
		'dpt', 'd.p.t', 'd.p.t.', 'pharmd', 'pharm.d', 'pharm.d.'
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
		'kimberly anne', 'kimberly ann', 'kimberly marie',
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

	// Words that shouldn't be treated as regular names
	private $organizationalWords = [
		'general', 'inquiries', 'information', 'correspondence', 'operations', 
		'marketing', 'ticket', 'office', 'department'
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

		// Check for organizational entries (like "General Inquiries")
		if ($this->isOrganizationalEntry($name)) {
			// For organizational entries, put everything in first_name and clear honorific
			$result['first_name'] = $name;
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

		// Check for honorific at the beginning (including compound military ranks)
		if ($startIndex <= $endIndex) {
			$honorific = $this->extractHonorific($parts, $startIndex);
			if ($honorific) {
				$result['honorific'] = $honorific['text'];
				$startIndex = $honorific['newIndex'];
			}
		}

		// Check for suffix at the end
		if ($startIndex <= $endIndex) {
			$suffix = $this->extractSuffix($parts, $endIndex);
			if ($suffix) {
				$result['suffix'] = $suffix['text'];
				$endIndex = $suffix['newIndex'];
			}
		}

		// Extract first and last names from remaining parts
		if ($startIndex <= $endIndex) {
			$remainingParts = array_slice($parts, $startIndex, $endIndex - $startIndex + 1);
			
			if (count($remainingParts) == 1) {
				// Only one name part remaining - treat as last name if it's not an initial
				if ($this->isInitial($remainingParts[0])) {
					$result['first_name'] = '';
					$result['last_name'] = $this->cleanCommasFromNameParts($remainingParts[0]);
				} else {
					$result['last_name'] = $this->cleanCommasFromNameParts($remainingParts[0]);
				}
			} elseif (count($remainingParts) == 2) {
				// Two parts - handle initials properly
				$first = $this->cleanCommasFromNameParts($remainingParts[0]);
				$last = $this->cleanCommasFromNameParts($remainingParts[1]);
				
				// If first part is an initial, skip it and use second as last name
				if ($this->isInitial($first)) {
					$result['first_name'] = '';
					$result['last_name'] = $last;
				} 
				// If first part is a name and second is Senior/Junior as last name
				elseif ($this->isSeniorJuniorLastName($remainingParts)) {
					$result['first_name'] = $first;
					$result['last_name'] = $last;
				} else {
					$result['first_name'] = $first;
					$result['last_name'] = $last;
				}
			} else {
				// More than two parts - need to handle initials and name prefixes
				$cleanedParts = $this->removeMiddleInitials($remainingParts);
				$splitNames = $this->intelligentNameSplit($cleanedParts);
				$result['first_name'] = $this->cleanCommasFromNameParts($splitNames['first']);
				$result['last_name'] = $this->cleanCommasFromNameParts($splitNames['last']);
			}
		}

		return $result;
	}

	/**
	 * Check if this appears to be an organizational entry rather than a person's name
	 */
	private function isOrganizationalEntry($name)
	{
		$lowerName = strtolower($name);
		
		// Check for patterns that suggest organizational entries
		$orgPatterns = [
			'/^general\s+(inquiries|information|correspondence|operations|marketing|ticket|office)/',
			'/^general\s+[a-z]+\s+(information|inquiries|office)/',
		];
		
		foreach ($orgPatterns as $pattern) {
			if (preg_match($pattern, $lowerName)) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Extract honorific, handling compound honorifics like "Reverend Dr."
	 */
	private function extractHonorific($parts, $startIndex)
	{
		if ($startIndex >= count($parts)) {
			return null;
		}

		// Check for three-word compound honorifics first (rare but possible)
		if ($startIndex + 2 < count($parts)) {
			$triple = strtolower(str_replace(['.', ','], '', $parts[$startIndex] . ' ' . $parts[$startIndex + 1] . ' ' . $parts[$startIndex + 2]));
			if (in_array($triple, $this->honorifics)) {
				return [
					'text' => $parts[$startIndex] . ' ' . $parts[$startIndex + 1] . ' ' . $parts[$startIndex + 2],
					'newIndex' => $startIndex + 3
				];
			}
		}

		// Check for compound honorifics (like "Reverend Dr." or "Lt. Col.")
		if ($startIndex + 1 < count($parts)) {
			$compound = strtolower(str_replace(['.', ','], '', $parts[$startIndex] . ' ' . $parts[$startIndex + 1]));
			if (in_array($compound, $this->honorifics)) {
				return [
					'text' => $parts[$startIndex] . ' ' . $parts[$startIndex + 1],
					'newIndex' => $startIndex + 2
				];
			}
		}

		// Check for single honorific
		$single = strtolower(str_replace(['.', ','], '', $parts[$startIndex]));
		if (in_array($single, $this->honorifics)) {
			return [
				'text' => $parts[$startIndex],
				'newIndex' => $startIndex + 1
			];
		}

		return null;
	}



	/**
	 * Check if "Senior" or "Junior" should be treated as a last name rather than suffix
	 */
	private function isSeniorJuniorLastName($parts)
	{
		if (count($parts) != 2) {
			return false;
		}

		$secondPart = strtolower(trim($parts[1]));
		
		// If it's exactly "Senior" or "Junior" (not "Sr." or "Jr."), treat as last name
		return in_array($secondPart, ['senior', 'junior']);
	}

	/**
	 * Check if a name part is just an initial (single letter with optional period)
	 */
	private function isInitial($part)
	{
		$part = trim($part);
		return preg_match('/^[A-Z]\.?$/', $part);
	}

	/**
	 * Remove middle initials from name parts array
	 */
	private function removeMiddleInitials($parts)
	{
		if (!is_array($parts) || count($parts) <= 2) {
			return $parts;
		}
		
		$cleaned = [];
		
		// Always keep the first part (unless it's an initial)
		if (!$this->isInitial($parts[0])) {
			$cleaned[] = $parts[0];
		}
		
		// For middle parts, skip initials
		for ($i = 1; $i < count($parts) - 1; $i++) {
			if (!$this->isInitial($parts[$i])) {
				$cleaned[] = $parts[$i];
			}
		}
		
		// Always keep the last part
		if (count($parts) > 0) {
			$cleaned[] = $parts[count($parts) - 1];
		}
		
		// Ensure we have at least one part
		return !empty($cleaned) ? $cleaned : $parts;
	}

	/**
	 * Clean special cases that cause parsing issues
	 */
	private function cleanSpecialCases($name)
	{
		// First, handle nicknames in quotes more carefully
		$name = $this->cleanNicknames($name);
		
		// Remove graduation years (e.g., John Smith '22 -> John Smith)
		$name = preg_replace("/\s*'[0-9]{2,4}[A-Z]*\s*/", ' ', $name);
		$name = preg_replace("/\s*[A-Z]+'[0-9]{2,4}\s*/", ' ', $name);
		$name = preg_replace("/\s*,\s*[A-Z]+'[0-9]{2,4}\s*/", ' ', $name);
		
		// Comprehensive credential removal - improved
		$name = $this->cleanCredentials($name);
		
		// Remove single letters at the end (likely initials that got separated)
		$name = preg_replace('/\s+[A-Z]\s*$/', '', $name);
		
		// Remove parenthetical information
		$name = preg_replace('/\s*\([^)]*\)\s*/', ' ', $name);
		
		// Remove bracketed information
		$name = preg_replace('/\s*\[[^\]]*\]\s*/', ' ', $name);
		
		// Remove year patterns that might be graduation years
		$name = preg_replace('/\s*\b(19|20)\d{2}\b\s*/', ' ', $name);
		
		// Clean up multiple spaces
		$name = preg_replace('/\s+/', ' ', $name);
		
		return trim($name);
	}

	/**
	 * Better nickname cleaning
	 */
	private function cleanNicknames($name)
	{
		// Handle nicknames in quotes - preserve the main name, remove the nickname
		// Pattern: FirstName "Nickname" LastName -> FirstName LastName
		$name = preg_replace('/(\w+)\s*[""]([^"""]+)["\"]\s*(\w+)/', '$1 $3', $name);
		
		// Handle cases where nickname is at the end: FirstName "Nickname"
		$name = preg_replace('/(\w+)\s*[""]([^"""]+)["\"]\s*$/', '$1', $name);
		
		// Handle regular quotes too
		$name = preg_replace('/(\w+)\s*"([^"]+)"\s*(\w+)/', '$1 $3', $name);
		$name = preg_replace('/(\w+)\s*"([^"]+)"\s*$/', '$1', $name);
		
		return $name;
	}

	/**
	 * Clean credentials but KEEP them as suffixes
	 */
	private function cleanCredentials($name)
	{
		// Don't remove credentials during cleaning - let suffix extraction handle them
		// Just clean up graduation years and obvious non-credential items
		
		if (strpos($name, ',') === false) {
			return $name;
		}

		$parts = explode(',', $name);
		$mainName = trim($parts[0]);
		$keepParts = [$mainName];
		
		// Only remove obvious non-credential items like graduation years
		for ($i = 1; $i < count($parts); $i++) {
			$part = trim($parts[$i]);
			
			// Skip empty parts
			if (empty($part)) {
				continue;
			}
			
			$shouldRemove = false;
			
			// Remove graduation years
			if (preg_match('/^\'?\d{2,4}$/', $part) || preg_match('/^[A-Z]+\'?\d{2,4}$/', $part)) {
				$shouldRemove = true;
			}
			
			// Keep everything else (credentials, military designations, etc.)
			if (!$shouldRemove) {
				$keepParts[] = $part;
			}
		}
		
		return implode(', ', $keepParts);  // Keep commas for credential parsing later
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
		
		if (empty($parts) || !is_array($parts)) {
			return $result;
		}
		
		$partsCount = count($parts);
		
		// Safety check for empty array
		if ($partsCount === 0) {
			return $result;
		}
		
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
				$wordsBefore = empty(trim($beforePrefix)) ? 0 : count(explode(' ', trim($beforePrefix)));
				
				// Only consider this a valid split if there's at least one word before the prefix
				if ($wordsBefore >= 1) {
					$splitPoint = $wordsBefore;
					$foundPrefix = true;
					break;
				}
			}
		}
		
		if ($foundPrefix && $splitPoint !== null) {
			// Split at the prefix
			$firstParts = array_slice($parts, 0, $splitPoint);
			$lastParts = array_slice($parts, $splitPoint);
			
			$result['first'] = implode(' ', $firstParts);
			$result['last'] = implode(' ', $lastParts);
		} else {
			// No prefix found, use default logic
			// For 3+ parts, typically: [First] [Middle...] [Last]
			$result['first'] = $parts[0];
			$result['last'] = implode(' ', array_slice($parts, 1));
		}
		
		return $result;
	}
	
	/**
	 * For exactly 3 parts, detect compound first names vs compound last names
	 */
	private function detectCompoundNames($parts)
	{
		if (count($parts) !== 3) {
			return null;
		}
		
		$fullName = implode(' ', $parts);
		$possibleCompoundFirst = strtolower($parts[0] . ' ' . $parts[1]);
		$possibleCompoundLast = strtolower($parts[1] . ' ' . $parts[2]);
		
		// Check if first two parts form a compound first name
		$isCompoundFirst = in_array($possibleCompoundFirst, $this->compoundFirstNames);
		
		// Check if last two parts suggest a compound last name
		$suggestsCompoundLast = $this->suggestsCompoundLastName($fullName);
		
		if ($isCompoundFirst && !$suggestsCompoundLast) {
			// Clear compound first name
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

?>