<?php
declare(strict_types=1);

class DataGrid_Filter
{
    /**
     * Column key/ID to filter on
     */
    protected $_columnId;

    /**
     * Comparison operator
     */
    protected $_op;

    /**
     * Criteria to filter on
     */
    protected $_criteria;

    public function __construct(string $columnId, string $op, $criteria) 
    {
        $this->_columnId = $columnId;
        $this->_op       = $op;
        $this->_criteria = $criteria;
    }

    /**
     * Return the ID of the column we're filtering
     *
     * @return  string
     */
    public function getColumnId() 
    {
        return $this->_columnId;
    }

    /**
     * Return the comparison operator of the filter
     *
     * @return  string
     */
    public function getOperator() 
    {
        return $this->_op;
    }

    /**
     * Return the criteria we're filtering on
     *
     * @return  mixed
     */
    public function getCriteria() 
    {
        return $this->_criteria;
    }

    /**
     * Perform a check/comparison 
     *
     * @return  boolean
     */
    public function __invoke($row): bool
    {
        if ($row[$this->_columnId] instanceof DataGrid_Column_Value) {
            $value = $row[$this->_columnId]->getValue();
        } else {
            $value = $row[$this->_columnId];
        }

        switch ($this->_op) {
            case "<":  return $value < $this->_criteria;
            case ">":  return $value > $this->_criteria;
            case "<=": return $value <= $this->_criteria;
            case ">=": return $value >= $this->_criteria;
            case "=":  return $value == $this->_criteria;
            case "!=": return $value != $this->_criteria;
            case "~":  return false !== stripos($value, $this->_criteria);
            case "!~": return false === stripos($value, $this->_criteria);
        }
    }
}
