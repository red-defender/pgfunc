<?php
namespace PgFunc {
    use PDO;
    use PgFunc\Exception\Usage;

    /**
     * Connection configuration.
     *
     * @author red-defender
     * @package pgfunc
     */
    class Configuration {
        /**
         * PDO driver name.
         */
        const DRIVER = 'pgsql';

        /**
         * Configuration parameters.
         */
        const DB_NAME = 'DB_NAME';
        const HOST = 'HOST';
        const PORT = 'PORT';
        const USER_NAME = 'USER_NAME';
        const PASSWORD = 'PASSWORD';
        const CUSTOM_DSN = 'CUSTOM_DSN';
        const ATTRIBUTES = 'ATTRIBUTES';
        const APPLICATION_NAME = 'APPLICATION_NAME';

        /**
         * @var string Database name.
         */
        private $dbName;

        /**
         * @var string|null Database hostname (null means local Unix-socket connection).
         */
        private $host;

        /**
         * @var int|null Database port (null means default port).
         */
        private $port;

        /**
         * @var string Database user.
         */
        private $userName;

        /**
         * @var string User's password.
         */
        private $password;

        /**
         * @var string|null Custom DSN string.
         */
        private $customDsn;

        /**
         * @var array Additional PDO connection attributes.
         */
        private $attributes = [];

        /**
         * @var string|null Application name (startup parameter).
         */
        private $applicationName;

        /**
         * Initializing configuration.
         *
         * @param array $configuration Array of configuration parameters.
         * @throws Usage When required parameter is missing.
         */
        final public function __construct(array $configuration) {
            if (isset($configuration[self::CUSTOM_DSN])) {
                $this->customDsn = (string) $configuration[self::CUSTOM_DSN];
            } else {
                if (! isset($configuration[self::DB_NAME])) {
                    throw new Usage('Database name is missing', Exception::CONFIGURATION_ERROR);
                }
                $this->dbName = (string) $configuration[self::DB_NAME];
                if (isset($configuration[self::HOST])) {
                    $this->host = (string) $configuration[self::HOST];
                }
                if (isset($configuration[self::PORT])) {
                    $this->port = (int) $configuration[self::PORT];
                }
            }
            if (! isset($configuration[self::USER_NAME])) {
                throw new Usage('User name is missing', Exception::CONFIGURATION_ERROR);
            }
            $this->userName = (string) $configuration[self::USER_NAME];
            if (isset($configuration[self::PASSWORD])) {
                $this->password = (string) $configuration[self::PASSWORD];
            }
            if (isset($configuration[self::ATTRIBUTES])) {
                $this->attributes = (array) $configuration[self::ATTRIBUTES];
            }
            if (isset($configuration[self::APPLICATION_NAME])) {
                $this->applicationName = (string) $configuration[self::APPLICATION_NAME];
            }
        }

        /**
         * @return string Full DSN string.
         */
        final public function getDsn() {
            if (! is_null($this->customDsn)) {
                return $this->customDsn;
            }

            $dsn = self::DRIVER . ':dbname=' . $this->escapeParameter($this->dbName);
            if (! is_null($this->host)) {
                $dsn .= ';host=' . $this->escapeParameter($this->host);
            }
            if (! is_null($this->port)) {
                $dsn .= ';port=' . $this->escapeParameter($this->port);
            }
            if (! is_null($this->applicationName)) {
                $dsn .= ';application_name=' . $this->escapeParameter($this->applicationName);
            }
            return $dsn;
        }

        /**
         * @return string Database user.
         */
        final public function getUserName() {
            return $this->userName;
        }

        /**
         * @return string User's password.
         */
        final public function getPassword() {
            return $this->password;
        }

        /**
         * @return array Additional PDO connection attributes.
         */
        final public function getAttributes() {
            return [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true,
            ] + $this->attributes;
        }

        /**
         * Escape DSN parameters according to libpq rules.
         *
         * @param string $value
         * @return string
         */
        private function escapeParameter($value) {
            return "'" . addcslashes($value, "\\'") . "'";
        }
    }
}
