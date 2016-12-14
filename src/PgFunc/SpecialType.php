<?php
namespace PgFunc {
    use PgFunc\Exception\Usage;

    /**
     * Base class for special input types.
     *
     * @author red-defender
     * @package pgfunc
     */
    abstract class SpecialType {
        /**
         * Get PostgreSQL type name.
         *
         * @return string
         */
        public static function getTypeName() {
            // Use current class name by default.
            $classParts = explode('\\', strtoupper(static::class));
            return end($classParts);
        }

        /**
         * Check input parameter for correctness.
         *
         * @param mixed $value Parameter value.
         * @param string $prefix Parameter name prefix.
         * @throws Usage When parameter is invalid.
         */
        abstract public function checkData($value, $prefix);

        /**
         * Build parameter SQL code.
         *
         * @param mixed $value Parameter value.
         * @param string $prefix Bind name.
         * @return string SQL code for parameter.
         */
        abstract public function getSql($value, $prefix);

        /**
         * Build array for binding parameter.
         *
         * @param mixed $value Parameter value.
         * @param string $prefix Bind name.
         * @return array Array for binding parameter.
         */
        abstract public function getParameter($value, $prefix);

        /**
         * SQL code for parameter can be cached.
         *
         * @return bool
         */
        abstract public function isCacheable();

        /**
         * @param mixed $value
         * @return bool
         */
        final protected function isScalarOrNull($value) {
            return is_scalar($value) || is_null($value);
        }
    }
}
