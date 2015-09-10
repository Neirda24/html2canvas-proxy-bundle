<?php

namespace HTML2Canvas\ProxyBundle\Exception;

/**
 * @todo: Change error status
 */
abstract class ErrorStatic
{
    /**
     * @const array MESSAGES
     */
    const MESSAGES = [
        self::REQUEST_NOT_SEND => 'The client did not send the Host header',
        self::PORT_NOT_FOUND => 'The Server-proxy did not send the PORT (configure PHP)',
        self::SHORT_EXEC_TIME => 'Execution time is less #seconds# seconds, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled), recommended time is 30 seconds or more',
        self::SOCKET_EXEC_TIME => 'The execution time is not configured enough to SOCKET_TIMEOUT in HTML2Canvas\ProxyBundle\Services\Html2CanvasProxy::SOCKET_TIMEOUT, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled), recommended that the "max_execution_time =;" be a minimum of 5 seconds longer or reduce the SOCKET_TIMEOUT in `HTML2Canvas\ProxyBundle\Services\Html2CanvasProxy::SOCKET_TIMEOUT` = #SOCKET_TIMEOUT#',
        self::MISSING_URL_GET_PARAMETER => 'No such parameter "url"',
        self::INVALID_CALLBACK_PARAMETER => 'Parameter "callback" contains invalid characters',
        self::FAILED_CREATE_DIRECTORY => 'Cannot create directory `%s`',
        self::FAILED_CREATE_FILE => 'Cannot create file `%s`',
        self::FAILED_RENAME_FILE => 'Cannot rename temporary file `%s`',
    ];

    /**
     * @const int REQUEST_NOT_SEND
     */
    const REQUEST_NOT_SEND = '1-500';

    /**
     * @const int PORT_NOT_FOUND
     */
    const PORT_NOT_FOUND = '2-500';

    /**
     * @const int SHORT_EXEC_TIME
     */
    const SHORT_EXEC_TIME = '3-500';

    /**
     * @const int SOCKET_EXEC_TIME
     */
    const SOCKET_EXEC_TIME = '4-500';

    /**
     * @const int MISSING_URL_GET_PARAMETER
     */
    const MISSING_URL_GET_PARAMETER = '5-500';

    /**
     * @const int INVALID_CALLBACK_PARAMETER
     */
    const INVALID_CALLBACK_PARAMETER = '6-500';

    /**
     * @const int FAILED_CREATE_DIRECTORY
     */
    const FAILED_CREATE_DIRECTORY = '7-500';

    /**
     * @const int FAILED_CREATE_FILE
     */
    const FAILED_CREATE_FILE = '8-500';

    /**
     * @const int FAILED_RENAME_FILE
     */
    const FAILED_RENAME_FILE = '9-500';
}
