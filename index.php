<?php

declare(strict_types=1);

/**
 * AUTHOR : AVONTURE Christophe
 *
 * Written date : 3 october 2018
 *
 * CSV to markdown converter.
 * Need to be rework for adding a form where we can specify the input string,
 * delimiters (like "," or ";"), click on a submit button and, thanks to an ajax
 * request, get the result in a dynamic div; without leaving the form.
 *
 * Class "CSVTable" based on https://github.com/mre/CSVTable
 * 	- Modified for PHP 7 compatibility
 * 	- Add a transpose feature
 * 	- Add the column separator as first / last character of the line
 * 	- Add a space before / after the column separator
 * 	- Add an interface for easily use the conversion tool
 */

define('REPO', 'https://github.com/cavo789/marknotes_csv2md');

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
		for ($i = 0; $i < $this->length; ++$i) {
			$output .= str_repeat('-', $this->col_widths[$i]);
			$output .= ' ' . $this->table_separator . ' ';
		}

		return trim($output) . "\n";
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
		for ($i = 0; $i < $this->length; ++$i) {
			$element = $this->padded(trim($row[$i]), $this->col_widths[$i]);
			$output .= $element;
			$output .= ' ' . $this->table_separator . ' ';
		}

		$output = trim($output) . "\n"; // row ends with a newline
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

$task = filter_input(INPUT_POST, 'task', FILTER_SANITIZE_STRING);

if ($task == 'convert') {
	// Retrieve the CSV content
	$csv = base64_decode(filter_input(INPUT_POST, 'csv', FILTER_SANITIZE_STRING));

	// Delimiters between columns (, or ; or ...)
	$delim = base64_decode(filter_input(INPUT_POST, 'delim', FILTER_SANITIZE_STRING));
	if (trim($delim) == '') {
		$delim = ',';
	}

	// In case of the text are between quotes like, for instance,
	//   "field1","field2", ...   If so, mention '"' as value for $enclosure
	$enclosure = base64_decode(filter_input(INPUT_POST, 'enclosure', FILTER_SANITIZE_STRING));

	// Separator to use in markdown to separate columns ('|' is the standard)
	$separator = base64_decode(filter_input(INPUT_POST, 'separator', FILTER_SANITIZE_STRING));
	if (trim($separator) == '') {
		$separator = '|';
	}

	// Transpose will works ONLY when there is two records in the
	// input string. Instead of having a long "horizontal" table, convert
	// the table vertically. The result table will have two columns and as
	// many rows that there was columns in the string
	$bTranspose = boolval(filter_input(INPUT_POST, 'transpose', FILTER_VALIDATE_BOOLEAN));

	// Create a new CSV parser
	$parser = new CSVTable($csv, $delim, $enclosure, $separator, $bTranspose);

	// Create a Markdown table from the parsed input
	header('Content-Type: text/html');
	echo $parser->getMarkup();

	die();
}

// Sample and default values
$csv =
	"Column 1 Header,Column 2 Header\n" .
	"Row 1-1,Row 1-2\n" .
	'Row 2-1,Row 2-2';

$delim = ',';
$enclosure = ',';
$separator = '|';

// Get the GitHub corner
$github = '';
if (is_file($cat = __DIR__ . DIRECTORY_SEPARATOR . 'octocat.tmpl')) {
	$github = str_replace('%REPO%', REPO, file_get_contents($cat));
}
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8"/>
		<meta name="author" content="Christophe Avonture" />
		<meta name="robots" content="noindex, nofollow" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=9; IE=8;" />
		<title>Marknotes - CSV2MD</title>
		<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
	</head>
	<body>
		<?php echo $github; ?>
		<div class="container">
			<div class="page-header"><h1>Marknotes - CSV2MD</h1></div>
			<div class="container">

				<div class="form-group">
					<label for="csv">Copy/Paste your CSV content in the textbox below then click on the Convert button:</label>
					<textarea class="form-control" rows="5" id="csv" name="csv"><?php echo $csv; ?></textarea>
				</div>
				<div class="row">
					<form class="form-inline">
						<div class="form-check mr-sm-3">
							<input type="checkbox" class="form-check-input" id="transpose">&nbsp;
							<label class="form-check-label" for="transpose">Transpose</label>
						</div>
						<div class=" form-group mr-sm-3">
							<label for="delim">Delimiter:</label>&nbsp;
							<input type="text" style="width:50px;" size="3" value="<?php echo $delim;?>" class="form-control" id="delim">
						</div>
						<div class=" form-group mr-sm-3">
							<label for="enclosure">Quote:</label>&nbsp;
							<input type="text" style="width:50px;" size="3" value="<?php echo $enclosure; ?>" class="form-control" id="enclosure">
						</div>
						<div class=" form-group mr-sm-3">
							<label for="separator">Separator:</label>&nbsp;
							<input type="text" style="width:50px;" size="3" value="<?php echo $separator; ?>" class="form-control" id="separator">
						</div>
					</form>
				</div>
				<button type="button" id="btnConvert" class="btn btn-primary">Convert</button>
				<hr/>
				<pre id="Result"></pre>
			</div>
		</div>
		<script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
		<script type="text/javascript" src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>
		<script type="text/javascript">
			$('#btnConvert').click(function(e)  {

				e.stopImmediatePropagation();

				var $data = new Object;
				$data.task = "convert";
				$data.csv = window.btoa($('#csv').val());
				$data.transpose = $("#transpose").is(':checked') ? 1 : 0;
				$data.delim = window.btoa($('#delim').val());
				$data.enclosure = window.btoa($('#enclosure').val());
				$data.separator = window.btoa($('#separator').val());

				$.ajax({
					beforeSend: function() {
						$('#Result').html('<div><span class="ajax_loading">&nbsp;</span><span style="font-style:italic;font-size:1.5em;">Converting...</span></div>');
						$('#btnConvert').prop("disabled", true);
					},
					async: true,
					type: "POST",
					url: "<?php echo basename(__FILE__); ?>",
					data: $data,
					datatype: "html",
					success: function (data) {
						$('#btnConvert').prop("disabled", false);
						$('#Result').html(data);
					}
				}); // $.ajax()
			});
		</script>
	</body>
</html>
