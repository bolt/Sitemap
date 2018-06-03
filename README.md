Sitemap
=======

This extension will automatically create XML sitemaps for your Bolt sites.
After enabling the extension, go to `http://example.org/sitemap.xml` to see it.

The bigger search-engines like Google and Bing will automatically pick up your
sitemap after a while, but it's always a good idea to explicitly tell the
search engines where to find it. To do so, this extension automatically adds
the link to the `<head>` section of your pages:

```html
<link rel="sitemap" type="application/xml" title="Sitemap" href="/sitemap.xml" />
```

Apart from that, it's good practice to also add the following line to your
`robots.txt` file:

```
Sitemap: http://example.org/sitemap.xml
```

Obviously, you should replace 'example.org' with the domain name of your
website.

This extension adds a 'route' for `/sitemap.xml` and `/sitemap` by default, but
it has lower priority than user defined routes.

If you use the `pagebinding` in `routing.yml`, or anything similar route that
would match 'sitemap' first, you will need to add the following _above_ that
route. You should also do this if you have an extension that might override the
default routing, like the AnimalDesign/bolt-translate extension.

```yaml
sitemap:
  path: /sitemap
  defaults: { _controller: sitemap.controller:sitemap }

sitemapXml:
  path: /sitemap.xml
  defaults: { _controller: sitemap.controller:sitemapXml }
```

Note, if you have a ContentType with the property `searchable: false`, that
content type will be ignored.

## Advanced links list control

If you have your own bundled extension you can add, remove or change links
before the sitemap is rendered. You need to subscribe to the 
`SitemapEvents::AFTER_COLLECTING_LINKS` event. The object you will get is
an instance of `SitemapEvent` class which has a `getLinks` method that returns 
a `MutableBag` object. The last one is an array-like list of links. See example:

```php
protected function subscribe($dispatcher)
{
    $dispatcher->addListener(SitemapEvents::AFTER_COLLECTING_LINKS,
        function ($event) {
            /** @var SitemapEvent $event */
            $links = $event->getLinks();
            $links->add([
                'link'  => '/lorem-ipsum',
                'title' => 'Hello World!',
                'depth' => 1,
            ]);
        }
    );
}
```

## Sitemap stylesheets

You can customize the sitemap with an xslt stylesheet if you copy the `templates/sitemap_xml.twig`
file and the `web/sitemap.xsl` file to your theme directory and by adding the xsl-stylesheet declaration
after the xml declaration so the first two lines of the `themes/{yourthemename}/sitemap_xml.twig` look like:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="{{ paths.theme }}/sitemap.xsl"?>
```
