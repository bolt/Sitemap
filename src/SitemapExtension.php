<?php

namespace Bolt\Extension\Bolt\Sitemap;

use Bolt\Asset\Snippet\Snippet;
use Bolt\Asset\Target;
use Bolt\Collection\MutableBag;
use Bolt\Controller\Zone;
use Bolt\Extension\SimpleExtension;
use Bolt\Legacy\Content;
use Carbon\Carbon;
use Silex\Application;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sitemap extension for Bolt.
 *
 * @author Bob den Otter <bob@twokings.nl>
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class SitemapExtension extends SimpleExtension
{
    /**
     * {@inheritdoc}
     */
    protected function registerServices(Application $app)
    {
        $app['sitemap.config'] = $app->share(
            function () {
                return $this->getConfig();
            }
        );
        $app['sitemap.links'] = $app->share(
            function () {
                return $this->getLinks();
            }
        );
        $app['sitemap.controller'] = $app->share(
            function () {
                return new Controller\Sitemap();
            }
        );
    }

    /**
     * Twig function returns sitemap.
     *
     * @return array
     */
    protected function registerTwigFunctions()
    {
        return [
            'sitemapEntries' => 'twigGetLinks',
        ];
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
            ->setCallback(function () {
                $app = $this->getContainer();
                $snippet = sprintf(
                    '<link rel="sitemap" type="application/xml" title="Sitemap" href="%s">',
                    $app['url_generator']->generate('sitemapXml', [], UrlGeneratorInterface::ABSOLUTE_URL)
                );

                return $snippet;
            })
        ;

        return [
            $snippet,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {
        return [
            'ignore'             => [],
            'ignore_contenttype' => [],
            'remove_link'        => [],
            'ignore_listing'     => false,
            'ignore_images'      => false,
        ];
    }

    public function twigGetLinks()
    {
        return $this->getLinks();
    }

    /**
     * {@inheritdoc}
     */

    protected function registerTwigPaths()
    {
        return [
            'templates',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerFrontendControllers()
    {
        $app = $this->getContainer();

        return [
            '/' => $app['sitemap.controller'],
        ];
    }

    /**
     * Get an array of links.
     *
     * @return MutableBag
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

        $homepageLink = [
            'link'  => $app['url_generator']->generate('homepage'),
            'title' => $app['config']->get('general/sitename'),
        ];

        $links = new MutableBag();
        $links->add($homepageLink);

        foreach ($contentTypes as $contentType) {
            $searchable = (isset($contentType['searchable']) && $contentType['searchable']) || !isset($contentType['searchable']);
            $isIgnored = in_array($contentType['slug'], $config['ignore_contenttype']);
            $isIgnoredURL = in_array('/' . $contentType['slug'], $config['remove_link']);

            if (!$isIgnored && !$contentType['viewless'] && $searchable) {
                $baseDepth = 0;
                if (!$config['ignore_listing']) {
                    $baseDepth = 1;
                    if ($isIgnoredURL) {
                        $links->add([
                            'link'  => '',
                            'title' => $contentType['name'],
                            'depth' => 1,
                        ]);
                    } else {
                        $link = $this->getListingLink($contentType['slug']);
                        $links->add([
                            'link'  => $link,
                            'title' => $contentType['name'],
                            'depth' => 1,
                        ]);
                    }
                }
                $content = $app['storage']->getContent($contentType['slug'], $contentParams);
                /** @var Content $entry */
                foreach ($content as $entry) {
                    $links->add([
                        'link'    => $entry->link(),
                        'title'   => $entry->getTitle(),
                        'depth'   => $baseDepth + 1,
                        'lastmod' => Carbon::createFromTimestamp(strtotime($entry->get('datechanged')))->toW3cString(),
                        'record'  => $entry,
                    ]);
                }
            }
        }

        foreach ($links as $idx => $link) {
            if ($this->linkIsIgnored($link)) {
                unset($links[$idx]);
            }
        }

        return $this->transformByListeners($links);
    }

    /**
     * @param string $contentTypeSlug
     * @return string
     */
    private function getListingLink($contentTypeSlug)
    {
        $config = $this->getConfig();
        $urlGenerator = $this->getContainer()['url_generator'];
        $urlParameters = ['contenttypeslug' => $contentTypeSlug];

        if(isset($config['listing_routes']) && isset($config['listing_routes'][$contentTypeSlug])) {
            $routeName = $config['listing_routes'][$contentTypeSlug];

            return $urlGenerator->generate($routeName, $urlParameters);
        }

        return $urlGenerator->generate('contentlisting', $urlParameters);
    }

    /**
     * Check to see if a link should be ignored from teh sitemap.
     *
     * @param array $link
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

    /**
     * @param MutableBag $links
     * @return MutableBag
     */
    private function transformByListeners($links)
    {
        $event = new SitemapEvent($links);
        $this->getContainer()['dispatcher']->dispatch(SitemapEvents::AFTER_COLLECTING_LINKS, $event);

        return $event->getLinks();
    }
}
