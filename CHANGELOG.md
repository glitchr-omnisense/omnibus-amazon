# Changelog

## [Unreleased]

### Added
- On omnisense/contracts: the carrier's usage policy as data (`policy.json`, named in
  `composer.json`'s `extra.omnisense.policy`; docs/index.md, Usage policy), and its waits honoured:
  the API client takes the gateway's Backoff, a 429 is a `RateLimitedException`, the next calls are
  held unsent, nothing is retried. Requires omnisense/contracts. `phpstan.neon` at level max,
  `phpstan-baseline.neon` what was there.
- A purchase carries `x-amzn-IdempotencyKey` from the shipment's `idempotency_key` option;
  `Api::call()` takes more headers (optional).
- Option `carrier_id` (default `AMZN_UK`): the carrier a bare tracking number is tracked with.

### Fixed
- A `CarrierException` no longer carries the client secret, the refresh token and the access token.
  The message quoted the HTTP client's (the whole URL), the previous was the client's own exception,
  and every trace of the chain kept the arguments of the calls under it (the request's options, the
  `Api` with its credentials) when `zend.exception_ignore_args` is off: a log of the whole chain
  wrote them. The message masks them as `***`, the previous is a copy of the client's exception (the
  same class and code), and no exception `Api` throws keeps its trace's arguments
  (`Omnibus\Http\Redaction`).
- Tracking asks with the carrier of the rate bought, not always `AMZN_UK`: Amazon's getTracking
  takes the `carrierId` of the selected rate (`AMZN_FR`, `AMZN_INTL` across borders, `USPS`...),
  which the label now keeps in its `carrierReference` (omnisense/omnibus's `Label::$carrierReference`).
  A bare tracking number is tracked with the `carrier_id` option.
- `cancel()` reaches the shipment `ship()` bought: Amazon cancels by its `shipmentId`, which the
  label did not keep (it carries the package's tracking id). The label's `carrierReference` keeps
  it, and `cancel()` takes that reference; a bare value is still taken as the shipment id.
- The ship date is sent in UTC: it was written with a `Z` without being converted, so a ship date
  outside UTC was off by its offset (10:00 in Paris went as 10:00Z, two hours late in summer).
