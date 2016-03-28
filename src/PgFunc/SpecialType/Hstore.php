<?php
namespace PgFunc\SpecialType {
    use PgFunc\Exception;
    use PgFunc\Exception\Usage;
    use PgFunc\SpecialType;

    /**
     * HSTORE special type.
     *
     * @author red-defender
     * @package pgfunc
     */
    final class Hstore extends SpecialType {
        /**
         * @inheritdoc
         */
        public function checkData($value, $prefix) {
            if (! is_array($value)) {
                throw new Usage('HSTORE value is not an array: ' . $prefix, Exception::INVALID_DATA);
            }
            if (count($value) !== count(array_filter(array_map([$this, 'isScalarOrNull'], $value)))) {
                throw new Usage('HSTORE array contains non-scalar elements: ' . $prefix, Exception::INVALID_DATA);
            }
        }

        /**
         * @inheritdoc
         */
        public function getSql($value, $prefix) {
            $hstore = [];
            $index = 0;
            foreach ($value as $item) {
                $item = isset($item) ? ':' . $prefix . 'v' . $index : 'NULL';
                $hstore[] = ':' . $prefix . 'k' . $index . ',' . $item;
                $index++;
            }
            return 'HSTORE(ARRAY[' . implode(',', $hstore) . ']::TEXT[])';
        }

        /**
         * @inheritdoc
         */
        public function getParameter($value, $prefix) {
            $index = 0;
            $hstore = [];
            foreach ($value as $name => $item) {
                $hstore[':' . $prefix . 'k' . $index] = $name;
                if (isset($item)) {
                    $hstore[':' . $prefix . 'v' . $index] = $item;
                }
                $index++;
            }
            return $hstore;
        }
    }
}
