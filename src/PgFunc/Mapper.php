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
        const BIGINT = 'BIGINT';
        const BIT = 'BIT';
        const BIT_VARYING = 'BIT VARYING';
        const BOOL = 'BOOL';
        const BOOLEAN = 'BOOLEAN';
        const CHAR = 'CHAR';
        const CHARACTER = 'CHARACTER';
        const CHARACTER_VARYING = 'CHARACTER VARYING';
        const CIDR = 'CIDR';
        const CITEXT = 'CITEXT';
        const DATE = 'DATE';
        const DECIMAL = 'DECIMAL';
        const DOUBLE_PRECISION = 'DOUBLE PRECISION';
        const FLOAT = 'FLOAT';
        const FLOAT4 = 'FLOAT4';
        const FLOAT8 = 'FLOAT8';
        const INET = 'INET';
        const INT = 'INT';
        const INT2 = 'INT2';
        const INT4 = 'INT4';
        const INT8 = 'INT8';
        const INTEGER = 'INTEGER';
        const INTERVAL = 'INTERVAL';
        const JSON = 'JSON';
        const JSONB = 'JSONB';
        const LTREE = 'LTREE';
        const MACADDR = 'MACADDR';
        const MONEY = 'MONEY';
        const NUMERIC = 'NUMERIC';
        const OID = 'OID';
        const REAL = 'REAL';
        const SMALLINT = 'SMALLINT';
        const TEXT = 'TEXT';
        const TIME = 'TIME';
        const TIME_WITH_TIME_ZONE = 'TIME WITH TIME ZONE';
        const TIME_WITHOUT_TIME_ZONE = 'TIME WITHOUT TIME ZONE';
        const TIMESTAMP = 'TIMESTAMP';
        const TIMESTAMP_WITH_TIME_ZONE = 'TIMESTAMP WITH TIME ZONE';
        const TIMESTAMP_WITHOUT_TIME_ZONE = 'TIMESTAMP WITHOUT TIME ZONE';
        const TIMESTAMPTZ = 'TIMESTAMPTZ';
        const TIMETZ = 'TIMETZ';
        const UUID = 'UUID';
        const VARBIT = 'VARBIT';
        const VARCHAR = 'VARCHAR';
        const XML = 'XML';

        /**
         * Field name used to specify record type in parameter definition.
         */
        const RECORD_TYPE = '';

        /**
         * @var SpecialType[] Special types registry.
         */
        private static $specialTypeList = [];

        /**
         * Get special type object by class name.
         *
         * @param string|SpecialType $class Special type class name.
         * @return SpecialType
         */
        final public static function getSpecialType($class) {
            $typeName = $class::getTypeName();
            if (empty(self::$specialTypeList[$typeName])) {
                self::$specialTypeList[$typeName] = new $class();
            }
            return self::$specialTypeList[$typeName];
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
