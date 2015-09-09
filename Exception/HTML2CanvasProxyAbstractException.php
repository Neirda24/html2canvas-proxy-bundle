<?php

namespace HTML2Canvas\ProxyBundle\Exception;

abstract class HTML2CanvasProxyAbstractException extends \Exception
{
    /**
     * @param string $message
     * @param int    $code
     */
    public function __construct($message = '', $code = 0)
    {
        parent::__construct($message, $code);
    }
}
