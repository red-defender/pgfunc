<?php
namespace PgFunc {
    use PDO;
    use PDOException;
    use PDOStatement;
    use PgFunc\Exception\Database;
    use PgFunc\Exception\Specified;
    use PgFunc\Exception\Usage;
    use PgFunc\OptionTrait\AttemptsCount;
    use PgFunc\OptionTrait\JsonResult;
    use PgFunc\OptionTrait\LocalParams;

    /**
     * Connection to database.
     *
     * @author red-defender
     * @package pgfunc
     */
    class Connection {
        use AttemptsCount, JsonResult, LocalParams;

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
         * @var bool Transactions are allowed in this connection.
         */
        private $isTransactionEnabled;

        /**
         * Initialize connection.
         *
         * @param Configuration $configuration
         */
        final public function __construct(Configuration $configuration) {
            $this->configuration = clone $configuration;
            $this->isTransactionEnabled = $configuration->isTransactionEnabled();

            $this->queryAttemptsCount = $configuration->getQueryAttemptsCount();
            $this->isJsonAsArray = $configuration->isJsonAsArray();
            $this->localParams = $configuration->getLocalParams();

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
         * @throws Exception
         */
        final public function queryProcedure(Procedure $procedure) {
            list ($sql, $parameters) = $procedure->generateQueryData();
            $localParams = array_replace($this->localParams, $procedure->getLocalParams());
            if ($localParams) {
                $this->applyLocalParams($sql, $parameters, $localParams);
            }
            $exception = null;
            $queryAttemptsCount = $procedure->getQueryAttemptsCount() ?: $this->queryAttemptsCount ?: 2;

            while (--$queryAttemptsCount >= 0) {
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
         * @throws Usage When transactions are not allowed.
         */
        final public function createTransaction() {
            if (! $this->isTransactionEnabled) {
                throw new Usage('Transactions are not allowed in this connection', Exception::TRANSACTION_ERROR);
            }
            return new Transaction($this->db, $this->connectionId);
        }

        /**
         * @param bool $isTransactionEnabled Transactions are allowed in this connection.
         * @return self
         */
        final public function setIsTransactionEnabled($isTransactionEnabled) {
            $this->isTransactionEnabled = (bool) $isTransactionEnabled;
            return $this;
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
                    $this->configuration->getUser(),
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
         * Modify SQL statement to use local PostgreSQL parameters.
         *
         * @param string &$sql SQL statement.
         * @param array &$parameters Parameters for binding.
         * @param array $localParams Local PostgreSQL parameters list.
         */
        private function applyLocalParams(& $sql, array & $parameters, array $localParams) {
            $sqlParts = [];
            foreach (array_keys($localParams) as $paramIndex => $name) {
                $sqlParts[] = 'set_config(:sn' . $paramIndex . '::TEXT,:sv' . $paramIndex . '::TEXT,TRUE)';
                $parameters[':sn' . $paramIndex] = $name;
                $parameters[':sv' . $paramIndex] = $localParams[$name];
            }
            $sql = 'WITH sl AS (SELECT ' . implode(',', $sqlParts) . ') ' . $sql . ' FROM sl';
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

            $isSingleRow = $procedure->getReturnType() === Procedure::RETURN_SINGLE;
            $resultIdentifierCallback = $procedure->getResultIdentifierCallback();
            $isJsonAsArray = $this->prepareIsJsonAsArray($procedure);
            $result = [];
            foreach ($statement as $data) {
                $data = json_decode($data[0], $isJsonAsArray);
                if ($isSingleRow) {
                    return $this->applyResultCallbacks($procedure, $data);
                }

                if ($resultIdentifierCallback) {
                    $result[$resultIdentifierCallback($data)] = $this->applyResultCallbacks($procedure, $data);
                } else {
                    $result[] = $this->applyResultCallbacks($procedure, $data);
                }
            }
            return $isSingleRow ? null : $result;
        }

        /**
         * @param Procedure $procedure
         * @return bool Decode JSON data as array.
         */
        private function prepareIsJsonAsArray(Procedure $procedure) {
            $isJsonAsArray = $procedure->isJsonAsArray();
            if (is_null($isJsonAsArray)) {
                $isJsonAsArray = $this->isJsonAsArray;
            }
            if (is_null($isJsonAsArray)) {
                $isJsonAsArray = true;
            }
            return $isJsonAsArray;
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
                    // Simple retrying if not in transaction.
                    if (Transaction::isActive($this->connectionId)) {
                        throw $databaseException;
                    }
                    break;

                // All other errors.
                default:
                    throw $databaseException;
            }
            return $databaseException;
        }

        /**
         * @param Procedure $procedure
         * @param mixed $data Row of result set.
         *
         * @return mixed Modified row of result set.
         */
        private function applyResultCallbacks(Procedure $procedure, $data) {
            foreach ($procedure->getResultCallbacks() as $resultCallback) {
                $data = $resultCallback($data);
            }
            return $data;
        }
    }
}
