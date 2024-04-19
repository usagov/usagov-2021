# Redirects

USAGov hosts a lot of redirects. These fall in to three general classes:

- Internal redirects (from one path on the site to another) created within the CMS, using the _Redirect_ module
- Internal redirects (from one path on the site to another) implemented in nginx configuration
- "Domain" redirects - from a fully qualified domain and path to a path on USAGov

## Internal redirects using the _Redirect_ module

Content editors can add or edit redirects from one path to another within the Drupal CMS. The interface is at /admin/config/search/redirect. The main advantage of this system is that content editors can manage it directly.

During static site generation (tome), a small HTML file with a `<meta http-equiv="refresh" ...>` header is generated at the "from" path, directing the client to load the "to" path.

This module also automatically creates a redirect when the path alias for a node is changed.

## Internal redirects implemented in nginx configuration

In the long term, nginx is probably a better home for redirects that aren't expected to change. It's also easier to add a batch of redirects to the nginx configuration, as we did during cutover from the previous USAGov site.

These redirects are all defined in the [www app's internal_redirects.conf](../.docker/src-www/etc/nginx/partials/internal_redirects.conf) file. Note that the `rewrite` directive used here takes a regular expression, so most patterns should have start (`^`) and end (`$`) delimiters. The nginx documentation for these is at https://nginx.org/en/docs/http/ngx_http_rewrite_module.html.

These redirects work identically on the dev, stage, and prod static sites. A copy of this file resides within the [cms app's nginx configuration](../.docker/src-cms/etc/nginx/partials/internal_redirects.conf), so that they can also be tested on the CMS sites, including local dev.

## "Domain" redirects

USAGov hosts content that used to exist on several other domains, or is otherwise the best destination for users following links to some domains. In some cases all traffic for a domain is directed to a single page on the USAGov site, in some cases, the home page. For other domains, we direct different request paths to different destinations.

There are three parts to the redirect setup for each of these domains:

1. DNS: Each of these domains has a DNS `CNAME` or `ALIAS` record pointing to USA.gov.
1. cloud.gov domain and route configuration.
1. nginx configuration

### DNS

DNS records must include the _acme-challenge records documented in [cloud.gov's External domain service documentation](https://cloud.gov/docs/services/external-domain-service/#how-to-create-an-instance-of-this-service).

We are in the process of moving our DNS into [TTS DNS configuration](https://github.com/18F/dns).

### cloud.gov domain and route configuration

For each external domain, create a `domain` (these are at the cloud.gov `organization` level):

```
cf create-domain gsa-tts-usagov $domain
```

Then, in the `prod` space, map a route for that domain to the `www` app and bind the route service to `waf-route-prod-usagov`:

```
cf map-route www $domain
cf bind-route-service $domain waf-route-prod-usagov
```

Finally, create the cloud.gov external domain service. This command relies on the _acme-challenge DNS records to generate LetsEncrypt certificates for the domain.

For an apex domain (only one `.`, like `businessusa.gov`), you will need to create an external domain service with the `domain-with-cdn` plan. For non-apex domains, the `domain` plan will suffice.

The requirement to use `domain-with-cdn` for apex domains is due to the need to point the domain to another named service and should be explained in the DNS section, or in a document yet to be written.

```
cf create-service external-domain $plan redirect-domain-${domain} -c '{"domains": "$domain"}'
```

An example script for setting up a batch of domains exists at [bin/cloudgov/domains-for-redirects/create-domains](../bin/cloudgov/domains-for-redirects/create-domains).


### Nginx configuration

The nginx configuration for domain redirects is in the `waf` app. The WAF's nginx server handles the redirect directly.



The [waf's nginx default.conf](../.docker/src-waf/etc/nginx/conf.d/default.conf) file sets `$cf_forwarded_host` to the value of host and then includes `domain_redirects.conf`:

```
proxy_set_header x-usa-forwarded-host "$cf_forwarded_host";

# domain-redirects will potentially set $port to 8883-6
include /etc/nginx/snippets/domain-redirects.conf;
```

[domain_redirects.conf](../.docker/src-waf/etc/nginx/snippets/domain-redirects.conf) will immediately redirect and end the response for many domains by returning 301, for example:

```
if ($cf_forwarded_host ~* ^publications\.usa\.gov$) {
  return 301 https://connect.usa.gov/publications;
}
```

(In that example, we're not even directing to USA.gov!)

If we're serving path-specific redirects for a domain, it will instead set the `$port` variable to an appropriate value:

```
if ($cf_forwarded_host ~* ^benefits-tool\.usa\.gov$) {
  set $port 8886;
  break;
}
```

Nginx then returns to processing the directives in [default.conf](../.docker/src-waf/etc/nginx/conf.d/default.conf), which will construct a new URI using the `$port` and `$cf_request_uri` value (the request path), and proxy the request to a server on that port. Servers by port are defined further down in the same default.conf file -- there is one server block for the static site, one for the cms, and one for each domain we're handling with path-based redirects.

Finally, each of the server blocks for a "redirect domain" includes a file that contains rewrite rules for that domain, for example:

```
server {
  # benefits-tool redirects
  server_name 127.0.0.1;
  listen 8886;

  location / {
   # Note that the forwarded_host WILL match if we got to this port in the expected way.
     if ($http_x_usa_forwarded_host ~* ^benefits-tool\.usa\.gov$) {
        include /etc/nginx/snippets/domain-rewrites-benefits-tool.conf;
      }
     return 301 https://www.usa.gov;
  }
}
```

These domain-specific rewrite files are very similar to the [Internal redirects](#internal-redirects-implemented-in-nginx-configuration) described above.

Example: [domain-rewrites-benefits-tool.conf](../.docker/src-waf/etc/nginx/snippets/domain-rewrites-benefits-tool.conf)