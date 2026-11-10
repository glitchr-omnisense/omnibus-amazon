<?php

namespace Omnibus\Amazon\Tests;

use Omnibus\Amazon\AmazonGatewayFactory;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\Testing\RecordedHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * A label's purchase carries x-amzn-IdempotencyKey when the shipment gives one (its
 * "idempotency_key" option): purchased again after a failure, Amazon buys one label. Without
 * the option no key is sent - none is safe to invent - and the rating request never carries one.
 */
final class AmazonIdempotencyTest extends TestCase
{
    public function testThePurchaseCarriesTheShipmentsIdempotencyKey(): void
    {
        $http = $this->http();
        $this->gateway($http)->ship($this->shipment(['idempotency_key' => 'ORDER-1042-attempt-1']));

        self::assertSame([null, 'ORDER-1042-attempt-1'], $this->keys($http), 'the rating carries none, the purchase the shipment\'s');
    }

    public function testNoKeyIsInventedWithoutTheOption(): void
    {
        $http = $this->http();
        $this->gateway($http)->ship($this->shipment());

        self::assertSame([null, null], $this->keys($http));
    }

    private function http(): RecordedHttpClient
    {
        return new RecordedHttpClient([
            'api.amazon.com/auth' => ['access_token' => 'Atza|tok', 'expires_in' => 3600],
            '/shipping/v2/shipments/rates' => ['payload' => ['requestToken' => 'rt-1', 'rates' => [['rateId' => 'r-std', 'serviceId' => 'std-uk', 'totalCharge' => ['value' => 4.2, 'unit' => 'GBP']]]]],
            'POST /shipping/v2/shipments' => ['payload' => ['shipmentId' => 'sh-1', 'packageDocumentDetails' => [['trackingId' => 'TBA123456789000', 'packageDocuments' => [['type' => 'LABEL', 'format' => 'PDF', 'contents' => base64_encode('%PDF-1.4 amz')]]]]]],
        ]);
    }

    private function gateway(RecordedHttpClient $http): \Omnibus\GatewayInterface
    {
        return (new AmazonGatewayFactory($http))->create(['client_id' => 'amzn1.application-oa2-client.x', 'client_secret' => 'secret', 'refresh_token' => 'Atzr|refresh', 'region' => 'eu', 'sandbox' => true]);
    }

    /** @param array<string, mixed> $options */
    private function shipment(array $options = []): Shipment
    {
        return new Shipment(new Address('Glitch Art', ['1 Rue du Test'], '67000', 'Strasbourg', 'FR'), new Address('Alex Martin', ['10 Downing Street'], 'SW1A 2AA', 'London', 'GB'), [new Parcel(850)], reference: 'ORDER-1042', options: $options);
    }

    /** @return list<?string> the x-amzn-IdempotencyKey of each request to the Shipping API, in order */
    private function keys(RecordedHttpClient $http): array
    {
        $keys = [];
        foreach ($http->requests() as $request) {
            if (!str_contains($request['url'], '/shipping/v2/')) {
                continue;
            }
            $key = null;
            $headers = $request['options']['headers'] ?? [];
            // The HTTP client hands the headers on as "Name: value" lines.
            foreach (\is_array($headers) ? $headers : [] as $line) {
                if (\is_string($line) && 0 === stripos($line, 'x-amzn-IdempotencyKey:')) {
                    $key = trim(substr($line, \strlen('x-amzn-IdempotencyKey:')));
                }
            }
            $keys[] = $key;
        }

        return $keys;
    }
}
