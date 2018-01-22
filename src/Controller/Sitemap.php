<?php

namespace Bolt\Extension\Bolt\Sitemap\Controller;

use Silex\Api\ControllerProviderInterface;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The controller for Sitemap routes.
 */

class Sitemap implements ControllerProviderInterface
{
    /** @var Application */
    protected $app;

    /**
     * {@inheritdoc}
     */
    public function connect(Application $app)
    {
        $this->app = $app;

        /** @var ControllerCollection $ctr */
        $ctr = $app['controllers_factory'];

        // This matches both GET requests.
        $ctr->match('sitemap', [$this, 'sitemap'])
            ->bind('sitemap')
            ->method('GET');

        $ctr->match('sitemap.xml', [$this, 'sitemapXml'])
            ->bind('sitemapXml')
            ->method('GET');

        return $ctr;
    }

    /**
     * @param Application $app
     *
     * @return Response
     */
    public function sitemap(Application $app)
    {
        $config = $app['sitemap.config'];
        $twig = $app['twig'];
        $context = [
            'entries' => $app['sitemap.links'],
            'ignore_images'  => $config['ignore_images'],
        ];

        $body = $twig->render($config['template'], $context);

        return new Response($body, Response::HTTP_OK);
    }

    /**
     * @param Application $app
     *
     * @return Response
     */
    public function sitemapXml(Application $app)
    {
        $twig = $app['twig'];
        $config = $app['sitemap.config'];
        $context = [
            'entries' => $app['sitemap.links'],
            'ignore_images'  => $config['ignore_images'],
        ];

        $body = $twig->render($config['xml_template'], $context);

        $response = new Response($body, Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/xml; charset=utf-8');

        return $response;
    }
}
