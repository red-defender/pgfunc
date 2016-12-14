<?php
namespace PgFunc\SpecialType {
    use PgFunc\Exception;
    use PgFunc\Exception\Usage;
    use PgFunc\SpecialType;

    /**
     * BYTEA special type.
     *
     * @author red-defender
     * @package pgfunc
     */
    final class Bytea extends SpecialType {
        /**
         * @inheritdoc
         */
        public function checkData($value, $prefix) {
            if (is_scalar($value) || is_resource($value)) {
                return;
            }
            throw new Usage('BYTEA value is not scalar or resource: ' . $prefix, Exception::INVALID_DATA);
        }

        /**
         * @inheritdoc
         */
        public function getSql($value, $prefix) {
            return ':' . $prefix . '::BYTEA';
        }

        /**
         * @inheritdoc
         */
        public function getParameter($value, $prefix) {
            return [':' . $prefix => $value];
        }

        /**
         * @inheritdoc
         */
        public function isCacheable() {
            return true;
        }
    }
}
