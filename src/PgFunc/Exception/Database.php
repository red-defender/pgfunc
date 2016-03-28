<?php
namespace PgFunc\Exception {
    use PgFunc\Exception;

    /**
     * Exception caused by database error.
     *
     * Always contains a nested PDOException object.
     *
     * @see Exception::getPrevious()
     *
     * @author red-defender
     * @package pgfunc
     */
    class Database extends Exception {

    }
}
