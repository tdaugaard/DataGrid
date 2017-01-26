<?php
/**
 * DataGrid - A library for displaying ASCII tables in PHP-CLI.
 *
 * @category Libraries
 * @package  DataGrid
 * @license  New BSD License
 * @link     https://github.com/tdaugaard
 *
 * Copyright (c) 2016, Thomas Daugaard
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the <organization> nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
declare(strict_types=1);

require_once "Filter.php";
require_once "Tools.php";
require_once "Column/Abstract.php";
require_once "Column/Value.php";
require_once "Column/String.php";
require_once "Column/Integer.php";
require_once "Column/Float.php";
require_once "Column/Separator.php";

/**
 * ASCII table drawing characters
 */
define("LINE_TOP_LEFT",     "\u{250C}");
define("LINE_TOP_RIGHT",    "\u{2510}");
define("LINE_BOTTOM_LEFT",  "\u{2514}");
define("LINE_BOTTOM_RIGHT", "\u{2518}");
define("LINE_HORIZONTAL",   "\u{2500}");
define("LINE_VERTICAL",     "\u{2502}");
define("LINE_T_DOWN",       "\u{252C}");
define("LINE_T_UP",         "\u{2534}");
define("LINE_LEFT_T",       "\u{251C}");
define("LINE_RIGHT_T",      "\u{2524}");
define("LINE_CROSS",        "\u{253C}");
define("LINE_HELLIP",       "\u{2026}");
define("LINE_ARROW_DOWN",   "\u{25BC}");
define("LINE_ARROW_UP",     "\u{25B2}");

/**
 * Main DataGrid class
 */
class DataGrid_Table implements Countable, Iterator, ArrayAccess
{
    /**
     * Grid flag indicating we should hide column headers
     */
    const FLAG_HIDE_COLUMN_HEADERS = 0x01;

    /**
     * Column type flags
     */
    const COLUMN_TYPE_AUTO     = 1;
    const COLUMN_TYPE_STRING   = 2;
    const COLUMN_TYPE_INTEGER  = 4;
    const COLUMN_TYPE_FLOAT    = 8;

    /**
     * Column flag to enable sorting on it.
     */
    const COLUMN_FLAG_SORTABLE = 1;

    /**
     * Column flag to hide a column.
     */
    const COLUMN_FLAG_HIDDEN = 2;

    /**
     * Indicates left alignment regardless of column type
     */
    const COLUMN_FLAG_ALIGN_LEFT = 4;

    /**
     * Indicates right alignment regardless of column type
     */
    const COLUMN_FLAG_ALIGN_RIGHT = 8;

    /**
     * Indicates even alignment regardless of column type
     */
    const COLUMN_FLAG_ALIGN_EVEN = 16;

    /**
     * Data columns
     */
    protected $columns = [];

    /**
     * Actual data
     */
    protected $data = [];

    /**
     * Actual data (copy)
     */
    protected $data_orig = [];

    /**
     * Array holding calculated column header widths
     *
     */
    protected $column_widths = [];

    /**
     * Array holding data widths
     *
     */
    protected $data_widths = [];

    /**
     * Array of callbacks for filtering displayed content
     */
    protected $display_filters = [];

    /**
     * Current sorting key
     */
    protected $sort_key = null;

    /**
     * Current sorting direction
     */
    protected $sort_dir = SORT_ASC;

    /**
     * The width, in characters, of the terminal as returned by 'tput cols'
     */
    protected $terminal_width = 0;

    /**
     * Specifies whether we're allowed to truncate string columns for resizing
     * table data to fit the terminal width.
     */
    protected $allow_truncate_string_columns = true;

    /**
     * Grid flags (Flag* consts)
     */
    protected $flags = 0;

    /**
     * Grid title, if any.
     */
    protected $table_title = "";

    /**
     * Iterable current position
     */
    protected $it_pos = 0;

    /* Iterable methods */
    function rewind() {
        $this->it_pos = 0;
    }

    function current() {
        return $this->data[$this->it_pos];
    }

    function key() {
        return $this->it_pos;
    }

    function next() {
        ++$this->it_pos;
    }

    function valid() {
        return isset($this->data[$this->it_pos]);
    }

    /* ArrayAccess methods */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->data[] = $value;
            $this->data_orig[] = $value;
        } else {
            $this->data[$offset] = $value;
            $this->data_orig[$offset] = $value;
        }
    }

    public function offsetExists($offset) {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset) {
        unset($this->data[$offset]);
        unset($this->data_orig[$offset]);
    }

    public function offsetGet($offset) {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    /**
     * Generates the actual table and displays it
     *
     * @return DataGrid $this
     */
    public function display(): DataGrid_Table
    {
        $this->_filterData();

        if ($this->sort_key !== null) {
            $this->_sortData();
        }

        $this->_calculateColumnWidths();
        if (!$this->_calculateDataWidths()) {
            $this->_adjustColumnWidths();
        }

        $header         = empty($this->table_title) ? LINE_TOP_LEFT : LINE_LEFT_T;
        $footer         = LINE_BOTTOM_LEFT;
        $row_divider    = LINE_LEFT_T;
        $column_divider = LINE_LEFT_T;
        $column_list    = LINE_VERTICAL;

        // Exclude columns that aren't visible
        $columns = $this->getVisibleColumns();

        // Generate the header
        end($columns);
        $last_field_id = key($columns);
        foreach ($columns as $id => $col) {
            $is_last        = $id == $last_field_id;
            $col_width      = max($this->column_widths[$id], $this->data_widths[$id]);
            $col_name       = DataGrid_Tools::strPad((string)$col, $col_width, $col->getValueAligment());
            $straight_line  = str_repeat(LINE_HORIZONTAL, $col_width + 2);

            $header         .= $straight_line . ($is_last ? "" : LINE_T_DOWN);
            $footer         .= $straight_line . ($is_last ? "" : LINE_T_UP );
            $column_divider .= $straight_line . ($is_last ? "" : LINE_CROSS);
            $column_list    .= " " . $col_name . " " . ($is_last ? "" : LINE_VERTICAL);
        }

        $header         .= (empty($this->table_title) ? LINE_TOP_RIGHT : LINE_RIGHT_T) . PHP_EOL;
        $footer         .= LINE_BOTTOM_RIGHT . PHP_EOL;
        $column_divider .= LINE_RIGHT_T . PHP_EOL;
        $column_list    .= LINE_VERTICAL . PHP_EOL;

        $table = "";

        // Include the title if there is one
        if ($this->table_title) {
            $header_width = DataGrid_Tools::strLen($header) - 3;
            $title_width  = DataGrid_Tools::strLen($this->table_title);
            $table_width  = max($header_width, $title_width);

            $table  .= $header;
            $header  = LINE_TOP_LEFT . str_repeat(LINE_HORIZONTAL, $table_width + 2) . LINE_TOP_RIGHT . PHP_EOL;
            $header .= LINE_VERTICAL . " " . $this->table_title . str_repeat(" ", $table_width - $title_width) . " " . LINE_VERTICAL . PHP_EOL;
        }

        if (!($this->flags & static::FLAG_HIDE_COLUMN_HEADERS)) {
            $table .= $column_list . $column_divider;
        }

        foreach ($this->data as $row) {
            $table .= LINE_VERTICAL;

            foreach ($columns as $id => $col) {
                $data      = isset($row[$id]) ? $row[$id] : '';
                $col_width = max($this->column_widths[$id], $this->data_widths[$id]);

                // Is this a DataGrid value with raw/display values?
                if ($data instanceof DataGrid_Column_Value) {
                    $data = (string)$data;
                }

                $len = DataGrid_Tools::strLen($data);

                // Truncate data?
                if ($len > $col_width && $this->allow_truncate_string_columns) {
                    $data = DataGrid_Tools::subStr($data, 0, $col_width - 1) . LINE_HELLIP;

                // Pad data that is shorter than the column header
                } elseif ($len < $col_width) {
                    $data = DataGrid_Tools::strPad($data, $col_width - $len, $col->getValueAligment());
                }

                $table .= " " . $data . " " . LINE_VERTICAL;
            }

            $table .= PHP_EOL;
        }

        print $header . $table . $footer;

        return $this;
    }

    /**
     * Add a column to the grid.
     *
     * @param string $id
     * @param string $name
     * @param int    $type
     * @param mixed  $sorted false, SORT_ASC, or SORT_DESC.
     * @param int    $flags  A combination of static::COLUMN_FLAG_* const's
     *
     * @return DataGrid $this
     */
    public function addColumn(string $id, string $name, int $type = DataGrid_Table::COLUMN_TYPE_STRING, $sorted = false, int $flags = 0): DataGrid_Table
    {
        switch ($type) {
            case static::COLUMN_TYPE_STRING:
                $col = new DataGrid_Column_String($name, $sorted, $flags);
                break;

            case static::COLUMN_TYPE_INTEGER:
                $col = new DataGrid_Column_Integer($name, $sorted, $flags);
                break;

            case static::COLUMN_TYPE_FLOAT:
                $col = new DataGrid_Column_Float($name, $sorted, $flags);
                break;

            default:
                throw new DataGridNoSuchColumnTypeException($type);
        }

        // Since only one column can be sorted on, we need to remove the sort flag on
        // the current columns.
        if ($sorted !== false) {
            array_walk(
                $this->columns,
                function (&$v) {
                    $v->setSorted(false);
                }
            );

            $this->sort_key = $id;
        }

        $this->columns[$id] = $col;

        return $this;
    }

    /**
     * Returns an array of columns that can be sorted (has DataGrid_Table::COLUMN_FLAG_SORTABLE)
     *
     * @return array
     */
    public function getSortableColumns(): array
    {
        return array_filter(
            $this->columns,
            function ($v) {
                return $v->isSortable();
            }
        );
    }

    /**
     * Returns an array of visible (non-hidden) columns
     *
     * @return array
     */
    public function getVisibleColumns(): array
    {
        return array_filter(
            $this->columns,
            function ($v) {
                return $v->isVisible();
            }
        );
    }

    /**
     * Enables a flag
     *
     * @param int $flag Either of static::Flag* constants
     *
     * @return DataGrid $this
     */
    public function setFlag(int $flag): DataGrid_Table
    {
        $this->flags |= $flag;

        return $this;
    }

    /**
     * Sets the title of the grid
     *
     * @param string $title Title. Set to "" to disable.
     *
     * @return DataGrid $this
     */
    public function setTitle(string $title): DataGrid_Table
    {
        $this->table_title = $title;

        return $this;
    }

    /**
     * Returns the column given by $id
     *
     * @param string $id
     *
     * @return DataGrid $this\Column
     */
    public function getColumn(string $id): DataGrid_Column
    {
        return $this->columns[$id];
    }

    /**
     * Returns all column objects
     *
     * @return DataGrid $this\Column
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Returns all current display filters
     *
     * @return array
     */
    public function getFilters(): array
    {
        return $this->display_filters;
    }

    /**
     * Returns whether there are any filters
     *
     * @return bool
     */
    public function hasFilters(): bool
    {
        return !!count($this->display_filters);
    }

    /**
     * Overwrites any current filters and replaces them with whatever is given
     *
     * @param array $filters Array of DataGrid_Filter objects or callables
     *
     * @return DataGrid $this
     */
    public function setFilters(array $filters): DataGrid_Table
    {
        $this->display_filters = $filters;

        return $this;
    }

    /**
     * Adds a display filter. Anything that can be passed to array_filter() goes.
     *
     * @param callable $filter A DataGrid_Filter object or a callable
     *
     * @return DataGrid $this
     */
    public function addFilter(callable $filter): DataGrid_Table
    {
        $this->display_filters[] = $filter;

        return $this;
    }

    /**
     * Replaces an existing display filter. Anything that can be passed to array_filter() goes.
     *
     * @param int      $id     Numeric index as retrieved from getFilters()
     * @param callable $filter A DataGrid_Filter object or a callable
     *
     * @return DataGrid $this
     */
    public function replaceFilter(int $id, callable $filter): DataGrid_Table
    {
        $this->display_filters[$id] = $filter;

        return $this;
    }

    /**
     * Removes a display filter
     *
     * @param int $id Numeric index as retrieved from getFilters()
     *
     * @return DataGrid $this
     */
    public function removeFilter(int $id): DataGrid_Table
    {
        if (isset($this->display_filters[$id])) {
            unset($this->display_filters[$id]);
        }

        return $this;
    }

    /**
     * Clear all display filters
     *
     * @return DataGrid $this
     */
    public function clearFilters(): DataGrid_Table
    {
        $this->display_filters = [];

        return $this;
    }

    /**
     * Sort the data set according to the given column name
     *
     * @param string $id        Column ID as given to addColumn()
     * @param int    $direction Sort direction (SORT_ASC | SORT_DESC)
     *
     * @return DataGrid $this
     */
    public function sortColumn(string $id, int $direction = SORT_ASC): DataGrid_Table
    {
        if ($id === "none") {
            $this->sort_key = null;
            $this->sort_dir = SORT_ASC;
        } else {
            $this->sort_key = $id;
            $this->sort_dir = $direction;

            array_walk(
                $this->columns,
                function (&$v, $k) use ($id, $direction) {
                    $v->setSorted($k !== $id ? false : $direction);
                }
            );
        }

        return $this;
    }

    /**
     * Sets the data set to display
     *
     * @param  array $data Array of items
     * @return DataGrid $this
     */
    public function setData(array $data): DataGrid_Table
    {
        $this->data      = $data;
        $this->data_orig = $data;

        return $this;
    }

    /**
     * Adds a data item to the data set
     *
     * @param  array|DataGrid_Column_Seperator $data An array of items (arrays), OR a DataGrid_Column_Seperator
     * @return DataGrid $this  $this
     */
    public function addData($data): DataGrid_Table
    {
        if (!is_array($data) && !$data instanceof DataGrid_Column_Seperator) {
            $data = [$data];
        }

        $previous_row_id = count($this->data);

        $this->data[]      = $data;
        $this->data_orig[] = $data;

        return $this;
    }

    /**
     * Returns a data row whose associative array key matches $key and whose
     * value matches $value
     *
     * @param  string $key
     * @param  string $value
     * @return mixed
     */
    public function getRowWith($key, $value)
    {
        return current(
            array_filter(
                $this->data_orig,
                function (array $v) use ($key, $value) {
                    return isset($v[$key]) && $v[$key] == $value;
                }
            )
        );
    }

    /**
     * Sorts the data set according to the current sorting key and direction
     *
     * @return DataGrid $this
     */
    protected function _sortData(): DataGrid_Table
    {
        usort(
            $this->data,
            $this->_buildSorter(
                $this->sort_key,
                $this->sort_dir,
                $this->columns[$this->sort_key] instanceof DataGrid_Column_String ?  SORT_STRING : SORT_NUMERIC
            )
        );

        return $this;
    }

    /**
     * Build and return a sorting function for comparing data items
     *
     * @param  string $key
     * @param  int    $direction
     * @param  int    $type
     * @return callable
     */
    protected function _buildSorter(string $key, int $direction, int $type): callable
    {
        return function (array $a, array $b) use ($key, $direction, $type) {
            if ($a[$key] instanceof DataGrid_Column_Value) {
                $cmp_a = $a[$key]->getValue();
                $cmp_b = $b[$key]->getValue();
            } else {
                $cmp_a = &$a[$key];
                $cmp_b = &$b[$key];
            }

            switch ($direction) {
                case SORT_DESC:
                    return $type === SORT_NUMERIC ? $cmp_b <=> $cmp_a : strnatcmp($cmp_b, $cmp_a);

                case SORT_ASC:
                default:
                    return $type === SORT_NUMERIC ? $cmp_a <=> $cmp_b : strnatcmp($cmp_a, $cmp_b);
            }
        };
    }

    /**
     * Filters the current data set according to any display filters.
     *
     * @return DataGrid $this
     */
    protected function _filterData(): DataGrid_Table
    {
        $this->data = $this->data_orig;

        // Apply any display filters
        foreach ($this->display_filters as $filter) {
            $this->data = array_filter($this->data, $filter);
        }

        $this->data = array_values($this->data);

        return $this;
    }

    /**
     * Calculate the width of the visible columns by the length of their header
     * text.
     *
     * @return DataGrid $this
     */
    protected function _calculateColumnWidths()
    {
        $columns = $this->getVisibleColumns();

        $this->terminal_width = exec("tput cols");
        $this->column_widths  = array_map(['DataGrid_Tools', 'strLen'], $columns);

        return $this;
    }

    /**
     * Calculate the maximum widths of the actual data as well as adjust any columns that
     * might be too wide, to make them fit in the terminal viewport.
     *
     * @param  int $data_row_start Only process data starting with this row.
     * @return bool                FALSE if table would not fit in the viewport, TRUE if it would.
     */
    protected function _calculateDataWidths(int $data_row_start = 0): bool
    {
        $columns = $this->getVisibleColumns();

        // Reset data widths by zeroing them.
        $this->data_widths = array_combine(
            array_keys($columns),
            array_fill(0, count($columns), 0)
        );

        for ($i = $data_row_start; $i < count($this->data); ++$i) {
            foreach ($columns as $id => $col) {
                // Skip any columns not present in the current row
                if (!isset($this->data[$i][$id])) {
                    continue;
                }

                $this->data_widths[$id] = max(
                    DataGrid_Tools::strLen($this->data[$i][$id]),
                    $this->data_widths[$id]
                );
            }
        }

        $table_width = $this->_calculateTableWidth();
        $title_width = DataGrid_Tools::strLen($this->table_title) + 4;

        $this->terminal_width = exec("tput cols");

        // If table fits in the viewport, there's nothing more to do here.
        if ($table_width < $this->terminal_width && $title_width < $table_width) {
            return true;
        }

        // Should we truncate anything?
        if ($table_width > $this->terminal_width && (!$this->_hasStringColumns() || !$this->allow_truncate_string_columns)) {
            return true;
        }

        return false;
    }

    /**
     * Adjusts column widths to expand/contract them as needed to fit into the viewport of the terminal.
     *
     * @return DataGrid $this
     */
    protected function _adjustColumnWidths()
    {
        $visiblecolumns = $this->getVisibleColumns();
        $table_width    = $this->_calculateTableWidth();

        // Array of column widths of string columns
        $stringcolumn_widths = array_filter(
            array_map(
                function ($v) {
                    return $v instanceof DataGrid_Column_String ? DataGrid_Tools::strLen($v) + 4 : 0;
                },
                $visiblecolumns
            )
        );

        $stringcolumn_widths = [];
        foreach ($visiblecolumns as $id => $col) {
            if (!$col instanceof DataGrid_Column_String) {
                continue;
            }

            $stringcolumn_widths[$id] = $this->data_widths[$id];
        }

        // Sort them by length as want to shorten string columns by the longest first.
        arsort($stringcolumn_widths);

        $title_width = DataGrid_Tools::strLen($this->table_title) + 4;

        // Figure out how wide the table needs to be
        if ($table_width > $this->terminal_width) {
            $target_width = $this->terminal_width;
        } else {
            $target_width = max($table_width, min($this->terminal_width, $title_width));
        }

        // We need to adjust the table width by this much
        $adjust_by = $table_width - $target_width;

        // Process each column one at a time until it fits.
        foreach ($stringcolumn_widths as $id => $width) {
            $adjusted_by = $this->data_widths[$id] - $adjust_by < $this->column_widths[$id] ? $this->data_widths[$id] - $this->column_widths[$id] : $adjust_by;

            // Adjust column width, apparent table width, and the amount
            // we need to adjust by for the next iteration, if any.
            $this->data_widths[$id] -= $adjusted_by;
            $table_width            -= $adjusted_by;
            $adjust_by              -= $adjusted_by;

            // If the table would fit now, break out and call it a day.
            if ($table_width <= $target_width) {
                break;
            }
        }
    }

    /**
     * Calculate the total width of the table, before any truncating takes place
     *
     * @return int
     */
    protected function _calculateTableWidth(): int
    {
        $visiblecolumns = $this->getVisibleColumns();

        // Calculate the final size of the table as displayed
        $vert_line_len = DataGrid_Tools::strLen(LINE_VERTICAL);

        // 1x LINE_VERTICAL on each row
        $table_width   = $vert_line_len;

        foreach ($visiblecolumns as $id => $col) {
            // 1 space on either side + a LINE_VERTICAL on the right side.
            $table_width += max($this->column_widths[$id], $this->data_widths[$id]) + 2 + $vert_line_len;
        }

        return $table_width;
    }

    /**
     * Determines whether any columns are instances of DataGrid_Column_String
     *
     * @return bool
     */
    protected function _hasStringColumns(): bool
    {
        return !!array_filter(
            $this->columns,
            function ($v) {
                return $v instanceof DataGrid_Column_String;
            }
        );
    }

    /**
     * Returns the number of visible rows in the data grid
     *
     * @return int
     */
    public function count(): int
    {
        $this->_filterData();

        return count($this->data);
    }
}
