<?php

namespace FSi\Bundle\SyliusPayuBundle\Payum\Payu;

use Buzz\Client\ClientInterface;
use Buzz\Message\Request;
use Buzz\Message\Response;

class Api
{
    const PAYU_BASE_URL = 'https://gatewaylap.pagosonline.net/';

    const PAYU_SANDBOX_URL = 'https://gatewaylap.pagosonline.net/ppp-web-gateway';

    const PAYMENT_STATUS_OK = 'OK';

    const PAYMENT_STATE_NEW = '1';

    const PAYMENT_STATE_CANCELLED = '2';

    const PAYMENT_STATE_REJECTED = '3';

    const PAYMENT_STATE_STARTED = '4';

    const PAYMENT_STATE_PENDING = '5';

    const PAYMENT_STATE_RETURNED = '7';

    const PAYMENT_STATE_COMPLETED = '99';

    protected $options = array(
        'merchantid' => null,
        'apikey' => null,
        'charset' => 'UTF',
        'sandbox' => false,
    );

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @param ClientInterface $client
     * @param array $options
     */
    public function __construct(ClientInterface $client, array $options)
    {
        $this->options = array_replace($this->options, $options);
        $this->client = $client;
    }

    /**
     * @return string
     */
    public function getNewPaymentUrl()
    {
        return $this->getPayuUrl();
    }

    /**
     * @param array $details
     * @return array
     */
    public function prepareNewPaymentDetails(array $details)
    {
        $details = array_merge(
            $details,
            array(
                'usuarioId' => $this->options['usuarioId'],
                'firma' => $this->options['firma']
            )
        );

        return $details;
    }

    /**
     * @param array $notificationDetails
     * @throws \InvalidArgumentException
     */
    public function validatePaymentNotification(array $notificationDetails)
    {
        if (!array_key_exists('usuarioId', $notificationDetails) || !array_key_exists('session_id', $notificationDetails) ||
            !array_key_exists('signature', $notificationDetails) ) {
            throw new \InvalidArgumentException(
                "Missing one of usuarioId, session_id, or signature in payment notification"
            );
        }

        $notificationSignature = md5(
            $this->options['apikey'] .'~'.
            $this->options['merchantid'] .'~'.
            $notificationDetails['referenceCode'] .'~'.
            $notificationDetails['amount'] .'~'.
            $notificationDetails['currency']
        );

        if ($notificationSignature != $notificationDetails['signature']) {
            throw new \InvalidArgumentException(
                "Invalid payment notification signature"
            );
        }
    }

    public function getPaymentDetails($notificationDetails)
    {
        $notificationSignature = md5(
            $this->options['apikey'] .'~'.
            $this->options['merchantid'] .'~'.
            $notificationDetails['referenceCode'] .'~'.
            $notificationDetails['amount'] .'~'.
            $notificationDetails['currency']
        );

        $request = new Request(
            Request::METHOD_POST,
            $this->options['charset'],
            $this->getPayuUrl()
        );

        $request->setContent(
            sprintf(
                'usuarioId=%s&Api Key=%s&refVenta=%s&decripión=%s&valor=%s&moneda=%s&firma=%s',
                $notificationDetails['merchantid'],
                $notificationDetails['apikey'],
                $notificationDetails['referenceCode'],
                $notificationDetails['description'],
                $notificationDetails['amount'],
                $notificationDetails['currency'],
                $notificationSignature
            )
        );

        $response = new Response();
        $this->client->send($request, $response);

        if (!$response->isOk()) {
            throw new \RuntimeException("Can't finish /Payment/get request");
        }

        return $this->parsePaymentDetailsXML($response->getContent());
    }

    public function validatePaymentDetails($paymentDetails)
    {
        $paymentSignature = md5(
            $this->options['apikey'] .'~'.
            $this->options['merchantid'] .'~'.
            $paymentDetails['referenceCode'] .'~'.
            $paymentDetails['amount'] .'~'.
            $paymentDetails['currency']
        );

        if ($paymentSignature != $paymentDetails['signature']) {
            throw new \RuntimeException("Invalid payment signature");
        }
    }

    /**
     * @param $xml
     * @throws \RuntimeException
     * @return array
     */
    protected function parsePaymentDetailsXML($xml)
    {
        $paymentDetails = array();
        $xmlData = simplexml_load_string($xml);
        if ((string)$xmlData->status != self::PAYMENT_STATUS_OK) {
            throw new \RuntimeException(
                sprintf(
                    'Invalid payment details status response. Error code: %d',
                    (int)$xmlData->error->nr
                )
            );
        }

        foreach ((array) $xmlData->trans as $key => $value) {
            $paymentDetails[$key] = (string) $value;
        }

        return $paymentDetails;
    }

    protected function getPayuUrl()
    {
        return ($this->options['sandbox'])
            ? self::PAYU_SANDBOX_URL
            : self::PAYU_BASE_URL;
    }
}