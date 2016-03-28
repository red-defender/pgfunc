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
            $params = [];
            foreach ($this->buildValueArray($value, $prefix) as $key => $item) {
                $params[] = is_null($item) ? 'NULL' : $key;
            }
            return static::getTypeName() . '(' . implode(',', $params) . ')';
        }

        /**
         * @inheritdoc
         */
        final public function getParameter($value, $prefix) {
            $return = [];
            foreach ($this->buildValueArray($value, $prefix) as $key => $item) {
                if (! is_null($item)) {
                    $return[$key] = $item;
                }
            }
            return $return;
        }

        /**
         * Build value array for binding.
         *
         * @param array $value Parameter value.
         * @param string $prefix Bind name.
         * @return array
         */
        private function buildValueArray(array $value, $prefix) {
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
    }
}
