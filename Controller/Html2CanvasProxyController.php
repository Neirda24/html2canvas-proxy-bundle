<?php

namespace HTML2Canvas\ProxyBundle\Controller;

use HTML2Canvas\ProxyBundle\Event\ScreenPathEvent;
use HTML2Canvas\ProxyBundle\HTML2CanvasProxyEvents;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTML2Canvas Proxy Controller.
 */
class Html2CanvasProxyController extends Controller
{
    /**
     * @return Response
     */
    public function indexAction()
    {
        $html2CanvasProxy = $this->container->get('html2canvasproxybundle.services.html2canvasproxy');
        $response = $html2CanvasProxy->execute();

        return new Response($response);
    }

    /**
     * @param Request $request
     */
    public function generateScreenAction(Request $request)
    {
        $dispatcher  = $this->get('event_dispatcher');
        $image       = $request->request->get('image');
        $path        = $this->container->getParameter('html2canvas_proxy.config_proxy.screen_path');
        $screenPath  =  $path.'/'.sha1(uniqid('', false)).'.png';

        $event = new ScreenPathEvent($screenPath);
        $dispatcher->dispatch(HTML2CanvasProxyEvents::SCREEN_PATH, $event);

        $image = str_replace('data:image/png;base64,', '', $image);
        $decoded = base64_decode($image);

        file_put_contents($screenPath, $decoded, LOCK_EX);

        return new JsonResponse([
            'screen' => $screenPath,
        ]);
    }
}
