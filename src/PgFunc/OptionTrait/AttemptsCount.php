<?php
namespace PgFunc\OptionTrait {
    use PgFunc\Exception;
    use PgFunc\Exception\Usage;

    /**
     * Methods for setting the number of attempts.
     *
     * @author red-defender
     * @package pgfunc
     */
    trait AttemptsCount {
        /**
         * @var int|null Number of attempts to execute procedure.
         */
        private $queryAttemptsCount;

        /**
         * @param int|null $queryAttemptsCount Number of attempts to execute procedure (null means default).
         * @return static
         * @throws Usage When value is invalid.
         */
        final public function setQueryAttemptsCount($queryAttemptsCount) {
            $queryAttemptsCount = is_null($queryAttemptsCount) ? null : (int) $queryAttemptsCount;
            if (is_int($queryAttemptsCount) && $queryAttemptsCount <= 0) {
                throw new Usage('Wrong query attempts count: ' . $queryAttemptsCount, Exception::INVALID_PARAMETER);
            }
            $this->queryAttemptsCount = $queryAttemptsCount;
            return $this;
        }

        /**
         * @return int|null Number of attempts to execute procedure.
         */
        final public function getQueryAttemptsCount() {
            return $this->queryAttemptsCount;
        }
    }
}
