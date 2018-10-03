<?php
declare(strict_types=1);

/**
 * CSV to markdown converter.
 * Need to be rework for adding a form where we can specify the input string,
 * delimiters (like "," or ";"), click on a submit button and, thanks to an ajax
 * request, get the result in a dynamic div; without leaving the form.
 *
 * Class "CSVTable" based on https://github.com/mre/CSVTable 
 *		- Modified for PHP 7 compatibility
 *		- Add the column separator as first / last character of the line
 *		- Add a space before / after the column separator
 */
 
class CSVTable
{
	public function __construct(string $csv, string $delim = ',', string $enclosure = '"', string $table_separator = '|')
	{
		$this->csv = $csv;
		$this->delim = $delim;
		$this->enclosure = $enclosure;
		$this->table_separator = $table_separator;
		
		// Fill the rows with Markdown output
		$this->header = ''; // Table header
		$this->rows = ''; // Table rows
		$this->CSVtoTable($this->csv);
	}

	private function CSVtoTable()
	{
		$parsed_array = $this->toArray($this->csv);
		$this->length = $this->minRowLength($parsed_array);
		$this->col_widths = $this->maxColumnWidths($parsed_array);
		$header_array = array_shift($parsed_array);
		$this->header = $this->createHeader($header_array);
		$this->rows = $this->createRows($parsed_array);
	}

	/**
	 * Convert the CSV into a PHP array
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
			$output .= ' '.$this->table_separator.' ';
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
			$element = $this->padded($row[$i], $this->col_widths[$i]);
			$output .= $element;
			$output .= ' '.$this->table_separator.' ';
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
	"Column 1 Header,Column 2 Header\n".
	"Row 1-1,Row 1-2\n".
	"Row 2-1,Row 2-2";

// Create a new CSV parser
$parser = new CSVTable($input);

// Create a Markdown table from the parsed input
echo '<pre>'.$parser->getMarkup().'</pre>';