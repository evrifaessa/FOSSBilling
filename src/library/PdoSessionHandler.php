<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PdoSessionHandler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Michael Williams <michael.williams@funsational.com>
 */
class PdoSessionHandler implements SessionHandlerInterface
{
    /**
     * @var PDO PDO instance
     */
    private readonly PDO $pdo;

    /**
     * @var array database options
     */
    private array $dbOptions;

    /**
     * Constructor.
     *
     * List of available options:
     *  * db_table: The name of the table [required]
     *  * db_id_col: The column where to store the session id [default: sess_id]
     *  * db_data_col: The column where to store the session data [default: sess_data]
     *  * db_time_col: The column where to store the timestamp [default: sess_time]
     *
     * @param PDO   $pdo       A \PDO instance
     * @param array $dbOptions An associative array of DB options
     *
     * @throws InvalidArgumentException When "db_table" option is not provided
     */
    public function __construct(PDO $pdo, array $dbOptions = [])
    {
        $dbOptions = [
            'db_table' => 'session',
        ];

        if (!array_key_exists('db_table', $dbOptions)) {
            throw new InvalidArgumentException('You must provide the "db_table" option for a PdoSessionStorage.');
        }
        if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new InvalidArgumentException(sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION))', self::class));
        }
        $this->pdo = $pdo;

        $this->dbOptions = ['db_id_col' => 'id', 'db_data_col' => 'content', 'db_time_col' => 'modified_at', ...$dbOptions];
    }

    public function open($path, $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function destroy($id): bool
    {
        // get table/column
        $dbTable = $this->dbOptions['db_table'];
        $dbIdCol = $this->dbOptions['db_id_col'];

        // delete the record associated with this id
        $sql = "DELETE FROM $dbTable WHERE $dbIdCol = :id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_STR);
            $stmt->execute();
        } catch (PDOException $e) {
            throw new RuntimeException(sprintf('PDOException was thrown when trying to manipulate session data: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

    public function gc($lifetime): int|false
    {
        // get table/column
        $dbTable = $this->dbOptions['db_table'];
        $dbTimeCol = $this->dbOptions['db_time_col'];

        // delete the session records that have expired
        $sql = "DELETE FROM $dbTable WHERE $dbTimeCol < :time";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':time', time() - $lifetime, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new RuntimeException(sprintf('PDOException was thrown when trying to manipulate session data: %s', $e->getMessage()), 0, $e);
        }

        return false;
    }

    public function read($id): string|false
    {
        // get table/columns
        $dbTable = $this->dbOptions['db_table'];
        $dbDataCol = $this->dbOptions['db_data_col'];
        $dbIdCol = $this->dbOptions['db_id_col'];

        try {
            $sql = "SELECT $dbDataCol FROM $dbTable WHERE $dbIdCol = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_STR);

            $stmt->execute();
            // it is recommended to use fetchAll so that PDO can close the DB cursor
            // we anyway expect either no rows, or one row with one column. fetchColumn, seems to be buggy #4777
            $sessionRows = $stmt->fetchAll(PDO::FETCH_NUM);

            if (count($sessionRows) == 1) {
                return base64_decode($sessionRows[0][0]);
            }

            // session does not exist, create it
            $this->createNewSession($id);

            return '';
        } catch (PDOException $e) {
            throw new RuntimeException(sprintf('PDOException was thrown when trying to read the session data: %s', $e->getMessage()), 0, $e);
        }
    }

    public function write($id, $data): bool
    {
        // get table/column
        $dbTable = $this->dbOptions['db_table'];
        $dbDataCol = $this->dbOptions['db_data_col'];
        $dbIdCol = $this->dbOptions['db_id_col'];
        $dbTimeCol = $this->dbOptions['db_time_col'];

        // session data can contain non binary safe characters so we need to encode it
        $encoded = base64_encode($data);

        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'mysql') {
                // MySQL would report $stmt->rowCount() = 0 on UPDATE when the data is left unchanged
                // it could result in calling createNewSession() whereas the session already exists in
                // the DB which would fail as the id is unique
                $stmt = $this->pdo->prepare(
                    "INSERT INTO $dbTable ($dbIdCol, $dbDataCol, $dbTimeCol) VALUES (:id, :data, :time) " .
                    "ON DUPLICATE KEY UPDATE $dbDataCol = VALUES($dbDataCol), $dbTimeCol = VALUES($dbTimeCol)"
                );
                $stmt->bindParam(':id', $id, PDO::PARAM_STR);
                $stmt->bindParam(':data', $encoded, PDO::PARAM_STR);
                $stmt->bindValue(':time', time(), PDO::PARAM_INT);
                $stmt->execute();
            } elseif ($driver === 'oci') {
                $stmt = $this->pdo->prepare("MERGE INTO $dbTable USING DUAL ON($dbIdCol = :id) " .
                    "WHEN NOT MATCHED THEN INSERT ($dbIdCol, $dbDataCol, $dbTimeCol) VALUES (:id, :data, sysdate) " .
                    "WHEN MATCHED THEN UPDATE SET $dbDataCol = :data WHERE $dbIdCol = :id");

                $stmt->bindParam(':id', $id, PDO::PARAM_STR);
                $stmt->bindParam(':data', $encoded, PDO::PARAM_STR);
                $stmt->execute();
            } else {
                $stmt = $this->pdo->prepare("UPDATE $dbTable SET $dbDataCol = :data, $dbTimeCol = :time WHERE $dbIdCol = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_STR);
                $stmt->bindParam(':data', $encoded, PDO::PARAM_STR);
                $stmt->bindValue(':time', time(), PDO::PARAM_INT);
                $stmt->execute();

                if (!$stmt->rowCount()) {
                    // No session exists in the database to update. This happens when we have called
                    // session_regenerate_id()
                    $this->createNewSession($id, $data);
                }
            }
        } catch (PDOException $e) {
            throw new RuntimeException(sprintf('PDOException was thrown when trying to write the session data: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

    /**
     * Creates a new session with the given $id and $data.
     *
     * @param string $id
     * @param string $data
     *
     * @return bool true
     */
    private function createNewSession($id, $data = '')
    {
        // get table/column
        $dbTable = $this->dbOptions['db_table'];
        $dbDataCol = $this->dbOptions['db_data_col'];
        $dbIdCol = $this->dbOptions['db_id_col'];
        $dbTimeCol = $this->dbOptions['db_time_col'];

        $sql = "INSERT INTO $dbTable ($dbIdCol, $dbDataCol, $dbTimeCol) VALUES (:id, :data, :time)";

        // session data can contain non binary safe characters so we need to encode it
        $encoded = base64_encode($data);
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        $stmt->bindParam(':data', $encoded, PDO::PARAM_STR);
        $stmt->bindValue(':time', time(), PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }

    /**
     * Return a PDO instance.
     *
     * @return PDO
     */
    protected function getConnection()
    {
        return $this->pdo;
    }
}
