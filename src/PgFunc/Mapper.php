<?php
namespace PgFunc {
    /**
     * Base mapper class.
     *
     * @author red-defender
     * @package pgfunc
     */
    abstract class Mapper {
        /**
         * PostgreSQL data types, supported by the library.
         *
         * Special types (BYTEA, HSTORE, ranges, etc) are created with custom method getSpecialType().
         */
        const BIGINT                      = 'INT8';
        const BIT                         = 'BIT';
        const BIT_VARYING                 = 'VARBIT';
        const BOOL                        = 'BOOL';
        const BOOLEAN                     = 'BOOL';
        const CHAR                        = 'CHAR';
        const CHARACTER                   = 'CHAR';
        const CHARACTER_VARYING           = 'VARCHAR';
        const CIDR                        = 'CIDR';
        const CITEXT                      = 'CITEXT';
        const DATE                        = 'DATE';
        const DECIMAL                     = 'NUMERIC';
        const DOUBLE_PRECISION            = 'FLOAT8';
        const FLOAT                       = 'FLOAT8';
        const FLOAT4                      = 'FLOAT4';
        const FLOAT8                      = 'FLOAT8';
        const INET                        = 'INET';
        const INT                         = 'INT4';
        const INT2                        = 'INT2';
        const INT4                        = 'INT4';
        const INT8                        = 'INT8';
        const INTEGER                     = 'INT4';
        const INTERVAL                    = 'INTERVAL';
        const JSON                        = 'JSON';
        const JSONB                       = 'JSONB';
        const LTREE                       = 'LTREE';
        const MACADDR                     = 'MACADDR';
        const MONEY                       = 'MONEY';
        const NUMERIC                     = 'NUMERIC';
        const OID                         = 'OID';
        const REAL                        = 'FLOAT4';
        const SMALLINT                    = 'INT2';
        const TEXT                        = 'TEXT';
        const TIME                        = 'TIME';
        const TIME_WITH_TIME_ZONE         = 'TIMETZ';
        const TIME_WITHOUT_TIME_ZONE      = 'TIME';
        const TIMESTAMP                   = 'TIMESTAMP';
        const TIMESTAMP_WITH_TIME_ZONE    = 'TIMESTAMPTZ';
        const TIMESTAMP_WITHOUT_TIME_ZONE = 'TIMESTAMP';
        const TIMESTAMPTZ                 = 'TIMESTAMPTZ';
        const TIMETZ                      = 'TIMETZ';
        const UUID                        = 'UUID';
        const VARBIT                      = 'VARBIT';
        const VARCHAR                     = 'VARCHAR';
        const XML                         = 'XML';

        /**
         * Field name used to specify record type in parameter definition.
         */
        const RECORD_TYPE = '';

        /**
         * @var SpecialType[] Special types registry.
         */
        private static $specialTypes = [];

        /**
         * Get special type object by class name.
         *
         * @param string|SpecialType $class Special type class name.
         * @return SpecialType
         */
        final public static function getSpecialType($class) {
            $typeName = $class::getTypeName();
            if (empty(self::$specialTypes[$typeName])) {
                self::$specialTypes[$typeName] = new $class();
            }
            return self::$specialTypes[$typeName];
        }

        /**
         * Parse BYTEA output into binary string.
         *
         * Method works only with setting "bytea_output = hex".
         *
         * @param string $bytea BYTEA value in "hex" format.
         * @return string Binary string.
         */
        final public static function parseBytea($bytea) {
            return pack('H*', ltrim($bytea, '\x'));
        }
    }
}
