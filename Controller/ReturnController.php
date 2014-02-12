<?php
namespace FSi\Bundle\SyliusPayuBundle\Controller;

use Payum\Bundle\PayumBundle\Controller\PayumController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReturnController extends PayumController
{
    public function doSessionTokenAction(Request $request)
    {
        if (false == $request->getSession()) {
            throw new HttpException(400, 'This controller requires session to be started.');
        }

        if (false == $hash = $request->getSession()->get('payum_token')) {
            throw new HttpException(400, 'This controller requires token hash to be stored in the session.');
        }

        $request->getSession()->remove('payum_token');

        return $this->redirect($this->generateUrl('sylius_payu_capture_do', array_replace(
            $request->query->all(),
            array(
                'payum_token' => $hash,
            )
        )));
    }

    public function doAction(Request $request)
    {
        $token = $this->getHttpRequestVerifier()->verify($request);

        return $this->redirect($token->getAfterUrl());
    }
}