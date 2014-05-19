<?php

/*
* This file is part of the Sylius package.
*
* (c) Paweł Jędrzejewski
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace FSi\Bundle\SyliusPayuBundle\Payum\Payu\Action;

use FSi\Bundle\SyliusPayuBundle\Payum\Payu\Api;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Request\PostRedirectUrlInteractiveRequest;
use Payum\Core\Request\SecuredCaptureRequest;
use Sylius\Bundle\CoreBundle\Model\OrderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

class CaptureAction implements ActionInterface, ApiAwareInterface
{    
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var Request
     */
    protected $httpRequest;

    function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setApi($api)
    {
        if (false == $api instanceof Api) {
            throw new UnsupportedApiException('Not supported api type.');
        }

        $this->api = $api;
    }
    
    /**
     * Define the Symfony Request
     * 
     * @param Request $request
     */
    public function setRequest(Request $request = null)
    {
        $this->httpRequest = $request;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($request)
    {
        /** @var $request SecuredCaptureRequest */
        if (!$this->supports($request)) {
            throw RequestNotSupportedException::createActionNotSupported($this, $request);
        }

        if (!$this->httpRequest) {
            throw new LogicException('The action can be run only when http request is set.');
        }

        /** @var OrderInterface $order */
        $order = $request->getModel();
        $payment = $order->getPayment();
        
        if ($order->getCurrency() != 'COP') {
            throw new \InvalidArgumentException(
                sprintf("Currency %s is not supported in PayU payments", $order->getCurrency())
            );
        }
              
        $details = array(
            'session_id' => $this->httpRequest->getSession()->getId() . time(),
            'amount' => $order->getTotal(),
            'description' => sprintf(
                'Zamówienie %d przedmiotów na kwotę %01.2f',
                $order->getItems()->count(),
                $order->getTotal() / 100
            ),
            'referenceCode' => $order->getId(),
            'currency' => $order->getCurrency(),
            'first_name' => $order->getBillingAddress()->getFirstName(),
            'last_name' => $order->getBillingAddress()->getLastName(),
            'email' => $order->getUser()->getEmail(),
            'client_ip' => $this->httpRequest->getClientIp()
        );
        $payment->setDetails($details);       

        try {
            $request->setModel($payment);
            $this->httpRequest->getSession()->set('payum_token', $request->getToken()->getHash());

            $this->payment->execute($request);

            $request->setModel($order);
        } catch (\Exception $e) {
            $request->setModel($order);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof SecuredCaptureRequest &&
            $request->getModel() instanceof OrderInterface
        ;
    }
}