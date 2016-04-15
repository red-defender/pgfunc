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
         */
        final public function queryProcedure(Procedure $procedure) {
            list ($sql, $parameters) = $procedure->generateQueryData();
            $statement = $this->getStatement($sql);
            $this->bindParams($statement, $parameters);
            $this->executeStatement($statement, $procedure);
            return $this->fetchResult($statement, $procedure);
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
                case is_bool($value):
                    return PDO::PARAM_BOOL;

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
         * @throws Database When executing is failed.
         * @throws Specified When database exception is known and specified.
         */
        private function executeStatement(PDOStatement $statement, Procedure $procedure) {
            try {
                $statement->execute();
            } catch (PDOException $exception) {
                switch ($exception->getCode()) {
                    // Raised exceptions in stored procedures or specified constraint violations.
                    case 'P0001': // RAISE_EXCEPTION.
                    case '23503': // FOREIGN_KEY_VIOLATION.
                    case '23505': // UNIQUE_VIOLATION.
                    case '23514': // CHECK_VIOLATION.
                        // Recognizing cause of error.
                        $errorType = $procedure->handleError($exception->getMessage());
                        if ($errorType !== null) {
                            if (class_exists($errorType)) {
                                // These exceptions should be inherited from Specified exception class.
                                throw new $errorType(null, 0, $exception);
                            }
                            throw new Specified($errorType, 0, $exception);
                        }
                        break;
                }

                throw new Database(
                    'Failed to execute statement: ' . $exception->getMessage(),
                    Exception::FAILED_QUERY,
                    $exception
                );
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
    }
}
