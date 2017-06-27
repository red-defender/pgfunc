<?php
namespace PgFunc\OptionTrait {
    /**
     * Methods for JSON decoding control.
     *
     * @author red-defender
     * @package pgfunc
     */
    trait JsonResult {
        /**
         * @var bool|null Decode JSON data as array.
         */
        private $isJsonAsArray;

        /**
         * @param bool|null $isJsonAsArray Decode JSON data as array (null means default).
         * @return static
         */
        final public function setIsJsonAsArray($isJsonAsArray) {
            $this->isJsonAsArray = is_null($isJsonAsArray) ? null : (bool) $isJsonAsArray;
            return $this;
        }

        /**
         * @return bool|null Decode JSON data as array.
         */
        final public function isJsonAsArray() {
            return $this->isJsonAsArray;
        }
    }
}
