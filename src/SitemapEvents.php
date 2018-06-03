<?php

namespace Bolt\Extension\Bolt\Sitemap;

/**
 * Definitions for all possible SitemapEvents.
 *
 *  * @codeCoverageIgnore
 */
final class SitemapEvents
{
    private function __construct()
    {
    }

    const AFTER_COLLECTING_LINKS = 'sitemapAfterCollectingLinks';
}
