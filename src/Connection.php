<?php

namespace Yabx\MySQL;

use mysqli;
use Exception;
use mysqli_result;
use DateTimeInterface;
use mysqli_sql_exception;
use Psr\Log\LoggerInterface;
use Yabx\MySQL\Exceptions\QueryException;
use Yabx\MySQL\Exceptions\DuplicateEntryException;

class Connection {

    protected ?LoggerInterface $logger;
    private mysqli $driver;
    private static array $pool = [];

    public static function fromPool(int $index = 0): Connection {
        if(!$connection = self::$pool[$index] ?? null)
            throw new Exception('There is no connection at index ' . $index);
        return $connection;
    }

    public function __construct(string $hostname, string $username, string $password, string $database, int $port = 3306,
                                string $charset = 'utf8mb4', ?LoggerInterface $logger = null) {
        $this->driver = new mysqli($hostname, $username, $password, $database, $port);
        $this->driver->set_opt(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
        if(!$this->driver->select_db($database))
            throw new Exception('Failed to set database: ' . $database);
        if(!$this->driver->set_charset($charset))
            throw new Exception('Failed to set charset: ' . $charset);
        self::$pool[] = $this;
        $this->logger = $logger;
    }

    public function query(string $query, array $params = []): array {
        return $this->exec($query, $params)->fetch_all(MYSQLI_ASSOC);
    }

    public function queryOne(string $query, array $params = []): array|null {
        if(!str_ends_with($query, 'LIMIT 1')) $query .= ' LIMIT 1';
        return $this->query($query, $params)[0] ?? null;
    }

    public function insert(string $table, array $data, bool $igrnore = false): int|string {
        $query = 'INSERT' . ($igrnore? ' IGNORE' : '') .  ' INTO ' . $this->escapeId($table) . ' (';
        $query .= $this->escapeId(array_keys($data)) . ') VALUES ' . $this->escape(array_values($data));
        $this->exec($query);
        return $this->driver->insert_id;
    }

    public function select(string $table, array $where = [], array $order = [], ?int $limit = null, array $fields = ['*']): array {
        $query = 'SELECT ' . $this->escapeId($fields) . ' FROM ' . $this->escapeId($table);
        if($where) $query .= ' ' . $this->where($where);
        if($order) {
            $query .= ' ORDER BY ';
            $orders = [];
            foreach($order as $key => $direction) {
                $orders[] = $this->escapeId($key) . ' ' . strtoupper($direction);
            }
            $query .= implode(', ', $orders);
        }
        if($limit) $query .= ' LIMIT ' . $limit;
        return $this->query($query);
    }

    public function selectOne(string $table, array $where = [], array $order = [], array $fields = ['*']): ?array {
        return $this->select($table, $where, $order, 1, $fields)[0] ?? null;
    }

    public function update(string $table, array $data, array $where): int {
        $query = 'UPDATE ' . $this->escapeId($table) . ' SET ';
        $pairs = [];
        foreach($data as $key => $value) {
            $pairs[] = $this->escapeId($key) . ' = ' . $this->escape($value);
        }
        $query .= implode(', ', $pairs);
        $query .= $this->where($where);
        $this->exec($query);
        return $this->driver->affected_rows;
    }

    public function delete(string $table, array $where): int {
        $query = 'DELETE FROM ' . $this->escapeId($table) . ' ' . $this->where($where);
        $this->exec($query);
        return $this->driver->affected_rows;
    }

    public function begin(): void {
        $this->exec('START TRANSACTION');
    }

    public function commit(): void {
        $this->exec('COMMIT');
    }

    public function rollback(): void {
        $this->exec('ROLLBACK');
    }

    private function exec(string $query, array $params = []): mysqli_result|bool {
        $this->logger?->debug($query, $params);
        $query = $this->prepare($query, $params);
        //echo "{$query}\n\n";
        try {
            return $this->driver->query($query);
        } catch(mysqli_sql_exception $e) {
            $this->logger?->error($e->getMessage());
            if($e->getCode() === 1062) throw new DuplicateEntryException($e->getMessage(), $query);
            throw new QueryException($e->getMessage(), $e->getCode(), $query);
        }
    }

    private function prepare(string $query, array $params = []): string {
        foreach($params as $key => $value) {
            $query = str_replace('{$' . $key . '}', $this->escape($value), $query);
            $query = str_replace('{&' . $key . '}', $this->escapeId($value), $query);
            $query = str_replace('{#' . $key . '}', $value, $query);
        }
        return $query;
    }

    private function escape(mixed $value): string {
        return match(true) {
            is_null($value) => 'NULL',
            is_numeric($value) => $value,
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            is_string($value) => '"' . $this->driver->real_escape_string($value) . '"',
            is_array($value) => '(' . implode(', ', array_map(fn(mixed $v) => $this->escape($v), $value)) . ')',
            $value instanceof DateTimeInterface => $this->escape($value->format('c')),
            default => throw new Exception('Failed to sqlize value with type ' . gettype($value))
        };
    }

    private function escapeId(mixed $id): string {
        if($id === '*') return $id;
        elseif(is_string($id)) {
            return str_replace(['.', '`*`'], ['`.`', '*'], '`' . $id . '`');
        }
        elseif(is_array($id)) {
            $keys = [];
            foreach($id as $arg1 => $arg2) {
                if(is_numeric($arg1)) {
                    $keys[] = $this->escapeId($arg2);
                } else {
                    $keys[] = $this->escapeId($arg1) . ' AS ' . $this->escapeId($arg2);
                }
            }
            return implode(', ', $keys);
        }
        else throw new Exception('Invalid id type: ' . gettype($id));
    }

    private function where(array $where): string {
        $query = 'WHERE ';
        $conditions = [];
        foreach($where as $key => $value) {
            $condition = $this->escapeId($key);
            if(is_null($value)) $condition .= ' IS NULL';
            elseif(is_array($value)) $condition .= ' IN ' . $this->escape($value);
            else $condition .= ' = ' . $this->escape($value);
            $conditions[] = $condition;
        }
        $query .= implode(' AND ', $conditions);
        return $query;
    }

    public function getDriver(): mysqli {
        return $this->driver;
    }

}
