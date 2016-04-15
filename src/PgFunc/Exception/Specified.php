<?php
namespace PgFunc\Exception {
    use Exception;

    /**
     * Exception caused by a specified database error (may be known constraint violation or raised exception).
     *
     * Exception code here is always zero.
     *
     * @author red-defender
     * @package pgfunc
     */
    class Specified extends Database {
        /**
         * Override exception message with predefined property.
         *
         * @param string|null $message
         * @param int $code
         * @param Exception|null $previous
         */
        public function __construct($message = null, $code = 0, Exception $previous = null) {
            if (is_null($message)) {
                $message = $this->message;
            }
            parent::__construct($message, $code, $previous);
        }
    }
}
