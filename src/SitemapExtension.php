<?php

namespace Bolt\Extension\Bolt\Sitemap;

use Bolt\Asset\Snippet\Snippet;
use Bolt\Asset\Target;
use Bolt\Controller\Zone;
use Bolt\Extension\SimpleExtension;
use Bolt\Legacy\Content;
use Carbon\Carbon;
use Pimple\Container;
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
    protected function registerServices(Container $app)
    {
        $app['sitemap.config'] = function () {
            return $this->getConfig();
        };
        $app['sitemap.links'] = function () {
            return $this->getLinks();
        };
        $app['sitemap.controller'] = function () {
            return new Controller\Sitemap();
        };
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

        $links = [
            [
                'link'  => $app['url_generator']->generate('homepage'),
                'title' => $app['config']->get('general/sitename'),
            ],
        ];

        foreach ($contentTypes as $contentType) {
            $searchable = (isset($contentType['searchable']) && $contentType['searchable']) || !isset($contentType['searchable']);
            $isIgnored = in_array($contentType['slug'], $config['ignore_contenttype']);
            $isIgnoredURL = in_array('/' . $contentType['slug'], $config['remove_link']);

            if (!$isIgnored && !$contentType['viewless'] && $searchable) {
                $baseDepth = 0;
                if (!$config['ignore_listing']) {
                    $baseDepth = 1;
                    if ($isIgnoredURL) {
                        $links[] = [
                            'link'  => '',
                            'title' => $contentType['name'],
                            'depth' => 1,
                        ];
                    } else {
                        $link = $app['url_generator']->generate('contentlisting', ['contenttypeslug' => $contentType['slug']]);
                        $links[] = [
                            'link'  => $link,
                            'title' => $contentType['name'],
                            'depth' => 1,
                        ];
                    }
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
}
