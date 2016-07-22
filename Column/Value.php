<?php
declare(strict_types=1);

class DataGrid_Column_Value
{
    protected $_value;
    protected $_display;

    public function __construct($value, $display)
    {
        $this->_value   = $value;
        $this->_display = $display;
    }

    public function __toString(): string
    {
        return (string)$this->_display;
    }

    public function getValue()
    {
        return $this->_value;
    }
}

