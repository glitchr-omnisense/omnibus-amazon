# omnibus/amazon

Amazon Shipping for [omnisense/omnibus](https://github.com/glitchr-omnisense/omnibus): rates,
purchased shipments with their labels, tracking and cancellation - the Selling Partner API's
Shipping v2, with Login with Amazon.

## Install and a first call

```sh
composer require omnibus/amazon
```

No account for this first call: Amazon Shipping's answer is recorded
(`Omnibus\Testing\RecordedHttpClient` answers in place of the API), so nothing leaves the process.
With your own credentials, give the factory your HTTP client (or none: it makes its own) and drop
the recorded one.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnibus\Amazon\AmazonGatewayFactory;
use Omnibus\Testing\RecordedHttpClient;

// Amazon Shipping's answer, recorded: no account, and nothing leaves the process.
$http = new RecordedHttpClient([
    'api.amazon.com/auth' => ['access_token' => 'Atza|tok', 'expires_in' => 3600],
    '/shipping/v2/tracking' => ['payload' => ['trackingId' => 'TBA123456789000', 'summary' => ['status' => 'DELIVERED'], 'eventHistory' => [
        ['eventCode' => 'DELIVERED', 'eventTime' => '2026-10-02T11:30:00Z', 'location' => ['city' => 'London', 'countryCode' => 'GB']],
    ]]],
]);
$gateway = (new AmazonGatewayFactory($http))->create(['client_id' => 'amzn1.application-oa2-client.x', 'client_secret' => 'secret', 'refresh_token' => 'Atzr|refresh', 'region' => 'eu', 'business_id' => 'AmazonShipping_UK', 'sandbox' => true]);

$tracking = $gateway->track('TBA123456789000');
echo $tracking->status->value, ' - ', $tracking->latest()?->description, "\n";   // delivered - DELIVERED
```

A real call needs a Amazon Shipping account (the credentials below). The answer is shaped after
Amazon Shipping's published documentation; the package has not been run against its service yet.

## Configuration

```php
$gateway = (new AmazonGatewayFactory($http))->create($options);   // $http: the application's HTTP client - none given, the factory makes its own; the options below
```

No framework needed: the package requires `omnisense/omnibus` and `symfony/http-client`. In a
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
                carrier_id: AMZN_UK                      # the carrier a bare tracking number is tracked with
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
