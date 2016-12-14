<?php
namespace PgFunc\SpecialType {
    use PgFunc\Exception;
    use PgFunc\Exception\Usage;
    use PgFunc\SpecialType;

    /**
     * Base class for range special types.
     *
     * @author red-defender
     * @package pgfunc
     */
    abstract class AbstractRange extends SpecialType {
        /**
         * @inheritdoc
         */
        final public function checkData($value, $prefix) {
            if (! is_array($value)) {
                throw new Usage('Range value is not an array: ' . $prefix, Exception::INVALID_DATA);
            }
            if (count($value) < 2 || count($value) > 3) {
                throw new Usage('Range value contains wrong number of arguments: ' . $prefix, Exception::INVALID_DATA);
            }
            if (count($value) !== count(array_filter(array_map([$this, 'isScalarOrNull'], $value)))) {
                throw new Usage('Range value contains non-scalar elements: ' . $prefix, Exception::INVALID_DATA);
            }
        }

        /**
         * @inheritdoc
         */
        final public function getSql($value, $prefix) {
            return static::getTypeName() . '(' . implode(',', array_keys($this->getParameter($value, $prefix))) . ')';
        }

        /**
         * @inheritdoc
         */
        final public function getParameter($value, $prefix) {
            $value = array_values($value);
            $return = [
                ':' . $prefix . 'l' => $value[0],
                ':' . $prefix . 'u' => $value[1],
            ];
            if (count($value) > 2) {
                $return[':' . $prefix . 'b'] = $value[2];
            }
            return $return;
        }

        /**
         * @inheritdoc
         */
        final public function isCacheable() {
            return false;
        }
    }
}
