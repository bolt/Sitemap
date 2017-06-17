<?php

namespace Bolt\Extension\Bolt\Sitemap\Controller;

use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The controller for Sitemap routes.
 *
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
     * @param Request $request
     *
     * @return Response
     */
    public function sitemap(Application $app, Request $request)
    {
        $config = $app['sitemap.config'];

        $body = $app["twig"]->render($config['template'], ['entries' => $app['sitemap.links']]);

        return new Response($body, Response::HTTP_OK);
    }

    /**
     * @param Application $app
     * @param Request $request
     *
     * @return Response
     */
    public function sitemapXml(Application $app, Request $request)
    {
        $config = $app['sitemap.config'];

        $body = $app["twig"]->render($config['xml_template'], ['entries' => $app['sitemap.links']]);

        $response = new Response($body, Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/xml; charset=utf-8');

        return $response;
    }
}