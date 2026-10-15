# omnibus/amazon

Amazon Shipping for [glitchr/omnibus](https://github.com/glitchr-studio/omnibus): rates,
purchased shipments with their labels, tracking and cancellation - the Selling Partner API's
Shipping v2, with Login with Amazon.

```php
$gateway = (new AmazonGatewayFactory($http))->create($options);   // $http: the application's HTTP client - none given, the factory makes its own; the options below
```

No framework needed: the package requires `glitchr/omnibus` and `symfony/http-client`. In a
Symfony application, the same through the bundle's configuration:

```yaml
omnibus:
    gateways:
        amazon:
            factory: amazon
            options:
                client_id: '%env(AMAZON_LWA_CLIENT_ID)%'
                client_secret: '%env(AMAZON_LWA_CLIENT_SECRET)%'
                refresh_token: '%env(AMAZON_REFRESH_TOKEN)%'
                region: eu                               # eu, na, fe
                business_id: AmazonShipping_UK           # the shipping business
                sandbox: true
                rates: [...]                             # optional: configured prices instead of getRates
```

The service is Amazon's serviceId as rates return it (none: the cheapest rate is purchased).
Shipment options: `label_format` (PDF, ZPL), `description`. No pickup points.

Credentials: an SP-API application (Seller Central > Develop Apps, the Shipping role) gives the
LWA client id and secret; authorising it on the Amazon Shipping account gives the refresh token.

Built from Amazon's published SP-API documentation and tested on recorded answers; not yet run
against the sandbox: that needs the credentials above.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Amazon (Amazon.com, Inc. and its affiliates). It reaches their service on behalf of
whoever runs it, and it does not redistribute their data beyond the short excerpts recorded as test
fixtures. Its MIT licence covers this package's code only and grants no right to the service or to
its data.

Whoever installs and runs this package is responsible for complying with the Selling Partner API's
terms ([SP-API policies and
agreements](https://developer-docs.amazon.com/sp-api/docs/policies-and-agreements)), for obtaining
whatever they require (an account, a key, a licence or subscription, an attribution or a citation, a
rate limit to respect), and for any use made of what it returns. The authors publish it free of
charge, make no commercial use of it, and accept no liability for how it is used (see
[LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
