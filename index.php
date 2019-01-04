<?php

declare(strict_types = 1);

/*
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
 *     - Modified for PHP 7 compatibility
 *     - Add a transpose feature
 *     - Add the column separator as first / last character of the line
 *     - Add a space before / after the column separator
 *     - Add an interface for easily use the conversion tool
 *
 * Last mod:
 * 2019-01-01 - Abandonment of jQuery and migration to vue.js
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
        $this->csv             = $csv;
        $this->delim           = $delim;
        $this->enclosure       = $enclosure;
        $this->table_separator = $table_separator;

        // Fill the rows with Markdown output
        $this->header = '';     // Table header
        $this->rows   = '';     // Table rows

        $this->CSVtoTable($transpose);
    }

    /**
     * Convert the CSV into a PHP array.
     *
     * @param string $csv Convert a string into a PHP array
     *
     * @return array
     */
    public function toArray(string $csv): array
    {
        $parsed = str_getcsv($csv, "\n"); // Parse the rows
        $output = [];
        foreach ($parsed as &$row) {
            $row = str_getcsv($row, $this->delim, $this->enclosure); // Parse the items in rows
            array_push($output, $row);
        }

        return $output;
    }

    public function getMarkup(): string
    {
        return $this->header . $this->rows;
    }

    protected function createRows(array $rows): string
    {
        $output = '';
        foreach ($rows as $row) {
            $output .= $this->createRow($row);
        }

        return $output;
    }

    protected function createRow(array $row): string
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

    /**
     * Transpose a two-dimensional array.
     *
     * ### Example
     *
     * We've an array by user and, for each user, we have a question and
     * the answer.
     *
     * $in = [
     *     'User1' => [
     *         'Question1' => 'Answer User1 - Q1',
     *         'Question2' => 'Answer User1 - Q2',
     *         'Question3' => 'Answer User1 - Q3'
     *     ],
     *     'User2' => [
     *         'Question1' => 'Answer User2 - Q1',
     *         'Question2' => 'Answer User2 - Q2',
     *         'Question3' => 'Answer User2 - Q3'
     *     ],
     *     'User3' => [
     *         'Question1' => 'Answer User3 - Q1',
     *         'Question2' => 'Answer User3 - Q2',
     *         'Question3' => 'Answer User3 - Q3'
     *     ]
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
     *        'Question1' => [
     *            'User1' => 'Answer User1 - Q1',
     *            'User2' => 'Answer User2 - Q1',
     *            'User3' => 'Answer User3 - Q1'
     *        ],
     *        'Question2' => [
     *            'User1' => 'Answer User1 - Q2',
     *            'User2' => 'Answer User2 - Q2',
     *            'User3' => 'Answer User3 - Q2'
     *        ],
     *        'Question3' => [
     *            'User1' => 'Answer User1 - Q3',
     *            'User2' => 'Answer User2 - Q3',
     *            'User3' => 'Answer User3 - Q3'
     *        ]
     *    ]
     *
     *
     * @see https://stackoverflow.com/questions/797251/transposing-multidimensional-arrays-in-php/797268#797268
     *
     * @param array $arr
     *
     * @return array
     */
    private function transpose(array $arr): array
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
     * Convert a string into an array.
     *
     * @param bool $transpose True if the orientation of the table
     *                        should be changed. Works ONLY when the table
     *                        is composed of two rows.
     *
     * @return void
     */
    private function CSVtoTable(bool $transpose = false)
    {
        $parsed_array = $this->toArray($this->csv);

        // Transpose only when the array has exactly two rows
        if ((2 == count($parsed_array)) && ($transpose)) {
            $parsed_array = self::transpose($parsed_array);
            // Add, as the first row, a heading row
            $arrHeader = ['code', 'value'];
            array_unshift($parsed_array, $arrHeader);
        }

        $this->length     = $this->minRowLength($parsed_array);
        $this->col_widths = $this->maxColumnWidths($parsed_array);
        $header_array     = array_shift($parsed_array);
        $this->header     = $this->createHeader($header_array);
        $this->rows       = $this->createRows($parsed_array);
    }

    private function createHeader(array $header_array): string
    {
        return $this->createRow($header_array) . $this->createSeparator();
    }

    private function createSeparator(): string
    {
        $output = $this->table_separator . ' ';
        for ($i = 0; $i < $this->length; ++$i) {
            $output .= str_repeat('-', $this->col_widths[$i]);
            $output .= ' ' . $this->table_separator . ' ';
        }

        return trim($output) . "\n";
    }

    /**
     * Add padding to a string.
     */
    private function padded(string $str, int $width): string
    {
        if ($width < strlen($str)) {
            return $str;
        }
        $padding_length = $width - strlen($str);
        $padding        = str_repeat(' ', $padding_length);

        return $str . $padding;
    }

    private function minRowLength(array $arr): int
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

    // Calculate the maximum width of each column in characters
    private function maxColumnWidths(array $arr): array
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
}

// Retrieve posted data
$data = json_decode(file_get_contents('php://input'), true);
if ($data !== []) {
    $task = filter_var(($data['task'] ?? ''), FILTER_SANITIZE_STRING);

    if ('convert' == $task) {
        // Retrieve the CSV content
        $csv = base64_decode(filter_var(($data['csv'] ?? ''), FILTER_SANITIZE_STRING));

        // Delimiters between columns (, or ; or ...)
        $delim = base64_decode($data['delim'] ?? '');
        if ('' == trim($delim)) {
            $delim = ',';
        }

        // In case of the text are between quotes like, for instance,
        //   "field1","field2", ...   If so, mention '"' as value for $enclosure
        $enclosure = base64_decode($data['enclosure'] ?? '');

        // Separator to use in markdown to separate columns ('|' is the standard)
        $separator = base64_decode($data['separator'] ?? '');

        if ('' == trim($separator)) {
            $separator = '|';
        }

        // Transpose will works ONLY when there is two records in the
        // input string. Instead of having a long "horizontal" table, convert
        // the table vertically. The result table will have two columns and as
        // many rows that there was columns in the string
        $bTranspose = boolval(filter_var(($data['transpose'] ?? ''), FILTER_VALIDATE_BOOLEAN));

        // Create a new CSV parser
        $parser = new CSVTable($csv, $delim, $enclosure, $separator, $bTranspose);

        // Create a Markdown table from the parsed input
        header('Content-Type: text/plain');
        echo base64_encode($parser->getMarkup());
        die();
    }
}

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
            <div class="container" id="app">
                <div class="form-group">
                    <how-to-use demo="https://raw.githubusercontent.com/cavo789/marknotes_csv2md/master/images/demo.gif">
                        <ul>
                            <li>Copy/Paste your CSV content in the textbox below</li>
                            <li>Update one or more options</li>
                            <li>If you've only two lines, you can select Transpose</li>
                            <li>Click on the Convert button</li>
                        </ul>
                    </how-to-use>
                    <label for="csv">Copy/Paste your CSV content in the textbox below then click on the Convert button:</label>
                    <textarea class="form-control" rows="5" v-model="CSV" name="csv"></textarea>
                </div>
                <div class="row">
                    <form class="form-inline">
                        <div class="form-check mr-sm-3">
                            <input type="checkbox" class="form-check-input" v-model="transpose">&nbsp;
                            <label class="form-check-label" for="transpose">Transpose</label>
                        </div>
                        <div class=" form-group mr-sm-3">
                            <label for="delim">Delimiter:</label>&nbsp;
                            <input type="text" style="width:50px;" size="3" maxlength="3" v-model="delim" class="form-control">
                        </div>
                        <div class=" form-group mr-sm-3">
                            <label for="enclosure">Quote:</label>&nbsp;
                            <input type="text" style="width:50px;" size="3" maxlength="3" v-model="enclosure" class="form-control">
                        </div>
                        <div class=" form-group mr-sm-3">
                            <label for="separator">Separator:</label>&nbsp;
                            <input type="text" style="width:50px;" size="3" maxlength="3" v-model="separator" class="form-control">
                        </div>
                    </form>
                </div>
                <button type="button" @click="doConvert" class="btn btn-primary">Convert</button>
                <hr/>
                <div v-if="Markdown!==''">
                    <h2 id="markdown">Markdown code <small style="font-size:0.4em"><a href="#html">See HTML rendering</a></small></h2>
                    <pre v-html="Markdown"></pre>
                    <hr/>
                </div>
                <div v-if="HTML!==''">
                    <h2 id="html">HTML rendering <small style="font-size:0.4em"><a href="#markdown">See Markdown code</a></small></h2>
                    <pre v-html="HTML"></pre>
                    <hr/>
                </div>
            </div>
        </div>

        <script src="https://unpkg.com/vue"></script>
        <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
        <script src="https://unpkg.com/marked@0.3.6"></script>
        <script type="text/javascript">
            Vue.component('how-to-use', {
                props: {
                    demo: {
                        type: String,
                        required: true
                    }
                },
                template:
                    `<details>
                        <summary>How to use?</summary>
                        <div class="row">
                                <div class="col-sm">
                                    <slot></slot>
                                </div>
                                <div class="col-sm"><img v-bind:src="demo" alt="Demo"></div>
                            </div>
                        </div>
                    </details>`
            });

            var app = new Vue({
                el: '#app',
                data: {
                    CSV: "Column 1 Header,Column 2 Header\n" +
                        "Row 1-1,Row 1-2\n" +
                        "Row 2-1,Row 2-2",
                    transpose: false,
                    delim: ',',
                    enclosure: '"',
                    separator: '|',
                    Markdown: ''
                },
                methods: {
                    doConvert() {
                        var $data = {
                            task: 'convert',
                            csv: window.btoa(this.CSV),
                            transpose: this.transpose,
                            delim: window.btoa(this.delim),
                            enclosure: window.btoa(this.enclosure),
                            separator: window.btoa(this.separator)
                        }
                        axios.post('<?php echo basename(__FILE__); ?>', $data)
                            .then(response => (this.Markdown = window.atob(response.data)))
                            .catch(function (error) {console.log(error);});
                    }
                },
                computed: {
                    HTML() {
                        if (this.Markdown == '') {
                            return '';
                        }
                        // Call marked() to convert the MD string into a HTML table
                        var mdTable = marked(this.Markdown, { sanitize: true });
                        // Add Boostrap classes
                        mdTable = mdTable.replace('<table>', '<table class="table table-hover table-striped">');
                        return mdTable;
                    }
                }
            });
        </script>
    </body>
</html>
