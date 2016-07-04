<?php
namespace PgFunc {
    use PgFunc\Exception\Usage;

    /**
     * Connection pool.
     *
     * You should configure your connections when application initializes.
     * Real connecting to database is performed only on demand.
     *
     * @author red-defender
     * @package pgfunc
     */
    class Pool {
        /**
         * @var Configuration[]
         */
        private static $configurationList = [];

        /**
         * @var Connection[]
         */
        private static $connectionList = [];

        /**
         * Saving connection configuration (reconfiguration drops current connection).
         *
         * @param string $connectionName
         * @param Configuration $configuration
         */
        final public static function configure($connectionName, Configuration $configuration) {
            self::$configurationList[$connectionName] = $configuration;
            self::disconnect((string) $connectionName);
        }

        /**
         * Getting connection (establish a real connection to database).
         *
         * @param string $connectionName
         * @return Connection
         * @throws Usage When connection is not configured.
         */
        final public static function getConnection($connectionName) {
            if (isset(self::$connectionList[$connectionName])) {
                return self::$connectionList[$connectionName];
            }

            if (! isset(self::$configurationList[$connectionName])) {
                throw new Usage('Connection is not configured: ' . $connectionName, Exception::CONFIGURATION_ERROR);
            }

            self::$connectionList[$connectionName] = new Connection(self::$configurationList[$connectionName]);
            return self::$connectionList[$connectionName];
        }

        /**
         * Remove connection from pool.
         *
         * @param string|null $connectionName Connection name (null means all connections in pool).
         */
        final public static function disconnect($connectionName = null) {
            if (is_null($connectionName)) {
                self::$connectionList = [];
                return;
            }
            unset(self::$connectionList[$connectionName]);
        }
    }
}
