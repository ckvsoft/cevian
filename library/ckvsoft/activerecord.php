<?php

namespace ckvsoft;

class ActiveRecord extends \PDO
{

    private $_table = null;
    private $_queryPart = array();
    private $_queryString = array();
    private $_queryBinding = array();
    private $_queryOrder = array(
        'select', 'innerJoin', 'outerJoin', 'where', 'groupBy', 'orderBy', 'limit'
    );

    public function __construct($table)
    {
        $this->_table = $table;
    }

    public function select($set = array())
    {
        $str = 'SELECT ';

        /** Loop Select Items & */
        foreach ($set as $key => $value) {
            if (is_int($key)) {
                $str .= "`$value`, ";
            } else {
                $str .= "$key AS `$value`, ";
            }
        }

        $str = rtrim($str, ', ');

        $str .= " FROM `{$this->_table}` ";

        $this->_queryPart['select'] = $str;
        return $this;
    }

    public function where($set = array())
    {
        $operators = array(
            '<', '<=', '>', '>=', '!=', '<>'
        );
        $constructs = array(
            '&' => 'AND ', '|' => 'OR '
        );
        $str = 'WHERE ';

        foreach ($set as $key => $value) {

            if (array_key_exists($key[0], $constructs)) {
                $str .= $constructs[$key[0]];
                $key = substr($key, 1);
            }

            /**
             * See if we have a special character, otherwise apply equals
             */
            if (in_array(strrev($key)[0], $operators)) {
                $key = substr(strrev($key), 2);
                $key = strrev($key);
                $str .= "$key :$key ";
            } else {
                $str .= "$key = :$key";
            }

            $this->_queryBinding[$key] = $value;
        }

        $str = rtrim($str, ', ');

        $str .= " ";

        $this->_queryPart['where'] = $str;
        return $this;
    }

    public function outerJoin($table, $condition = array())
    {
        $this->_queryPart['outerJoin'][] = sprintf("left outer join $table ON ($conditions)");
        return $this;
    }

    public function innerJoin($table, $condition = array())
    {
        $this->_queryPart['innerJoin'][] = sprintf("inner join $table ON ($conditions)");
        return $this;
    }

    public function orderBy($set = array())
    {
        $str = "ORDER BY ";
        if (!empty($set)) {
            $str .= implode(', ', $set);
        }

        $this->_queryPart['orderBy'] = $str;
        return $this;
    }

    public function limit($set = array())
    {
        $str = "LIMIT ";

        if (count($set) == 1)
            $str .= $set[0];

        elseif (count($set == 2))
            $str .= $set[0] . ', ' . $set[1];

        $str .= " ";

        $this->_queryPart['limit'] = "LIMIT " . implode(',', $set);
        return $this;
    }

    public function groupBy($set = array())
    {
        $str = "GROUP BY ";
        if (!empty($set)) {
            $str .= implode(', ', $set);
        }

        $this->_queryPart['groupBy'] = $str;
        return $this;
    }

    public function run()
    {
        $this->_queryBuilder();
    }

    private function _queryBuilder()
    {
        $this->_queryString = "";
        foreach ($this->_queryOrder as $o) {
            if (isset($this->_queryPart[$o])) {
                $this->_queryString .= $this->_queryPart[$o];
            }
        }

        echo $this->_queryString;
    }

    private function _mapFunction($param)
    {
        
    }
}
