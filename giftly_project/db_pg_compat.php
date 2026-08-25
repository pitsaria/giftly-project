<?php
/**
 * MySQLi-compatible shim backed by PDO_PGSQL.
 *
 * The app's ~65 PHP files call $conn->query(), ->real_escape_string(),
 * ->insert_id, ->affected_rows, ->error, ->begin_transaction()/commit()/rollback(),
 * and on results ->fetch_assoc() / ->num_rows. This class + PgCompatResult
 * reproduce that exact surface on top of a PostgreSQL connection so none of
 * those call sites need to change. Only db_connect.php and
 * api/config/database.php construct this class directly.
 */

class PgCompatMysqli {
    public $connect_error = null;
    public $error = '';
    public $insert_id = 0;
    public $affected_rows = 0;

    private $pdo;

    public function __construct($host, $user, $pass, $dbname, $port = 5432, $sslmode = null) {
        try {
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            if ($sslmode) {
                $dsn .= ";sslmode=$sslmode";
            }
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_PERSISTENT => false,
            ]);
        } catch (PDOException $e) {
            $this->connect_error = $e->getMessage();
        }
    }

    public function query($sql) {
        $stmt = $this->pdo->query($sql);

        if ($stmt === false) {
            $err = $this->pdo->errorInfo();
            $this->error = $err[2] ?? 'Unknown database error';
            return false;
        }

        $this->error = '';

        if ($stmt->columnCount() > 0) {
            return new PgCompatResult($stmt);
        }

        $this->affected_rows = $stmt->rowCount();

        if (preg_match('/^\s*INSERT/i', $sql)) {
            try {
                $this->insert_id = (int) $this->pdo->lastInsertId();
            } catch (Throwable $e) {
                $this->insert_id = 0;
            }
        }

        return true;
    }

    public function real_escape_string($str) {
        $quoted = $this->pdo->quote((string) $str);
        if (stripos($quoted, "E'") === 0) {
            return substr($quoted, 2, -1);
        }
        return substr($quoted, 1, -1);
    }

    public function begin_transaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollBack();
    }
}

class PgCompatResult {
    public $num_rows;
    private $rows;
    private $pos = 0;

    public function __construct(PDOStatement $stmt) {
        $this->rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc() {
        if ($this->pos >= $this->num_rows) {
            return null;
        }
        return $this->rows[$this->pos++];
    }
}

if (!function_exists('mysqli_real_escape_string')) {
    function mysqli_real_escape_string($link, $string) {
        return $link->real_escape_string($string);
    }
}
