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
            $count = count($value);
            $hstore = [];
            for ($index = 0; $index < $count; $index++) {
                $hstore[] = ':' . $prefix . 'k' . $index . ',:' . $prefix . 'v' . $index;
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
                $hstore[':' . $prefix . 'v' . $index] = $item;
                $index++;
            }
            return $hstore;
        }

        /**
         * @inheritdoc
         */
        public function isCacheable() {
            return false;
        }
    }
}
