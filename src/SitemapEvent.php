<?php

namespace Bolt\Extension\Bolt\Sitemap;

use Bolt\Collection\MutableBag;
use Symfony\Component\EventDispatcher\GenericEvent;

class SitemapEvent extends GenericEvent
{
    /**
     * @return MutableBag
     */
    public function getLinks()
    {
        return $this->subject;
    }
}
