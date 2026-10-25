<?php

namespace Omnibus\Amazon\Tests;

use Omnibus\GatewayFactoryInterface;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\Amazon\AmazonGatewayFactory;
use Omnibus\Testing\GatewayContractTests;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** The conformance suite on Amazon Shipping's recorded answers (SP-API sandbox, Login with Amazon). */
final class AmazonContractTest extends TestCase
{
    use GatewayContractTests;

    protected function gatewayFactory(HttpClientInterface $http): GatewayFactoryInterface
    {
        return new AmazonGatewayFactory($http);
    }

    protected function gatewayOptions(): array
    {
        return ['client_id' => 'amzn1.application-oa2-client.x', 'client_secret' => 'secret', 'refresh_token' => 'Atzr|refresh', 'region' => 'eu', 'business_id' => 'AmazonShipping_UK', 'sandbox' => true];
    }

    protected function sessionRoutes(): array
    {
        return ['api.amazon.com/auth'];
    }

    protected function contractShipment(): Shipment
    {
        return new Shipment(new Address('Glitch Art', ['1 Rue du Test'], '67000', 'Strasbourg', 'FR'), new Address('Alex Martin', ['10 Downing Street'], 'SW1A 2AA', 'London', 'GB', email: 'alex@example.org'), [new Parcel(850, 30, 20, 10, 2500, 'GBP')], reference: 'ORDER-1042');
    }

    protected function contractTrackingNumber(): string
    {
        return 'TBA123456789000';
    }

    protected function recordedAnswers(): array
    {
        return [
            'api.amazon.com/auth' => ['access_token' => 'Atza|tok', 'expires_in' => 3600],
            '/shipping/v2/shipments/rates' => ['payload' => ['requestToken' => 'rt-1', 'rates' => [
                ['rateId' => 'r-std', 'serviceId' => 'std-uk', 'serviceName' => 'Amazon Shipping Standard', 'totalCharge' => ['value' => 4.2, 'unit' => 'GBP'], 'promise' => ['deliveryWindow' => ['start' => '2026-10-03T08:00:00Z', 'end' => '2026-10-05T20:00:00Z']]],
                ['rateId' => 'r-exp', 'serviceId' => 'exp-uk', 'serviceName' => 'Amazon Shipping Express', 'totalCharge' => ['value' => 7.9, 'unit' => 'GBP']],
            ]]],
            '/cancel' => ['payload' => new \stdClass()],
            '/shipping/v2/tracking' => ['payload' => ['trackingId' => 'TBA123456789000', 'summary' => ['status' => 'DELIVERED'], 'eventHistory' => [['eventCode' => 'DELIVERED', 'eventTime' => '2026-10-02T11:30:00Z', 'location' => ['city' => 'London', 'countryCode' => 'GB']], ['eventCode' => 'IN_TRANSIT', 'eventTime' => '2026-10-01T17:00:00Z', 'location' => ['city' => 'Coventry', 'countryCode' => 'GB']]]]],
            '/shipping/v2/shipments' => ['payload' => ['shipmentId' => 'sh-1', 'packageDocumentDetails' => [['trackingId' => 'TBA123456789000', 'packageDocuments' => [['type' => 'LABEL', 'format' => 'PDF', 'contents' => base64_encode('%PDF-1.4 amz')]]]]]],
        ];
    }
}
