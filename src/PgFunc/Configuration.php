<?php
namespace PgFunc {
    use PDO;
    use PgFunc\OptionTrait\AttemptsCount;
    use PgFunc\OptionTrait\JsonResult;
    use PgFunc\OptionTrait\LocalParams;

    /**
     * Connection configuration.
     *
     * @author red-defender
     * @package pgfunc
     */
    class Configuration {
        use AttemptsCount, JsonResult, LocalParams;

        /**
         * PDO driver name.
         */
        const DRIVER = 'pgsql';

        /**
         * @var array Libpq connection settings.
         */
        private $dsnParts = [
            'application_name'          => null,
            'client_encoding'           => null,
            'connect_timeout'           => null,
            'dbname'                    => null,
            'fallback_application_name' => null,
            'gsslib'                    => null,
            'host'                      => null,
            'hostaddr'                  => null,
            'keepalives'                => null,
            'keepalives_count'          => null,
            'keepalives_idle'           => null,
            'keepalives_interval'       => null,
            'krbsrvname'                => null,
            'options'                   => null,
            'passfile'                  => null,
            'port'                      => null,
            'requirepeer'               => null,
            'requiressl'                => null,
            'service'                   => null,
            'sslcert'                   => null,
            'sslcompression'            => null,
            'sslcrl'                    => null,
            'sslkey'                    => null,
            'sslmode'                   => null,
            'sslrootcert'               => null,
            'target_session_attrs'      => null,
            'tty'                       => null,
        ];

        /**
         * @var string|null Custom DSN string.
         */
        private $customDsn;

        /**
         * @var string|null Database user.
         */
        private $user;

        /**
         * @var string|null User's password.
         */
        private $password;

        /**
         * @var array Additional PDO connection attributes.
         */
        private $attributes = [];

        /**
         * @var bool Transactions are allowed in this connection.
         */
        private $isTransactionEnabled = true;

        /**
         * @return string Full DSN string.
         */
        final public function getDsn() {
            if (! is_null($this->customDsn)) {
                return $this->customDsn;
            }

            $dsnParts = array_filter($this->dsnParts, 'strlen');
            array_walk($dsnParts, function (& $value, $key) {
                $value = $key . "='" . addcslashes($value, "\\'") . "'";
            });
            return self::DRIVER . ':' . implode(';', $dsnParts);
        }

        /**
         * @return string|null Database user.
         */
        final public function getUser() {
            return $this->user;
        }

        /**
         * @return string|null User's password.
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
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM,
                PDO::ATTR_EMULATE_PREPARES   => true,
            ] + $this->attributes;
        }

        /**
         * @return bool Transactions are allowed in this connection.
         */
        final public function isTransactionEnabled() {
            return $this->isTransactionEnabled;
        }

        /**
         * @param string $customDsn Custom DSN string.
         * @return self
         */
        final public function setCustomDsn($customDsn) {
            $this->customDsn = (string) $customDsn;
            return $this;
        }

        /**
         * @param string $user Database user.
         * @return self
         */
        final public function setUser($user) {
            $this->user = (string) $user;
            return $this;
        }

        /**
         * @param string $password User's password.
         * @return self
         */
        final public function setPassword($password) {
            $this->password = (string) $password;
            return $this;
        }

        /**
         * @param array $attributes Additional PDO connection attributes.
         * @return self
         */
        final public function setAttributes(array $attributes) {
            $this->attributes = $attributes;
            return $this;
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
         * @param string $applicationName Libpq connection setting "application_name".
         * @return self
         */
        final public function setApplicationName($applicationName) {
            $this->dsnParts['application_name'] = (string) $applicationName;
            return $this;
        }

        /**
         * @param string $clientEncoding Libpq connection setting "client_encoding".
         * @return self
         */
        final public function setClientEncoding($clientEncoding) {
            $this->dsnParts['client_encoding'] = (string) $clientEncoding;
            return $this;
        }

        /**
         * @param int $connectTimeout Libpq connection setting "connect_timeout".
         * @return self
         */
        final public function setConnectTimeout($connectTimeout) {
            $this->dsnParts['connect_timeout'] = (int) $connectTimeout;
            return $this;
        }

        /**
         * @param string $dbName Libpq connection setting "dbname".
         * @return self
         */
        final public function setDbName($dbName) {
            $this->dsnParts['dbname'] = (string) $dbName;
            return $this;
        }

        /**
         * @param string $fallbackApplicationName Libpq connection setting "fallback_application_name".
         * @return self
         */
        final public function setFallbackApplicationName($fallbackApplicationName) {
            $this->dsnParts['fallback_application_name'] = (string) $fallbackApplicationName;
            return $this;
        }

        /**
         * @param string $gssLib Libpq connection setting "gsslib".
         * @return self
         */
        final public function setGssLib($gssLib) {
            $this->dsnParts['gsslib'] = (string) $gssLib;
            return $this;
        }

        /**
         * @param string $host Libpq connection setting "host".
         * @return self
         */
        final public function setHost($host) {
            $this->dsnParts['host'] = (string) $host;
            return $this;
        }

        /**
         * @param string $hostAddr Libpq connection setting "hostaddr".
         * @return self
         */
        final public function setHostAddr($hostAddr) {
            $this->dsnParts['hostaddr'] = (string) $hostAddr;
            return $this;
        }

        /**
         * @param bool $keepAlives Libpq connection setting "keepalives".
         * @return self
         */
        final public function setKeepAlives($keepAlives) {
            $this->dsnParts['keepalives'] = $keepAlives ? 1 : 0;
            return $this;
        }

        /**
         * @param int $keepAlivesCount Libpq connection setting "keepalives_count".
         * @return self
         */
        final public function setKeepAlivesCount($keepAlivesCount) {
            $this->dsnParts['keepalives_count'] = (int) $keepAlivesCount;
            return $this;
        }

        /**
         * @param int $keepAlivesIdle Libpq connection setting "keepalives_idle".
         * @return self
         */
        final public function setKeepAlivesIdle($keepAlivesIdle) {
            $this->dsnParts['keepalives_idle'] = (int) $keepAlivesIdle;
            return $this;
        }

        /**
         * @param int $keepAlivesInterval Libpq connection setting "keepalives_interval".
         * @return self
         */
        final public function setKeepAlivesInterval($keepAlivesInterval) {
            $this->dsnParts['keepalives_interval'] = (int) $keepAlivesInterval;
            return $this;
        }

        /**
         * @param string $krbSrvName Libpq connection setting "krbsrvname".
         * @return self
         */
        final public function setKrbSrvName($krbSrvName) {
            $this->dsnParts['krbsrvname'] = (string) $krbSrvName;
            return $this;
        }

        /**
         * @param string $options Libpq connection setting "options".
         * @return self
         */
        final public function setOptions($options) {
            $this->dsnParts['options'] = (string) $options;
            return $this;
        }

        /**
         * @param string $passFile Libpq connection setting "passfile".
         * @return self
         */
        final public function setPassFile($passFile) {
            $this->dsnParts['passfile'] = (string) $passFile;
            return $this;
        }

        /**
         * @param int $port Libpq connection setting "port".
         * @return self
         */
        final public function setPort($port) {
            $this->dsnParts['port'] = (int) $port;
            return $this;
        }

        /**
         * @param string $requirePeer Libpq connection setting "requirepeer".
         * @return self
         */
        final public function setRequirePeer($requirePeer) {
            $this->dsnParts['requirepeer'] = (string) $requirePeer;
            return $this;
        }

        /**
         * @param bool $requireSsl Libpq connection setting "requiressl".
         * @return self
         */
        final public function setRequireSsl($requireSsl) {
            $this->dsnParts['requiressl'] = $requireSsl ? 1 : 0;
            return $this;
        }

        /**
         * @param string $service Libpq connection setting "service".
         * @return self
         */
        final public function setService($service) {
            $this->dsnParts['service'] = (string) $service;
            return $this;
        }

        /**
         * @param string $sslCert Libpq connection setting "sslcert".
         * @return self
         */
        final public function setSslCert($sslCert) {
            $this->dsnParts['sslcert'] = (string) $sslCert;
            return $this;
        }

        /**
         * @param bool $sslCompression Libpq connection setting "sslcompression".
         * @return self
         */
        final public function setSslCompression($sslCompression) {
            $this->dsnParts['sslcompression'] = $sslCompression ? 1 : 0;
            return $this;
        }

        /**
         * @param string $sslCrl Libpq connection setting "sslcrl".
         * @return self
         */
        final public function setSslCrl($sslCrl) {
            $this->dsnParts['sslcrl'] = (string) $sslCrl;
            return $this;
        }

        /**
         * @param string $sslKey Libpq connection setting "sslkey".
         * @return self
         */
        final public function setSslKey($sslKey) {
            $this->dsnParts['sslkey'] = (string) $sslKey;
            return $this;
        }

        /**
         * @param string $sslMode Libpq connection setting "sslmode".
         * @return self
         */
        final public function setSslMode($sslMode) {
            $this->dsnParts['sslmode'] = (string) $sslMode;
            return $this;
        }

        /**
         * @param string $sslRootCert Libpq connection setting "sslrootcert".
         * @return self
         */
        final public function setSslRootCert($sslRootCert) {
            $this->dsnParts['sslrootcert'] = (string) $sslRootCert;
            return $this;
        }

        /**
         * @param string $targetSessionAttrs Libpq connection setting "target_session_attrs".
         * @return self
         */
        final public function setTargetSessionAttrs($targetSessionAttrs) {
            $this->dsnParts['target_session_attrs'] = (string) $targetSessionAttrs;
            return $this;
        }

        /**
         * @param string $tty Libpq connection setting "tty".
         * @return self
         */
        final public function setTty($tty) {
            $this->dsnParts['tty'] = (string) $tty;
            return $this;
        }
    }
}
