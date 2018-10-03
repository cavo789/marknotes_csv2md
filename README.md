# marknotes_csv2md

CSV to markdown converter

Very straight-forward (=no interface) script for converting a CSV content to a Markdown table.

Convert string like

```text
Column 1 Header,Column 2 Header
Row 1-1,Row 1-2
Row 2-1,Row 2-2
```

into

```markdown
| Column 1 Header | Column 2 Header |
| --------------- | --------------- |
| Row 1-1         | Row 1-2         |
| Row 2-1         | Row 2-2         |
```

**Note: there is not yet an interface for this, you'll need to modify the `index.php` script and inject your CSV content.**

Just edit index.php and edit this line:

```php
$input = 
	"Column 1 Header,Column 2 Header\n".
	"Row 1-1,Row 1-2\n".
	"Row 2-1,Row 2-2";
```

When done run the index.php script and get the content.

## Source

The `CSVTable` has been written by `Matthias Endler` and available on GitHub: https://github.com/mre/CSVTable.

The script has been quickly modified for:

* Add a transpose feature
* Modified for PHP 7 compatibility
* Add the column separator as first / last character of the line
* Add a space before / after the column separator
