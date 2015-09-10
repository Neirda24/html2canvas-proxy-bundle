<?php

namespace HTML2Canvas\ProxyBundle\Exception\Handler;

use HTML2Canvas\ProxyBundle\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

class ProxyExceptionHandler
{
    /**
     * If the exception is of type Exception\HTML2CanvasProxyAbstractException then
     * use its code to generate a equivalent http status.
     *
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        // We get the exception object from the received event
        $exception = $event->getException();

        if ($exception instanceof Exception\HTML2CanvasProxyAbstractException) {
            $status = 500;
            if (0 !== $exception->getCode()) {
                $status = $exception->getCode();
            }

            $response = new JsonResponse('error: ' . $exception->getMessage(), $status);
            $event->setResponse($response);
        }
    }
}
