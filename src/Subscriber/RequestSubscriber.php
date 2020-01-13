<?php

namespace FINDOLOGIC\FinSearch\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onRequest'
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        require_once __DIR__ . '/../../vendor/autoload.php';
    }
}
