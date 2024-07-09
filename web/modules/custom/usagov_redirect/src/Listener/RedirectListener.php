<?php

namespace Drupal\usagov_redirect\RedirectListener;

use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RedirectListener
{
    protected $templating;

    public function __construct(EngineInterface $templating)
    {
        $this->templating = $templating;
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        $response = $event->getResponse();

        if (!($response instanceof RedirectResponse)) {
            return;
        }

        $uri  = $response->getTargetUrl();
        $html = $this->templating->render(
            'templates:301.html.twig',
            array('uri' => $uri)
        );

        $response->setContent($html);
    }
}