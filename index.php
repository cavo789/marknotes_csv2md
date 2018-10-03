<?php

declare(strict_types=1);

/**
 * CSV to markdown converter.
 * Need to be rework for adding a form where we can specify the input string,
 * delimiters (like "," or ";"), click on a submit button and, thanks to an ajax
 * request, get the result in a dynamic div; without leaving the form.
 *
 * Class "CSVTable" based on https://github.com/mre/CSVTable
 * 	- Add a transpose feature
 *		- Modified for PHP 7 compatibility
 *		- Add the column separator as first / last character of the line
 *		- Add a space before / after the column separator
 */
class CSVTable
{
	public function __construct(
		string $csv,
		string $delim = ',',
		string $enclosure = '"',
		string $table_separator = '|',
		bool $transpose = false
	) {
		$this->csv = $csv;
		$this->delim = $delim;
		$this->enclosure = $enclosure;
		$this->table_separator = $table_separator;

		// Fill the rows with Markdown output
		$this->header = ''; 	// Table header
		$this->rows = ''; 	// Table rows

		$this->CSVtoTable($transpose);
	}

	/**
	 * Transpose a two-dimensional array
	 *
	 * ### Example
	 *
	 * We've an array by user and, for each user, we have a question and
	 * the answer.
	 *
	 * $in = [
	 * 	'User1' => [
	 * 		'Question1' => 'Answer User1 - Q1',
	 * 		'Question2' => 'Answer User1 - Q2',
	 * 		'Question3' => 'Answer User1 - Q3'
	 * 	],
	 * 	'User2' => [
	 * 		'Question1' => 'Answer User2 - Q1',
	 * 		'Question2' => 'Answer User2 - Q2',
	 * 		'Question3' => 'Answer User2 - Q3'
	 * 	],
	 * 	'User3' => [
	 * 		'Question1' => 'Answer User3 - Q1',
	 * 		'Question2' => 'Answer User3 - Q2',
	 * 		'Question3' => 'Answer User3 - Q3'
	 * 	]
	 * ];
	 *
	 * We can transpose the array to have first the question then
	 * the answer given to that question by each user.
	 *
	 * So User->Question->Answer should become Question->User->Answer
	 *
	 * $out = Transpose($in);
	 *
	 * This will give:
	 *
	 * $out = [
	 *		'Question1' => [
	 *			'User1' => 'Answer User1 - Q1',
	 *			'User2' => 'Answer User2 - Q1',
	 *			'User3' => 'Answer User3 - Q1'
	 *		],
	 *		'Question2' => [
	 *			'User1' => 'Answer User1 - Q2',
	 *			'User2' => 'Answer User2 - Q2',
	 *			'User3' => 'Answer User3 - Q2'
	 *		],
	 *		'Question3' => [
	 *			'User1' => 'Answer User1 - Q3',
	 *			'User2' => 'Answer User2 - Q3',
	 *			'User3' => 'Answer User3 - Q3'
	 *		]
	 *	]
	 *
	 *
	 * @link https://stackoverflow.com/questions/797251/transposing-multidimensional-arrays-in-php/797268#797268
	 *
	 * @param  array $arr
	 * @return array
	 */
	private function transpose(array $arr) : array
	{
		$out = [];
		foreach ($arr as $key => $subarr) {
			foreach ($subarr as $subkey => $subvalue) {
				$out[$subkey][$key] = $subvalue;
			}
		}

		return $out;
	}

	/**
	 * Convert a string into an array
	 *
	 * @param  boolean $transpose True if the orientation of the table
	 *                            should be changed. Works ONLY when the table
	 *                            is composed of two rows.
	 * @return void
	 */
	private function CSVtoTable(bool $transpose = false)
	{
		$parsed_array = $this->toArray($this->csv);

		// Transpose only when the array has exactly two rows
		if ((count($parsed_array) == 2) && ($transpose)) {
			$parsed_array = self::transpose($parsed_array);
			// Add, as the first row, a heading row
			$arrHeader = ['code', 'value'];
			array_unshift($parsed_array, $arrHeader);
		}

		$this->length = $this->minRowLength($parsed_array);
		$this->col_widths = $this->maxColumnWidths($parsed_array);
		$header_array = array_shift($parsed_array);
		$this->header = $this->createHeader($header_array);
		$this->rows = $this->createRows($parsed_array);
	}

	/**
	 * Convert the CSV into a PHP array
	 *
	 * @param  string $csv Convert a string into a PHP array
	 * @return array
	 */
	public function toArray(string $csv) : array
	{
		$parsed = str_getcsv($csv, "\n"); // Parse the rows
		$output = [];
		foreach ($parsed as &$row) {
			$row = str_getcsv($row, $this->delim, $this->enclosure); // Parse the items in rows
			array_push($output, $row);
		}

		return $output;
	}

	private function createHeader(array $header_array) : string
	{
		return $this->createRow($header_array) . $this->createSeparator();
	}

	private function createSeparator() : string
	{
		$output = $this->table_separator . ' ';
		for ($i = 0; $i < $this->length - 1; ++$i) {
			$output .= str_repeat('-', $this->col_widths[$i]);
			$output .= ' ' . $this->table_separator . ' ';
		}
		$last_index = $this->length - 1;
		$output .= str_repeat('-', $this->col_widths[$last_index]);

		return $output . ' ' . $this->table_separator . "\n";
	}

	protected function createRows(array $rows) : string
	{
		$output = '';
		foreach ($rows as $row) {
			$output .= $this->createRow($row);
		}

		return $output;
	}

	/**
	 * Add padding to a string
	 */
	private function padded(string $str, int $width) : string
	{
		if ($width < strlen($str)) {
			return $str;
		}
		$padding_length = $width - strlen($str);
		$padding = str_repeat(' ', $padding_length);

		return $str . $padding;
	}

	protected function createRow(array $row) : string
	{
		$output = $this->table_separator . ' ';
		// Only create as many columns as the minimal number of elements
		// in all rows. Otherwise this would not be a valid Markdown table
		for ($i = 0; $i < $this->length - 1; ++$i) {
			$element = $this->padded(trim($row[$i]), $this->col_widths[$i]);
			$output .= $element;
			$output .= ' ' . $this->table_separator . ' ';
		}
		// Don't append a separator to the last element
		$last_index = $this->length - 1;
		$element = $this->padded($row[$last_index], $this->col_widths[$last_index]);
		$output .= $element;
		$output .= ' ' . $this->table_separator . "\n"; // row ends with a newline
		return $output;
	}

	private function minRowLength(array $arr) : int
	{
		$min = PHP_INT_MAX;
		foreach ($arr as $row) {
			$row_length = count($row);
			if ($row_length < $min) {
				$min = $row_length;
			}
		}

		return $min;
	}

	/*
	 * Calculate the maximum width of each column in characters
	 */
	private function maxColumnWidths(array $arr) : array
	{
		// Set all column widths to zero.
		$column_widths = array_fill(0, $this->length, 0);
		foreach ($arr as $row) {
			foreach ($row as $k => $v) {
				if ($column_widths[$k] < strlen($v)) {
					$column_widths[$k] = strlen($v);
				}
				if ($k == $this->length - 1) {
					// We don't need to look any further since these elements
					// will be dropped anyway because all table rows must have the
					// same length to create a valid Markdown table.
					break;
				}
			}
		}

		return $column_widths;
	}

	public function getMarkup() : string
	{
		return $this->header . $this->rows;
	}
}

$input =
	"# tid, participant_id, firstname, lastname, email, emailstatus, token, language, blacklisted, sent, remindersent, remindercount, completed, usesleft, validfrom, validuntil, mpid, attribute_1, attribute_2, attribute_3, attribute_4, attribute_5, attribute_6, attribute_7, attribute_8, attribute_9, attribute_10, attribute_11, attribute_12, attribute_13, attribute_14, attribute_15
'1', NULL, '', '1', '', 'OK', '448F2zMsawul0xr', 'nl', NULL, 'N', 'N', '0', '2018-09-27 07:44', '0', NULL, NULL, NULL, '2', 'Argus Filch', 'Gilderoy Lockhart', 'Nearly Beheaded Nick', NULL, NULL, NULL, NULL, NULL, 'Nieuwe test', 'Nouveau test', 'admin admin', '20180512', '64290', NULL
";

// Delimiters between columns (, or ; or ...)
$delim = ',';

// In case of the text are between quotes like, for instance,
//   "field1","field2", ...   If so, mention '"' as value for $enclosure
$enclosure = '';

// Separator to use in markdown to separate columns ('|' is the standard)
$separator = '|';

// Transpose will works ONLY when there is two records in the
// input string. Instead of having a long "horizontal" table, convert
// the table vertically. The result table will have two columns and as
// many rows that there was columns in the string
$bTranspose = true;	// <=== WORKS ONLY if the array has two records

// Create a new CSV parser
$parser = new CSVTable($input, $delim, $enclosure, $separator, $bTranspose);

// Create a Markdown table from the parsed input
echo '<pre>' . $parser->getMarkup() . '</pre>';
