<?php
namespace PgFunc\OptionTrait {
    use PgFunc\Exception;
    use PgFunc\Exception\Usage;

    /**
     * Methods for dealing with local PostgreSQL parameters.
     *
     * @author red-defender
     * @package pgfunc
     */
    trait LocalParams {
        /**
         * @var array Local PostgreSQL parameters list.
         */
        private $localParams = [];

        /**
         * @return array Local PostgreSQL parameters list.
         */
        final public function getLocalParams() {
            return $this->localParams;
        }

        /**
         * @param array $localParams Local PostgreSQL parameters list.
         * @return static
         */
        final public function setLocalParams(array $localParams) {
            $this->checkLocalParams($localParams);
            $this->localParams = $localParams;
            return $this;
        }

        /**
         * @param array $localParams Local PostgreSQL parameters list.
         * @return static
         */
        final public function addLocalParams(array $localParams) {
            $this->checkLocalParams($localParams);
            $this->localParams = array_replace($this->localParams, $localParams);
            return $this;
        }

        /**
         * @param array $localParams Local PostgreSQL parameters list.
         * @throws Usage When not all parameters are scalar or null.
         */
        private function checkLocalParams(array $localParams) {
            $wrongParams = array_filter($localParams, function ($value) {
                return ! is_scalar($value) && ! is_null($value);
            });
            if ($wrongParams) {
                throw new Usage(
                    'Invalid local parameters: ' . implode(', ', array_keys($wrongParams)),
                    Exception::INVALID_PARAMETER
                );
            }
        }
    }
}
