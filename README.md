Sitemap
=======

This extension will automatically create XML sitemaps for your Bolt sites.
After enabling the extension, go to `http://example.org/sitemap.xml` to see it.

The bigger search-engines like Google and Bing will automatically pick up your
sitemap after a while, but it's always a good idea to explicitly tell the
search engines where to find it. To do so, this extension automatically adds
the link to the `<head>` section of your pages:

    <link rel="sitemap" type="application/xml" title="Sitemap" href="/sitemap.xml" />

Apart from that, it's good practice to also add the following line to your
`robots.txt` file:

    Sitemap: http://example.org/sitemap.xml

Obviously, you should replace 'example.org' with the domain name of your
website.

This extension adds a 'route' for `/sitemap.xml` and `/sitemap` by default, but
it has lower priority than user defined routes.

If you use the `pagebinding` in `routing.yml`, or anything similar route that
would match 'sitemap' first, you will need to add the following _above_ that
route. You should also do this if you have an extension that might override the
default routing, like the AnimalDesign/bolt-translate extension.

```
sitemap:
  path: /sitemap
  defaults: { _controller: sitemap.controller:sitemap }

sitemapXml:
  path: /sitemap.xml
  defaults: { _controller: sitemap.controller:sitemapXml }
```

Note, if you have a ContentType with the property `searchable: false`, that
content type will be ignored.
