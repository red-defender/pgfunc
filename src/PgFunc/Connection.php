<?php
namespace PgFunc {
    use PDO;
    use PDOException;
    use PDOStatement;
    use PgFunc\Exception\Database;
    use PgFunc\Exception\Specified;

    /**
     * Connection to database.
     *
     * @author red-defender
     * @package pgfunc
     */
    class Connection {
        /**
         * Number of attempts to execute query.
         */
        const QUERY_ATTEMPTS_LIMIT = 3;

        /**
         * @var PDO Current connection.
         */
        private $db;

        /**
         * @var Configuration
         */
        private $configuration;

        /**
         * @var string Unique connection ID (for transaction management).
         */
        private $connectionId;

        /**
         * Initialize connection.
         *
         * @param Configuration $configuration
         */
        final public function __construct(Configuration $configuration) {
            $this->configuration = $configuration;
            $this->connect();
        }

        /**
         * Cloning connection (creates new real connection with the same settings).
         */
        final public function __clone() {
            $this->configuration = clone $this->configuration;
            $this->connect();
        }

        /**
         * Rollback all pending transactions.
         */
        final public function __destruct() {
            Transaction::deactivateConnection($this->connectionId);
        }

        /**
         * Running stored procedure.
         *
         * @param Procedure $procedure
         * @return mixed Result of procedure call.
         * @throws
         */
        final public function queryProcedure(Procedure $procedure) {
            list ($sql, $parameters) = $procedure->generateQueryData();
            $exception = null;
            for ($tryCount = 0; $tryCount < self::QUERY_ATTEMPTS_LIMIT; $tryCount++) {
                $statement = $this->getStatement($sql);
                $this->bindParams($statement, $parameters);
                $exception = $this->executeStatement($statement, $procedure);
                if (! $exception) {
                    return $this->fetchResult($statement, $procedure);
                }
            }
            throw $exception;
        }

        /**
         * Create new transaction (or savepoint) in current connection.
         *
         * @return Transaction
         */
        final public function createTransaction() {
            return new Transaction($this->db, $this->connectionId);
        }

        /**
         * Establish a connection.
         *
         * @throws Database When connecting is failed.
         */
        private function connect() {
            static $connectionId = 0;
            $this->connectionId = md5(microtime()) . '_' . $connectionId++;

            try {
                $attributes = $this->configuration->getAttributes();
                $this->db = new PDO(
                    $this->configuration->getDsn(),
                    $this->configuration->getUserName(),
                    $this->configuration->getPassword(),
                    $attributes
                );

                // Discard current state of broken persistent connection.
                if (! empty($attributes[PDO::ATTR_PERSISTENT]) && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (PDOException $exception) {
                throw new Database(
                    'Failed to connect to database: ' . $exception->getMessage(),
                    Exception::FAILED_CONNECT,
                    $exception
                );
            }
        }

        /**
         * Prepare statement object.
         *
         * @param string $sql SQL statement.
         * @return PDOStatement Prepared statement.
         * @throws Database When preparing is failed.
         */
        private function getStatement($sql) {
            try {
                return $this->db->prepare($sql);
            } catch (PDOException $exception) {
                throw new Database('Failed to prepare statement: ' . $sql, Exception::FAILED_PREPARE, $exception);
            }
        }

        /**
         * Bind parameters of prepared statement.
         *
         * @param PDOStatement $statement Prepared statement.
         * @param array $params Parameters for binding.
         * @throws Database When binding is failed.
         */
        private function bindParams(PDOStatement $statement, array $params) {
            $name = null;
            try {
                foreach ($params as $name => $value) {
                    $statement->bindValue($name, $value, $this->getFlags($value));
                }
            } catch (PDOException $exception) {
                throw new Database('Failed to bind parameter: ' . $name, Exception::FAILED_BIND, $exception);
            }
        }

        /**
         * Get mask of flags for bindValue() call.
         *
         * @see PDOStatement::bindValue()
         *
         * @param mixed $value Parameter value.
         * @return int
         */
        private function getFlags($value) {
            switch (true) {
                case is_null($value):
                    return PDO::PARAM_NULL;

                case is_bool($value):
                    return PDO::PARAM_BOOL;

                case is_int($value);
                case is_float($value):
                    return PDO::PARAM_INT;

                case is_resource($value):
                    return PDO::PARAM_LOB;

                default:
                    return PDO::PARAM_STR;
            }
        }

        /**
         * Execute prepared statement of stored procedure call.
         *
         * @param PDOStatement $statement
         * @param Procedure $procedure
         * @return Database|null Database exception in case of error.
         * @throws Database When executing is failed.
         * @throws Specified When database exception is known and specified.
         */
        private function executeStatement(PDOStatement $statement, Procedure $procedure) {
            try {
                $statement->execute();
                return null;
            } catch (PDOException $exception) {
                return $this->handleException($exception, $procedure);
            }
        }

        /**
         * Get result of stored procedure call.
         *
         * @param PDOStatement $statement
         * @param Procedure $procedure
         * @return mixed Result of procedure call.
         */
        private function fetchResult(PDOStatement $statement, Procedure $procedure) {
            if ($procedure->getReturnType() === Procedure::RETURN_VOID) {
                return null;
            }

            $resultIdentifierCallback = $procedure->getResultIdentifierCallback();
            $result = [];
            foreach ($statement as $data) {
                $data = json_decode($data[Procedure::RESULT_FIELD], true);
                if ($procedure->getIsSingleRow()) {
                    return $data;
                }

                if (! is_null($resultIdentifierCallback)) {
                    $result[$resultIdentifierCallback($data)] = $data;
                } else {
                    $result[] = $data;
                }
            }
            return $procedure->getIsSingleRow() ? null : $result;
        }

        /**
         * Handle PDO exception while executing statement.
         *
         * @param PDOException $exception
         * @param Procedure $procedure
         * @return Database Last database error.
         * @throws Database When executing is failed.
         * @throws Specified When database exception is known and specified.
         */
        private function handleException(PDOException $exception, Procedure $procedure) {
            $databaseException = new Database(
                'Failed to execute statement: ' . $exception->getMessage(),
                Exception::FAILED_QUERY,
                $exception
            );
            switch ($exception->getCode()) {
                // Raised exceptions in stored procedures or specified constraint violations.
                case 'P0001': // RAISE_EXCEPTION.
                case '23503': // FOREIGN_KEY_VIOLATION.
                case '23505': // UNIQUE_VIOLATION.
                case '23514': // CHECK_VIOLATION.
                case '23P01': // EXCLUSION_VIOLATION.
                    // Recognizing cause of error.
                    $errorType = $procedure->handleError($exception->getMessage());
                    if ($errorType !== null) {
                        if (class_exists($errorType)) {
                            // These exceptions should be inherited from Specified exception class.
                            throw new $errorType(null, 0, $exception);
                        }
                        throw new Specified($errorType, 0, $exception);
                    }
                    throw $databaseException;

                // Connection errors.
                case '08000': // CONNECTION_EXCEPTION.
                case '08001': // SQLCLIENT_UNABLE_TO_ESTABLISH_SQLCONNECTION.
                case '08003': // CONNECTION_DOES_NOT_EXIST.
                case '08004': // SQLSERVER_REJECTED_ESTABLISHMENT_OF_SQLCONNECTION.
                case '08006': // CONNECTION_FAILURE.
                case '08007': // TRANSACTION_RESOLUTION_UNKNOWN.
                case '08P01': // PROTOCOL_VIOLATION.
                case '57P01': // ADMIN_SHUTDOWN.
                case '57P02': // CRASH_SHUTDOWN.
                case '57P03': // CANNOT_CONNECT_NOW.
                case 'HY000': // PHP_UNKNOWN_ERROR.
                    if (Transaction::deactivateConnection($this->connectionId)) {
                        // Don't reconnect silently if there was a pending transaction.
                        throw $databaseException;
                    }
                    try {
                        $this->connect();
                    } catch (Database $databaseException) {
                        // Return last exception if connecting failed.
                    }
                    break;

                // Serialization errors.
                case '40001': // SERIALIZATION_FAILURE.
                case '40P01': // DEADLOCK_DETECTED.
                    // Simple retrying.
                    break;

                // All other errors.
                default:
                    throw $databaseException;
            }
            return $databaseException;
        }
    }
}
