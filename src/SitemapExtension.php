<?php

namespace Bolt\Extension\Bolt\Sitemap;

use Bolt\Asset\Snippet\Snippet;
use Bolt\Asset\Target;
use Bolt\Controller\Zone;
use Bolt\Extension\SimpleExtension;
use Bolt\Legacy\Content;
use Carbon\Carbon;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sitemap extension for Bolt.
 *
 * @author Bob den Otter <bob@twokings.nl>
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class SitemapExtension extends SimpleExtension
{
    /**
     * Route for regular sitemap.
     *
     * @return Response
     */
    public function sitemap($xml = false)
    {
        $config = $this->getConfig();
        $body = $this->renderTemplate($config['template'], ['entries' => $this->getLinks()]);

        return new Response($body, Response::HTTP_OK);
    }

    /**
     * Route for XML based sitemap.
     *
     * @return Response
     */
    public function sitemapXml()
    {
        $config = $this->getConfig();
        $body = $this->renderTemplate($config['xml_template'], ['entries' => $this->getLinks()]);

        $response = new Response($body, Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/xml; charset=utf-8');

        return $response;
    }

    /**
     * Callback to generate the <link> inserted in the <head> section.
     *
     * @return string
     */
    public function snippetCallback()
    {
        $app = $this->getContainer();
        $snippet = sprintf(
            '<link rel="sitemap" type="application/xml" title="Sitemap" href="%ssitemap.xml">',
            $app['resources']->getUrl('root')
        );

        return $snippet;
    }
    /**
     * {@inheritdoc}
     */
    protected function registerAssets()
    {
        $snippet = new Snippet();
        $snippet
            ->setLocation(Target::END_OF_HEAD)
            ->setZone(Zone::FRONTEND)
            ->setCallback([$this, 'snippetCallback'])
        ;

        return [
            $snippet,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Set up the routes for the sitemap.
     */
    protected function registerFrontendRoutes(ControllerCollection $collection)
    {
        $collection->match('sitemap', [$this, 'sitemap']);
        $collection->match('sitemap.xml', [$this, 'sitemapXml']);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {
        return [
            'ignore'             => [],
            'ignore_contenttype' => [],
            'ignore_listing'     => false,
        ];
    }

    /**
     * Get an array of links.
     *
     * @return array
     */
    private function getLinks()
    {
        // If we have a boatload of content, we might need a bit more memory.
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $app = $this->getContainer();
        $config = $this->getConfig();
        $contentTypes = $app['config']->get('contenttypes');
        $contentParams = ['limit' => 10000, 'order' => 'datepublish desc', 'hydrate' => false];
        $rootPath = $app['resources']->getUrl('root');

        $links = [
            [
                'link'  => $rootPath,
                'title' => $app['config']->get('general/sitename'),
            ],
        ];
        foreach ($contentTypes as $contentType) {
            $searchable = (isset($contentType['searchable']) && $contentType['searchable']) || !isset($contentType['searchable']);
            $isIgnored = in_array($contentType['slug'], $config['ignore_contenttype']);

            if (!$isIgnored && !$contentType['viewless'] && $searchable) {
                $baseDepth = 0;
                if (!$config['ignore_listing']) {
                    $baseDepth = 1;
                    $links[] = [
                        'link'  => $rootPath . $contentType['slug'],
                        'title' => $contentType['name'],
                        'depth' => 1,
                    ];
                }
                $content = $app['storage']->getContent($contentType['slug'], $contentParams);
                /** @var Content $entry */
                foreach ($content as $entry) {
                    $links[] = [
                        'link'    => $entry->link(),
                        'title'   => $entry->getTitle(),
                        'depth'   => $baseDepth + 1,
                        'lastmod' => Carbon::createFromTimestamp(strtotime($entry->get('datechanged')))->toW3cString(),
                        'record'  => $entry,
                    ];
                }
            }
        }

        foreach ($links as $idx => $link) {
            if ($this->linkIsIgnored($link)) {
                unset($links[$idx]);
            }
        }

        return $links;
    }

    /**
     * Check to see if a link should be ignored from teh sitemap.
     *
     * @param string $link
     *
     * @return bool
     */
    private function linkIsIgnored($link)
    {
        $config = $this->getConfig();

        if (in_array($link['link'], $config['ignore'])) {
            // Perfect match
            return true;
        }

        // Use ignore as a regex
        foreach ($config['ignore'] as $ignore) {
            $pattern = str_replace('/', '\/', $ignore);

            // Match on whole string so a $ignore of "/entry/" isn't the same as "/entry/.*"
            if (preg_match("/^{$pattern}$/", $link['link'])) {
                return true;
            }
        }

        // No absolute match & no regex match
        return false;
    }
}
