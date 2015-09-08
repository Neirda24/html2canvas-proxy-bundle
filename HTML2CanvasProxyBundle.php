<?php

namespace HTML2Canvas\ProxyBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class HTML2CanvasProxyBundle extends Bundle
{
    /**
     * @const Token name to send to connect to api3a
     */
    const API3A_HEADER_NAME_TOKEN = 'api3a';

    /**
     * @const Token name to send to connect to the cloud
     */
    const CLOUD_HEADER_NAME_TOKEN = 'x-token';
}
