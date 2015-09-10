<?php

namespace HTML2Canvas\ProxyBundle;

final class HTML2CanvasProxyEvents
{
    /**
     * The screen.path event is thrown each time an order is created
     * in the system.
     *
     * The event listener receives an
     * HTML2Canvas\ProxyBundle\Event\ScreenPathEvent instance.
     *
     * @var string
     */
    const SCREEN_PATH = 'screen.path';
}
