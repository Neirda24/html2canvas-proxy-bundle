<?php

namespace HTML2Canvas\ProxyBundle\Exception;

class HTML2CanvasProxyException extends HTML2CanvasProxyAbstractException
{
    /**
     * {@inheritdoc}
     */
    public function __construct($message = '', $code = 0)
    {
        parent::__construct($message, $code);
    }
}
