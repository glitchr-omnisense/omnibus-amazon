# Changelog

## [Unreleased]

### Added
- Option `carrier_id` (default `AMZN_UK`): the carrier a bare tracking number is tracked with.

### Fixed
- Tracking asks with the carrier of the rate bought, not always `AMZN_UK`: Amazon's getTracking
  takes the `carrierId` of the selected rate (`AMZN_FR`, `AMZN_INTL` across borders, `USPS`...),
  which the label now keeps in its `carrierReference` (omnisense/omnibus's `Label::$carrierReference`).
  A bare tracking number is tracked with the `carrier_id` option.
- `cancel()` reaches the shipment `ship()` bought: Amazon cancels by its `shipmentId`, which the
  label did not keep (it carries the package's tracking id). The label's `carrierReference` keeps
  it, and `cancel()` takes that reference; a bare value is still taken as the shipment id.
