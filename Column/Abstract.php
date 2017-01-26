<?php
declare(strict_types=1);

abstract class DataGrid_Column
{
    protected $_name;
    protected $_flags;
    protected $_sorted = false;

    public function __construct(string $name, $sorted, int $flags)
    {
        $this->_name   = $name;
        $this->_sorted = $sorted;
        $this->_flags  = $flags;
    }

    public function __toString(): string
    {
        $sort_indicator = "";
        if ($this->_sorted !== false) {
            $sort_indicator = ($this->_sorted == SORT_ASC ? LINE_ARROW_DOWN : LINE_ARROW_UP);

            if ($this->isNumeric()) {
                return "\e[4m" . $sort_indicator. " " . $this->_name . "\e[0m";
            }

            return "\e[4m" . $this->_name." ".$sort_indicator . "\e[0m";
        }

        return $this->_name;
    }

    public function getName(): string
    {
        return $this->_name;
    }

    public function getValueAligment()
    {
        if ($this->hasFlag(DataGrid_Table::COLUMN_FLAG_ALIGN_LEFT)) {
            return STR_PAD_RIGHT;
        }

        if ($this->hasFlag(DataGrid_Table::COLUMN_FLAG_ALIGN_RIGHT)) {
            return STR_PAD_LEFT;
        }

        if ($this->hasFlag(DataGrid_Table::COLUMN_FLAG_ALIGN_EVEN)) {
            return STR_PAD_BOTH;
        }

        return $this->isNumeric() ? STR_PAD_LEFT : STR_PAD_RIGHT;
    }

    public function isNumeric(): bool
    {
        return ($this instanceof DataGrid_Column_Integer || $this instanceof DataGrid_Column_Float);
    }

    public function isVisible(): bool
    {
        return ($this->_flags & DataGrid_Table::COLUMN_FLAG_HIDDEN) === 0;
    }

    public function isSortable(): bool
    {
        return ($this->_flags & DataGrid_Table::COLUMN_FLAG_SORTABLE) !== 0;
    }

    public function setSorted($val)
    {
        $this->_sorted = $val;
    }

    public function hasFlag(int $flag): bool
    {
        return ($this->_flags & $flag) !== 0;
    }

    protected function _toggleFlag(int $flag, bool $toggle)
    {
        if ($toggle) {
            $this->_flags = $this->_flags | $flag;
        }
        else
        {
            $this->_flags = $this->_flags & (~ $flag);
        }
    }
}
