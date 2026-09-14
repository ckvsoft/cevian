<?php

/*
 * The MIT License
 *
 * Copyright 2025 chris.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace ckvsoft;

class Database extends \PDO
{

    /** @var boolean $activeTransaction Whether a transaction is going on */
    public $activeTransaction;

    /** @var string $_sql Stores the last SQL command */
    private $_sql;

    /** @var constant $_fetchMode The select statement fetch mode */
    private $_fetchMode = \PDO::FETCH_ASSOC;

    /**
     * __construct - Initializes a PDO connection (Two ways of connecting)
     *
     * @param array $db An associative array containing the connection settings,
     * @param string $type Optional if using arugments to connect
     * @param string $host Optional if using arugments to connect
     * @param string $name Optional: database name (omit for no dbname, e.g. admin connections)
     * @param string $user Optional if using arugments to connect
     * @param string $pass Optional if using arugments to connect
     * @param boolean $persistent Optional: whether to use a persistent connection
     */
    public function __construct($db, $type = null, $host = null, $name = null, $user = null, $pass = null, $port = null, $persistent = false)
    {
        // Add ATTR_ERRMODE => ERRMODE_EXCEPTION explicitly, even if it's the default in PHP 8+,
        // to ensure consistent behavior across different environments.
        $options = [
            \PDO::ATTR_PERSISTENT => $persistent,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ];

        try {
            /** Connect with arguments */
            if ($db == false || $db == null) {
                $dsn = "{$type}:host={$host}";
                if ($port) {
                    $dsn .= ";port={$port}";
                }
                if ($name) {
                    $dsn .= ";dbname={$name}";
                }
                $dsn .= ";charset=utf8mb4";
                parent::__construct($dsn, $user, $pass, $options);
            }
            /** Connect with assoc array */ else {
                $persistent = isset($db['persistent']) ? $db['persistent'] : false;
                $options[\PDO::ATTR_PERSISTENT] = $persistent; // Override persistent if set in array
                $dsn = "{$db['type']}:host={$db['host']}";
                if (!empty($db['port'])) {
                    $dsn .= ";port={$db['port']}";
                }
                if (!empty($db['name'])) {
                    $dsn .= ";dbname={$db['name']}";
                }
                $dsn .= ";charset=utf8mb4";
                parent::__construct($dsn, $db['user'], $db['pass'], $options);
            }
        } catch (\PDOException $e) {
            // Note: In a library, it's often better to throw a custom exception here as well,
            // but keeping 'die()' as in your original code.
            die($e->getMessage());
        }
    }

    /**
     * setFetchMode - Change the default mode for fetching a query
     *
     * @param constant $fetchMode Use the PDO fetch constants, eg: \PDO::FETCH_CLASS
     */
    public function setFetchMode($fetchMode)
    {
        $this->_fetchMode = $fetchMode;
    }

    /**
     * selectOne - Run a Select Query and return only the first row.
     *
     * @param string $query Build a query with :colin marks for binding
     * @param array|mixed $bindParams The fields to select to replace the :colin marks (can be single value or array)
     *
     * @return array|null The first row as an associative array, or null if no result found.
     */
    public function selectOne(string $query, $bindParams = array()): ?array
    {
        // Ensure bindParams is an array, even if a single value is passed for a single placeholder
        if (!is_array($bindParams)) {
            $bindParams = [$bindParams];
        }

        $results = $this->select($query, $bindParams, \PDO::FETCH_ASSOC);

        // Return the first row or null if the array is empty
        return $results[0] ?? null;
    }

    /**
     * select - Run & Return a Select Query
     *
     * @param string $query Build a query with :colin marks for binding
     * @param array $bindParams The fields to select to replace the :colin marks
     * @param constant $overrideFetchMode Pass in a PDO::FETCH_MODE to override the default
     *
     * @return array
     */
    public function select($query, $bindParams = array(), $overrideFetchMode = null)
    {
        /** Store the SQL for use with fetching it when desired */
        $this->_sql = $query;

        /** Make sure bindParams is an array, I mess this up a lot when overriding fetch! */
        if (!is_array($bindParams))
            throw new \ckvsoft\CkvException("$bindParams must be an array");

        /** Run Query and Bind the Values */
        $sth = $this->_prepareAndBind($bindParams);

        try {
            // Execute the query. If an error occurs, an exception is thrown.
            $sth->execute();
        } catch (\PDOException $e) {
            // Catch the exception and call the custom error handler
            $this->_handleError(false, __FUNCTION__, $e);
            return []; // Return empty array on SELECT failure
        }

        /** Automatically return all the goods */
        if ($overrideFetchMode != null)
            return $sth->fetchAll($overrideFetchMode);
        else
            return $sth->fetchAll($this->_fetchMode);
    }

    /**
     * insert - Convenience method to insert data
     *
     * @param string $table     The table to insert into
     * @param array $data      An associative array of data: field => value
     * @return int|false The last insert ID on success, or false on error.
     */
    public function insert($table, $data)
    {
        /** Prepare SQL Code */
        $insertString = $this->_prepareInsertString($data);

        /** Store the SQL for use with fetching it when desired */
        $this->_sql = "INSERT INTO `{$table}` (`{$insertString['names']}`) VALUES({$insertString['values']})";

        /** Bind Values */
        $sth = $this->_prepareAndBind($data);

        try {
            /** Execute Query */
            $sth->execute();
        } catch (\PDOException $e) {
            // Catch the exception and call the custom error handler
            $this->_handleError(false, __FUNCTION__, $e);

            // Optional: Handle specific SQLSTATE codes, e.g., '23000' for Integrity Constraint Violation (Duplicate Entry)
            if ($e->getCode() == '23000' || (isset($e->errorInfo[0]) && $e->errorInfo[0] == '23000')) {
                // Specific logic for Duplicate Entry, if needed
            }

            return false; // Return false on failure
        }

        /** Return the insert id */
        return $this->lastInsertId();
    }

    /**
     * update - Convenience method to update the database
     *
     * @param string $table The table to update
     * @param array $data An associative array of fields to change: field => value
     * @param string $where A condition on where to apply this update
     * @param array $bindWhereParams If $where has parameters, apply them here
     *
     * @return boolean Successful or not
     */
    public function update($table, $data, $where, $bindWhereParams = array())
    {
        /** Build the Update String */
        $updateString = $this->_prepareUpdateString($data);
        /** Store the SQL for use with fetching it when desired */
        $this->_sql = "UPDATE `{$table}` SET $updateString WHERE $where";

        /** Bind Values */
        $sth = $this->_prepareAndBind($data);

        /** Bind Where Params */
        $sth = $this->_prepareAndBind($bindWhereParams, $sth);

        try {
            /** Execute Query */
            $result = $sth->execute();
        } catch (\PDOException $e) {
            // Catch the exception and call the custom error handler
            $this->_handleError(false, __FUNCTION__, $e);
            return false; // Return false on failure
        }

        /** Return Result */
        return $result;
    }

    /**
     * replace - Convenience method to replace into the database
     * Note: Replace does a Delete and Insert
     *
     * @param string $table The table to update
     * @param array $data An associative array of fields to change: field => value
     *
     * @return boolean Successful or not
     */
    public function replace($table, $data)
    {
        /** Build the Update String */
        $updateString = $this->_prepareUpdateString($data);

        /** Prepare SQL Code */
        $this->_sql = "REPLACE INTO `{$table}` SET $updateString";

        /** Bind Values */
        $sth = $this->_prepareAndBind($data);

        try {
            /** Execute Query */
            $result = $sth->execute();
        } catch (\PDOException $e) {
            // Catch the exception and call the custom error handler
            $this->_handleError(false, __FUNCTION__, $e);
            return false; // Return false on failure
        }

        /** Return Result */
        return $result;
    }

    /**
     * delete - Convenience method to delete rows
     *
     * @param string $table The table to delete from
     * @param string $where A condition on where to apply this call
     * @param array $bindWhereParams If $where has parameters, apply them here
     *
     * @return integer Total affected rows
     */
    public function delete($table, $where, $bindWhereParams = array())
    {
        /** Prepare SQL Code */
        $this->_sql = "DELETE FROM `{$table}` WHERE $where";

        /** Bind Values */
        $sth = $this->_prepareAndBind($bindWhereParams);

        try {
            /** Execute Query */
            $sth->execute();
        } catch (\PDOException $e) {
            // Catch the exception and call the custom error handler
            $this->_handleError(false, __FUNCTION__, $e);
            return 0; // Return 0 affected rows on failure
        }

        /** Return Result */
        return $sth->rowCount();
    }

    /**
     * insertUpdate - Convenience method to insert/if key exists update.
     *
     * @param string $table     The table to insert into
     * @param array $data      An associative array of data: field => value
     * @return integer The last insert id on success.
     */
    public function insertUpdate($table, $data)
    {
        /** Prepare SQL Code */
        $insertString = $this->_prepareInsertString($data);
        $updateString = $this->_prepareUpdateString($data);

        /** Store the SQL for use with fetching it when desired */
        $this->_sql = "INSERT INTO `{$table}` (`{$insertString['names']}`) VALUES({$insertString['values']}) ON DUPLICATE KEY UPDATE {$updateString}";

        /** Bind Values */
        $sth = $this->_prepareAndBind($data);

        try {
            /** Execute Query */
            $sth->execute();
        } catch (\PDOException $e) {
            // Catch the exception and call the custom error handler
            $this->_handleError(false, __FUNCTION__, $e);
            return 0; // Return 0 on failure
        }

        /** Return the insert id */
        return $this->lastInsertId();
    }

    /**
     * getQuery - Return the last sql Query called
     *
     * @return string
     */
    public function showQuery()
    {
        return $this->_sql;
    }

    /*
     * showTables - Return all the tables of current Database
     *
     * @return string
     */

    public function showTables()
    {
        // Note: The select method is already secured via try/catch
        $tablesResult = $this->select("SHOW TABLES");
        $tables = array();
        foreach ($tablesResult as $tableRow) {
            $tableValues = array_values($tableRow);
            $tables[] = $tableValues[0];
        }
        return $tables;
    }

    /**
     * id - Gets the last inserted ID
     *
     * @return integer
     */
    public function id()
    {
        return $this->lastInsertId();
    }

    /**
     * tableExists - Check whether a table exists in the CURRENT
     * database. Replaces the hand-rolled information_schema selects
     * in adapters/utils.
     */
    public function tableExists(string $tableName): bool
    {
        $r = $this->selectOne(
                "SELECT 1 FROM information_schema.tables
                  WHERE table_schema = DATABASE()
                    AND table_name = :t LIMIT 1",
                ['t' => $tableName]
        );
        return !empty($r);
    }

    /**
     * databaseName - Name of the database this connection uses
     * (SELECT DATABASE()).
     */
    public function databaseName(): string
    {
        $row = $this->selectOne("SELECT DATABASE() AS db", []);
        return (string) ($row['db'] ?? '');
    }

    /**
     * showCreateTable - Clean text of SHOW CREATE TABLE.
     *
     * @param string $tableName strict identifier (letters/digits/underscore)
     */
    public function showCreateTable(string $tableName): ?string
    {
        $tableName = $this->safeIdent($tableName);
        foreach ($this->select("SHOW CREATE TABLE `{$tableName}`", []) as $row) {
            foreach (['Create Table', 'CREATE TABLE', 'Create View', 'CREATE VIEW'] as $k) {
                if (!empty($row[$k])) {
                    return (string) $row[$k];
                }
            }
        }
        return null;
    }

    /**
     * dropTable - DROP TABLE IF EXISTS with a validated identifier.
     */
    public function dropTable(string $tableName): void
    {
        $tableName = $this->safeIdent($tableName);
        $this->execDdl("DROP TABLE IF EXISTS `{$tableName}`");
    }

    /**
     * truncateTable - TRUNCATE TABLE with a validated identifier.
     */
    public function truncateTable(string $tableName): void
    {
        $tableName = $this->safeIdent($tableName);
        $this->execDdl("TRUNCATE TABLE `{$tableName}`");
    }

    /**
     * Identifier guard for DDL/string-interpolated statements:
     * MySQL identifiers are limited to 64 chars; we restrict to
     * letters/digits/underscore (no quoting traps).
     */
    private function safeIdent(string $name): string
    {
        if ($name === '' || strlen($name) > 64
                || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \ckvsoft\CkvException("Unsafe identifier '{$name}'");
        }
        return $name;
    }

    /**
     * executeSqlFile - Replay a plain SQL dump file (e.g. a pmwh3 PHP
     * backup) in three phases: all DROPs, then all CREATEs, then all
     * INSERTs. This makes the restore honours ordering and duplicate
     * constraints reliably and keeps statement parsing in ONE place.
     *
     * Statements that are neither DROP/CREATE/INSERT (SET/LOCK/...)
     * are ignored.
     *
     * @return int number of executed statements
     */
    public function executeSqlFile(string $path): int
    {
        $sql = (string) file_get_contents($path);
        $phases = ['drop' => [], 'create' => [], 'insert' => []];

        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql))) as $stmtRaw) {
            $stmt = '';
            foreach (preg_split('/\R/', $stmtRaw) as $line) {
                if (trim($line) === '' || str_starts_with(ltrim($line), '--')) {
                    continue;
                }
                $stmt .= $line . "\n";
            }
            $stmt = trim($stmt);
            if ($stmt === '') {
                continue;
            }
            $up = strtoupper($stmt);
            if (str_starts_with($up, 'DROP')) {
                $phases['drop'][] = $stmt;
            } elseif (str_starts_with($up, 'CREATE')) {
                $phases['create'][] = $stmt;
            } elseif (str_starts_with($up, 'INSERT')) {
                $phases['insert'][] = $stmt;
            }
        }

        $executed = 0;
        foreach ($phases as $list) {
            foreach ($list as $stmt) {
                $this->exec($stmt); // DDL/INTO replay: framework-internal, exceptions surface
                $executed++;
            }
        }
        return $executed;
    }


    /**
     * execDdl / execAdmin - Run a DDL/admin statement
     * (CREATE/DROP/GRANT/FLUSH/ALTER USER/...).
     *
     * Centralised DDL entry point: module code that has to run DDL
     * (e.g. DB-administration adapters which CREATE DATABASE / GRANT)
     * goes through this instead of the raw inherited ->exec().
     *
     * Identifiers can NOT be bound as SQL placeholders in DDL, so
     * identifiers must be whitelisted/validated by the caller before
     * reaching this. Values (like passwords) CAN be bound via the
     * optional :named params.
     *
     * @return int|false rows affected (PDO semantics) or false on failure
     */
    public function execDdl(string $sql, array $params = []): int|false
    {
        $this->_sql = $sql;
        if (empty($params)) {
            return $this->exec($sql);
        }
        $sth = $this->_prepareAndBind($params);
        try {
            $sth->execute();
            return $sth->rowCount();
        } catch (\PDOException $e) {
            $this->_handleError(null, 'execDdl', $e);
            return false;
        }
    }

    /**
     * beginTransaction - Overloading default method
     */
    #[\Override]
    public function beginTransaction(): bool
    {
        $ret = parent::beginTransaction();
        $this->activeTransaction = parent::inTransaction();
        return $ret;
    }

    /**
     * commit - Overloading default method
     */
    #[\Override]
    public function commit(): bool
    {
        if (parent::inTransaction()) {
            try {
                $ret = parent::commit();
                $this->activeTransaction = $ret;
                return $ret;
            } catch (\PDOException $e) {
                // Catch commit errors (e.g., if connection was lost)
                $this->_handleError(false, __FUNCTION__, $e);
                return false;
            }
        } else {
            // In PHP 8.0 a PDOException is thrown when a commit is attempted with no
            // transaction active. In previous PHP versions this failed silently.
            return true;
        }
    }

    /**
     * rollback - Overloading default method
     */
    #[\Override]
    public function rollback(): bool
    {
        // MySQL will automatically commit transactions when tables are altered or
        // created (DDL transactions are not supported). Prevent triggering an
        // exception to ensure that the error that has caused the rollback is
        // properly reported.
        if (!$this->activeTransaction) {
            // On PHP 7 $this->connection->inTransaction() will return TRUE and
            // $this->connection->rollback() does not throw an exception; the
            // following code is unreachable.
            // If \DatabaseConnection::rollback() would throw an
            // exception then continue to throw an exception.
            if (!parent::inTransaction()) { // Use parent::inTransaction() to be safe
                // Assuming DatabaseTransactionNoActiveException is defined elsewhere
                // throw new DatabaseTransactionNoActiveException();
            }
            trigger_error('Rollback attempted when there is no active transaction. This can cause data integrity issues.', E_USER_WARNING);
            return true;
        }

        try {
            $ret = parent::rollback();
            $this->activeTransaction = false;
            return $ret;
        } catch (\PDOException $e) {
            // Catch rollback errors (e.g., if connection was lost)
            $this->_handleError(false, __FUNCTION__, $e);
            $this->activeTransaction = false;
            return false;
        }
    }

    /**
     * showColumns - Display the columns for a table (MySQL)
     *
     * @param string $table Name of a MySQL table
     */
    public function showColumns($table)
    {
        // The method must parse the string
        $parts = explode('.', $table);

        if (count($parts) == 2) {
            $dbName = $parts[0];
            $tableName = $parts[1];
            $sql = "SHOW COLUMNS FROM `$dbName`.`$tableName`";
        } else {
            $tableName = $parts[0];
            $sql = "SHOW COLUMNS FROM `$tableName`";
        }

        // Note: The select method is already secured via try/catch
        $result = $this->select($sql, array(), \PDO::FETCH_ASSOC);

        $output = array();
        foreach ($result as $key => $value) {
            if ($value['Key'] == 'PRI') {
                $output['primary'] = $value['Field'];
            }
            $output['column'][$value['Field']] = $value['Type'];
        }

        return $output;
    }

    /**
     * _prepareAndBind - Binds values to the Statement Handler
     *
     * @param array $data
     * @param object $reuseStatement If you need to reuse the statement to apply another bind
     *
     * @return object
     */
    private function _prepareAndBind($data, $reuseStatement = false)
    {
        $sth = $reuseStatement ? $reuseStatement : $this->prepare($this->_sql);

        foreach ($data as $key => $value) {
            if ($value instanceof DbExpr) {
                // Skip binding, raw SQL used
                continue;
            }
            if (is_int($value)) {
                $sth->bindValue(":$key", $value, \PDO::PARAM_INT);
            } else {
                $sth->bindValue(":$key", $value, \PDO::PARAM_STR);
            }
        }

        return $sth;
    }

    /**
     * _prepareInsertString - Handles an array and turns it into SQL code
     *
     * @param array $data The data to turn into an SQL friendly string
     * @return array
     */
    private function _prepareInsertString($data)
    {
        $names = [];
        $values = [];
        foreach ($data as $key => $value) {
            $names[] = $key;
            if ($value instanceof DbExpr) {
                $values[] = (string) $value; // raw SQL
            } else {
                $values[] = ':' . $key;
            }
        }

        return [
            'names' => implode('`, `', $names),
            'values' => implode(', ', $values)
        ];
    }

    /**
     * _prepareUpdateString - Handles an array and turn it into SQL code
     *
     * @param array $data
     * @return string
     */
    private function _prepareUpdateString($data)
    {
        $parts = [];
        foreach ($data as $key => $value) {
            if ($value instanceof DbExpr) {
                $parts[] = "`$key`=" . (string) $value;
            } else {
                $parts[] = "`$key`=:$key";
            }
        }
        return implode(', ', $parts);
    }

    /**
     * _handleError - Handles errors with PDO and throws a custom exception.
     *
     * @param boolean $result The result of the execute() call (false on error)
     * @param string $method The calling function name
     * @param \PDOException $e Optional: The PDOException object, if thrown
     * @throws \ckvsoft\CkvException
     */
    private function _handleError($result, $method, ?\PDOException $e = null)
    {
        // *** PRIMARY LOGIC FOR EXCEPTION MODE ***
        if ($e !== null) {
            // Use the errorInfo array from the exception
            $errorInfo = $e->errorInfo ?? ['N/A', 'N/A', $e->getMessage()];

            throw new \ckvsoft\CkvException(
                            "DB Error in $method: " . implode(', ', $errorInfo) .
                            " - SQL: " . $this->showQuery() .
                            " - Message: " . $e->getMessage(),
                            (int) $e->getCode(), // Use PDO error code
                            $e // Pass the original exception for the stack trace
                    );
        }

        // *** FALLBACK LOGIC FOR SILENT/WARNING MODE (Should not be reached) ***

        /** If it's an SQL error */
        if ($this->errorCode() != '00000') {
            throw new \ckvsoft\CkvException("Error: " . implode(',', $this->errorInfo()));
        }

        if ($result == false) {
            $error = $method . " did not execute properly, " . $this->showQuery();
            throw new \ckvsoft\CkvException($error);
        }
    }
}
