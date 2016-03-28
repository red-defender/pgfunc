<?php
namespace PgFunc {
    /**
     * Base exception class of the library.
     *
     * @author red-defender
     * @package pgfunc
     */
    abstract class Exception extends \Exception {
        /**
         * Exception codes.
         */
        const CONFIGURATION_ERROR = 1;
        const FAILED_CONNECT = 2;
        const FAILED_PREPARE = 3;
        const FAILED_BIND = 4;
        const FAILED_QUERY = 5;
        const INVALID_IDENTIFIER = 6;
        const INVALID_RETURN_TYPE = 7;
        const INVALID_DEFINITION = 8;
        const INVALID_DATA = 9;
        const TRANSACTION_ERROR = 10;
    }
}
