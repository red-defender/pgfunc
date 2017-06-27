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
        const FAILED_BIND           = 101;
        const FAILED_CONNECT        = 102;
        const FAILED_PREPARE        = 103;
        const FAILED_QUERY          = 104;
        const INVALID_DATA          = 201;
        const INVALID_DEFINITION    = 202;
        const INVALID_IDENTIFIER    = 203;
        const INVALID_PARAMETER     = 204;
        const INVALID_RETURN_TYPE   = 205;
        const TRANSACTION_ERROR     = 301;
    }
}
