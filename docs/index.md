# omnibus/amazon

Amazon Shipping for [omnisense/omnibus](https://github.com/glitchr-omnisense/omnibus): rates,
purchased shipments with their labels, tracking and cancellation, through the Selling Partner API's
Shipping v2 on a Login with Amazon (LWA) access token. The gateway calls:

| Operation | Request |
|---|---|
| token | `POST https://api.amazon.com/auth/o2/token` |
| rate | `POST /shipping/v2/shipments/rates` |
| ship | `POST /shipping/v2/shipments/rates`, then `POST /shipping/v2/shipments` |
| track | `GET /shipping/v2/tracking?trackingId={number}&carrierId={carrier}` |
| cancel | `PUT /shipping/v2/shipments/{shipmentId}/cancel` |

on the region's endpoint: `https://sellingpartnerapi-eu.amazon.com`, `-na` or `-fe` (`region`),
or its sandbox `https://sandbox.sellingpartnerapi-<region>.amazon.com` (`sandbox: true`). Every
call has a 20-second timeout.

## Installation

```sh
composer require omnibus/amazon
```

Requires PHP 8.2, `omnisense/omnibus` (2.x) and `symfony/http-client` (6.4, 7 or 8); given no
HTTP client, the factory makes one with `HttpClient::create()`. In a Symfony application with
`Omnibus\Bridge\Symfony\OmnibusBundle`, the factory is registered as soon as the package is
installed.

## Credentials

- An SP-API application (Seller Central > Develop Apps, with the Shipping role): its LWA **client
  id** and **client secret**.
- Authorising the application on the Amazon Shipping account gives the **refresh token**.
- The shipping **business id** of the account (`AmazonShipping_UK`, `_US`, `_IN`, `_IT`, `_ES`,
  `_FR`, `_SA`, `_AE`, `_EG`, `_JP`...), sent as `x-amzn-shipping-business-id`.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `client_id` | string | - | yes | the LWA client id |
| `client_secret` | string | - | yes | the LWA client secret |
| `refresh_token` | string | - | yes | the authorised account's refresh token |
| `region` | string | `eu` | no | `eu`, `na` or `fe`; another value is `eu` |
| `business_id` | ?string | `null` | no | the shipping business; not sent when unset |
| `carrier_id` | string | `AMZN_UK` | no | the carrier a bare tracking number is tracked with; a label's `carrierReference` names the carrier of the rate bought |
| `sandbox` | bool | `false` | no | `true`: the region's SP-API sandbox |
| `rates` | list | - | no | prices from configuration instead of getRates ([the rates option](https://github.com/glitchr-omnisense/omnibus/blob/2.x/docs/configuration.md#the-rates-option)): with it, getRates is not called for rating |

Shipment options (`Shipment::$options`):

| Option | Read by | Default | |
|---|---|---|---|
| `description` | rate, ship | `Merchandise` | each package's item description |
| `label_format` | ship | `PDF` | `PDF` or `ZPL` |

## Capabilities

| Request | Supported |
|---|---|
| `Rating` | yes (getRates), or configured rates with `rates` |
| `Shipping` | yes (getRates then purchaseShipment) |
| `Tracking` | yes |
| `Pickup` | no: Amazon Shipping delivers to the door |
| `GetSlip` | no |
| `Cancel` | yes |
| `Paperless` | no |

## Behaviour

**Token.** On the first call, the refresh token is exchanged at LWA (form `POST`,
`grant_type=refresh_token`) for an access token, kept until 60 seconds before it expires
(`expires_in`, 3600 seconds when LWA does not say). Every call sends it as `x-amz-access-token`.
An answer's `payload` (else the answer) is read.

**Addresses and packages.** An address is its name (50 characters), company, two lines (60
characters), city, postcode, country, email and phone; no state is sent. Each parcel is a package:
its dimensions in centimetres (a missing one counts as 10), its weight in kilograms with three
decimals (at least 0.01), its `value` as insured value and as the value of its one item (with the
`description` and the same weight), and a client reference `<reference>-<n>` (`omnibus-<n>` without
a reference). The ship date is `Shipment::$shippingDate` (else now), written `Y-m-d\TH:i:s` followed
by `Z` without converting it to UTC. The channel is `EXTERNAL`.

> [!WARNING]
> Known defect: the ship date is suffixed `Z` without being converted to UTC, where Amazon reads it as UTC: outside UTC the date sent is off by the offset.

**Rate.** getRates for the shipment: every rate Amazon offers. The service is its `serviceId`
(else its `rateId`), the label its `serviceName`, the amount its `totalCharge` in that charge's
currency (`EUR` when none), `days` the whole days from the ship date to the end of the promised
delivery window. Rates come cheapest first; `toPickupPoint` is `false`.

**Ship.** getRates again, the rates sorted cheapest first, and the first whose `serviceId` or
`rateId` is `Shipment::$service` is chosen (the cheapest when the shipment names no service);
none matching is a `CarrierException` (`Amazon Shipping offers no "<service>" for this
shipment.`, or `... offers no rate ...`). It is purchased with the quote's `requestToken`: a 4 x 6
inch label at 300 dpi, `PDF` or `ZPL`. The tracking number is the first package's `trackingId`,
else the `shipmentId`; the label the first package document's `contents`, base64-decoded,
`application/pdf` or `application/zpl`. `trackingUrl` is `https://track.amazon.com/tracking/<number>`.
The label's `carrierReference` keeps what tracking and cancellation need and the tracking id
does not give: the `shipmentId`, the `carrierId` of the rate bought (`AMZN_UK`, `AMZN_FR`,
`AMZN_INTL` across borders, `USPS`...) and the tracking id, as
`shipmentId=...&carrierId=...&trackingId=...`. Keep it with the order and pass it to `track()` and
`cancel()`. A purchase without either id is a `CarrierException` (`Amazon Shipping purchased no
shipment.`). `pickupPoint` is not sent.

**Track.** `GET /tracking` with the tracking id and the carrier id: given the label's
`carrierReference`, the carrier of the rate bought; given a bare tracking id, the `carrier_id`
option (`AMZN_UK` by default, Amazon's default business being `AmazonShipping_UK`). The locale is
not sent; the tracking's `trackingNumber` is the tracking id. Each event of `eventHistory`: its `eventTime`, its
`eventCode` as both description and `code` (Amazon's event has no text here), `city countryCode`
as location; sorted oldest first. The status is the summary's `status`, else the latest event's
code:

| Amazon code | `TrackingStatus` |
|---|---|
| `DELIVERED` | `DELIVERED` |
| `OUT_FOR_DELIVERY` | `OUT_FOR_DELIVERY` |
| `AVAILABLE_FOR_PICKUP`, `READY_FOR_PICKUP` | `AVAILABLE_FOR_PICKUP` |
| `RETURNING`, `RETURNED`, `RETURN_TO_SENDER` | `RETURNED` |
| `UNDELIVERABLE`, `DELIVERY_ATTEMPTED`, `LOST`, `DAMAGED`, `EXCEPTION` | `EXCEPTION` |
| `PRE_TRANSIT`, `CREATED`, `LABEL_CREATED`, `READY_FOR_RECEIVE` | `PENDING` |
| `IN_TRANSIT`, `RECEIVED`, `PICKED_UP`, `ARRIVED`, `DEPARTED` | `IN_TRANSIT` |
| another code | `IN_TRANSIT` |
| none | `UNKNOWN` |

**Cancel.** `PUT /shipments/{shipmentId}/cancel` with the shipment id of the label's
`carrierReference`; a bare value is taken as the shipment id itself (Amazon finds no shipment by
its tracking id): `true` when it does not fail.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnibus\Amazon\AmazonGatewayFactory;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Symfony\Component\HttpClient\HttpClient;

$amazon = (new AmazonGatewayFactory(HttpClient::create()))->create([
    'client_id' => getenv('AMAZON_LWA_CLIENT_ID'),
    'client_secret' => getenv('AMAZON_LWA_CLIENT_SECRET'),
    'refresh_token' => getenv('AMAZON_REFRESH_TOKEN'),
    'region' => 'eu',
    'business_id' => 'AmazonShipping_UK',
    'sandbox' => true,
]);

$shipment = new Shipment(
    new Address('Glitch Art', ['1 Trafalgar Square'], 'WC2N 5DN', 'London', 'GB', phone: '+442071234567'),
    new Address('Ada Lovelace', ['10 Downing Street'], 'SW1A 2AA', 'London', 'GB', email: 'ada@example.org'),
    [new Parcel(1200, 30, 20, 10, value: 4500, currency: 'GBP')],
    reference: 'CMD-1042',
    options: ['description' => 'Prints', 'label_format' => 'PDF'],
);

foreach ($amazon->rate($shipment) as $rate) {
    printf("%-8s %-24s %6.2f %s, %s days\n", $rate->service, $rate->label, $rate->amount / 100, $rate->currency, $rate->days ?? '?');
}

$label = $amazon->ship($shipment);   // no service: the cheapest rate is purchased
file_put_contents($label->trackingNumber.'.pdf', (string) $label->content);
echo $label->trackingNumber, ' ', $label->trackingUrl, "\n";

$tracking = $amazon->track($label->carrierReference);   // the carrier of the rate bought
echo $tracking->status->value, ' ', $tracking->latest()?->location, "\n";

var_dump($amazon->cancel($label->carrierReference));    // by its shipment id
```

In Symfony:

```yaml
omnibus:
    gateways:
        amazon:
            factory: amazon
            options:
                client_id: '%env(AMAZON_LWA_CLIENT_ID)%'
                client_secret: '%env(AMAZON_LWA_CLIENT_SECRET)%'
                refresh_token: '%env(AMAZON_REFRESH_TOKEN)%'
                region: eu
                business_id: AmazonShipping_UK
                sandbox: '%env(bool:AMAZON_SANDBOX)%'
```

## Errors

Every failure is an `Omnibus\Exception\CarrierException` whose `carrier` is `amazon`:

- an HTTP status of 400 or more, or `errors` in the answer: the first error's `message` (else
  `HTTP <status>`), its `code` in `carrierCode`;
- a body that is not JSON: `Amazon Shipping answered HTTP <status> with a body that is not
  JSON.`; a network error: `Amazon Shipping request failed: ...`;
- no token: `Login with Amazon gave no token: ...`, LWA's `error_description`, or `Login with
  Amazon gave no token: check the client id, secret and refresh token.`;
- when booking: no rate for the service asked, or `Amazon Shipping purchased no shipment.`

A missing `client_id`, `client_secret` or `refresh_token` is an `InvalidConfigException` when the
gateway is built; `Pickup`, `GetSlip` and `Paperless` raise `RequestNotSupportedException` before
any call.

## Verified, and not

Tested offline: `Tests/AmazonGatewayTest.php` (rates cheapest first with their delivery window,
the sandbox host, the access token and business id headers, kilograms; the chosen rate purchased
with its request token and its label, the cheapest one without a service; tracking with its
statuses and location; the label's `carrierReference` tracking with the carrier of the rate bought
and cancelling by the shipment id; a bare tracking number tracked with `carrier_id`; the
cancellation by shipment id) and `Tests/AmazonContractTest.php`, the
family's conformance suite on recorded answers. The example above was run in the core's harness on
those recorded answers.

Not verified: the README says the package is built from Amazon's published SP-API documentation
and was not yet run against the sandbox; that needs the credentials above. Unchecked against
Amazon: the ship date carries a `Z` without being converted to UTC; tracking and cancelling through
the label's `carrierReference` follow the Shipping API v2 model (getTracking's `carrierId` "returned
by the getRates operation for the selected rate", cancelShipment's `shipmentId` "returned by the
purchaseShipment operation"), not yet a real purchase.

## Responsibility

An independent, unofficial client, not affiliated with Amazon (Amazon.com, Inc. and its
affiliates). Whoever runs it complies with the Selling Partner API's terms ([SP-API policies and
agreements](https://developer-docs.amazon.com/sp-api/docs/policies-and-agreements)): see the
README's Responsibility section.
