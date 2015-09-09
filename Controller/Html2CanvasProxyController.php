<?php

namespace HTML2Canvas\ProxyBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * HTML2Canvas Proxy Controller.
 */
class Html2CanvasProxyController extends Controller
{
    /**
     * @param Request $request
     */
    public function indexAction(Request $request)
    {
        $html2CanvasProxy = $this->container->get('html2canvasproxybundle.services.html2canvasproxy');
        $html2CanvasProxy->execute();
    }
}
