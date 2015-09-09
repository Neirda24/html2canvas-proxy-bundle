<?php

namespace HTML2Canvas\ProxyBundle\Exception;

abstract class ErrorStatic
{
    /**
     * @const array MESSAGES
     */
    const MESSAGES = [
        self::REQUEST_NOT_SEND => 'The client did not send the Host header',
        self::PORT_NOT_FOUND => 'The Server-proxy did not send the PORT (configure PHP)',
        self::SHORT_EXEC_TIME => 'Execution time is less %d seconds, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled), recommended time is 30 seconds or more',
        self::SOCKET_EXEC_TIME => 'The execution time is not configured enough to SOCKET_EXEC_TIME in ErrorStatic, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled), recommended that the "max_execution_time =;" be a minimum of 5 seconds longer or reduce the SOCKET_EXEC_TIME in `ErrorStatic::`SOCKET_EXEC_TIME',
        self::MISSING_URL_GET_PARAMETER => 'No such parameter "url"',
        self::INVALID_CALLBACK_PARAMETER => 'Parameter "callback" contains invalid characters',
        self::FAILED_CREATE_DIRECTORY => 'Cannot create directory `%s`',
        self::FAILED_CREATE_FILE => 'Cannot create file `%s`',
        self::FAILED_RENAME_FILE => 'Cannot rename temporary file `%s`',
    ];

    /**
     * @const int REQUEST_NOT_SEND
     */
    const REQUEST_NOT_SEND = 0;

    /**
     * @const int PORT_NOT_FOUND
     */
    const PORT_NOT_FOUND = 1;

    /**
     * @const int SHORT_EXEC_TIME
     */
    const SHORT_EXEC_TIME = 2;

    /**
     * @const int SOCKET_EXEC_TIME
     */
    const SOCKET_EXEC_TIME = 3;

    /**
     * @const int MISSING_URL_GET_PARAMETER
     */
    const MISSING_URL_GET_PARAMETER = 4;

    /**
     * @const int INVALID_CALLBACK_PARAMETER
     */
    const INVALID_CALLBACK_PARAMETER = 5;

    /**
     * @const int FAILED_CREATE_DIRECTORY
     */
    const FAILED_CREATE_DIRECTORY = 6;

    /**
     * @const int FAILED_CREATE_FILE
     */
    const FAILED_CREATE_FILE = 7;

    /**
     * @const int FAILED_RENAME_FILE
     */
    const FAILED_RENAME_FILE = 8;
}
