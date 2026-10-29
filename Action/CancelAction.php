<?php

namespace Omnibus\Amazon\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Amazon\Api;
use Omnibus\Amazon\Mapping;
use Omnibus\Request\Cancel;
use Omnibus\Request\Request;

/**
 * PUT /shipping/v2/shipments/{shipmentId}/cancel: the shipment id from the label's
 * carrierReference; a bare value is taken as the shipment id itself (not a tracking id:
 * Amazon finds no shipment by it).
 */
final class CancelAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Cancel;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Cancel);
        $shipmentId = Mapping::parseReference($request->trackingNumber)['shipmentId'] ?? $request->trackingNumber;
        $this->api->call('PUT', '/shipping/v2/shipments/'.rawurlencode($shipmentId).'/cancel');
        $request->setResult(true);
    }
}
