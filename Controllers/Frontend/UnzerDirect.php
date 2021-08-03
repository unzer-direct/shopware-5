<?php

use Enlight_Components_Session_Namespace;
use UnzerDirectPayment\Components\UnzerDirectService;
use UnzerDirectPayment\Models\UnzerDirectPayment;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Components\Logger;
use Shopware\Models\Order\Status;

class Shopware_Controllers_Frontend_UnzerDirect extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    /**
     * Instance of the UnzerDirect service
     * 
     *  @var UnzerDirectService $service
     */
    private $service;
    
    /**
     * Instance of the Session
     * 
     * @var Enlight_Components_Session_Namespace
     */
    private $session;
    
    public function preDispatch()
    {
        parent::preDispatch();
        $this->service = $this->get('unzerdirect_payment.unzerdirect_service');
        $this->session = $this->get('session');
    }
    
    public function creditcardAction()
    {
        return $this->redirectToPayment('creditcard');
    }
    
    public function paypalAction()
    {
        return $this->redirectToPayment('paypal', false);
    }
    
    public function klarnaAction()
    {
        return $this->redirectToPayment('klarna-payments');
    }
    
    /**
     * Redirect to gateway
     */
    private function redirectToPayment($paymentMethods, $withBasket = true)
    {
        try {
            
            $this->log(Logger::DEBUG, 'redirect action called');
            
            //Get current payment id if it exists in the session
            $paymentId = $this->session->offsetGet('unzerdirect_payment_id');
            
            $amount = $this->getAmount() * 100; //Convert to cents
            
            $variables = array(
                'device' => $this->Request()->getDeviceType(),
                'comment' => $this->session->offsetGet('sComment'),
                'dispatchId' => $this->session->offsetGet('sDispatch')
            );

            $basket = $withBasket ? $this->getBasket() : null;
            
            if(empty($paymentId))
            {   
                //Create new payment
                $payment = $this->service->createPayment($this->session->offsetGet('sUserId'), $basket, $amount, $variables, $this->getCurrencyShortName());
                $this->log(Logger::DEBUG, 'new payment created in redirect', [
                    'id' => $payment->getId(),
                    'variables' => $variables,
                    'amount' => $amount
                ]);
            }
            else
            {
                //Get the payment associated with the payment id from the session
                $payment = $this->service->getPayment($paymentId);

                //Check if the payment is still in its initial state
                if($payment->getStatus() == UnzerDirectPayment::PAYMENT_CREATED)
                {
                    //Update existing UnzerDirect payment
                    $payment = $this->service->updatePayment($paymentId, $basket, $amount, $variables);
                    $this->log(Logger::DEBUG, 'payment updated in redirect', [
                        'id' => $payment->getId(),
                        'variables' => $variables,
                        'amount' => $amount
                    ]);
                }
                else
                {
                    //Create new payment
                    $payment = $this->service->createPayment($this->session->offsetGet('sUserId'), $this->getBasket(), $amount, $variables, $this->getCurrencyShortName());
                    $this->log(Logger::DEBUG, 'new payment created in redirect', [
                        'id' => $payment->getId(),
                        'variables' => $variables,
                        'amount' => $amount
                    ]);
                }
            }
            
            $signature = $payment->getBasketSignature();
            // Check if basket has previously been persisted
            if(!empty($signature))
            {
                //delete the previously persisted basket
                $persister = $this->get('basket_persister');
                $persister->delete($signature);
                $this->log(Logger::DEBUG, 'previous basket deleted in redirect', ['signature' => $signature]);

            }
            //persist the current basket
            $payment->setBasketSignature($this->persistBasket());
            $this->log(Logger::DEBUG, 'basket persisted in redirect', ['signature' => $payment->getBasketSignature()]);
            
            // Save ID to session
            $this->session->offsetSet('unzerdirect_payment_id', $payment->getId());
            
            $user = $this->getUser();
            $email = $user['additional']['user']['email'];

            //Create payment link
            $paymentLink = $this->service->createPaymentLink(
                $payment,
                $paymentMethods,
                $email,
                $this->getContinueUrl(),
                $this->getCancelUrl(),
                $this->getCallbackUrl()
            );
            
            $payment->setLink($paymentLink);
            Shopware()->Models()->flush($payment);
            
            $this->log(Logger::DEBUG, 'redirected', ['url' => $paymentLink]);
            
            //Redirect to the payment page
            $this->redirect($paymentLink);
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * Handle callback
     */
    public function callbackAction()
    {
        // Prevent error from missing template
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();

        //Validate & save order
        $requestBody = $this->Request()->getRawBody();
        $data = json_decode($requestBody);
        
        $this->log(Logger::DEBUG, 'callback action called', $data);

        //By default return error code
        $responseCode = 400;
        
        if ($data)
        {
            
            $payment = $this->service->getPayment($data->id);
            
            if($payment)
            {
                
                //Get private key & calculate checksum
                $key = Shopware()->Config()->getByNamespace('UnzerDirectPayment', 'private_key');
                $checksum = hash_hmac('sha256', $requestBody, $key);
                $submittedChecksum = $this->Request()->getServer('HTTP_QUICKPAY_CHECKSUM_SHA256');

                //Validate checksum
                if ($checksum === $submittedChecksum)
                {
                    
                    //Check if the test mode info matches the configured value
                    if ($this->checkTestMode($data))
                    {
                        
                        if(isset($data->variables))
                        {
                            $this->session->offsetSet('sDispatch', $data->variables->dispatchId);
                            $this->session->offsetSet('sComment', $data->variables->comment);
                        }
                        
                        $this->service->registerCallback($payment, $data);
                        $this->log(Logger::DEBUG, 'callback registered', $data);
                        
                        //Check if the payment was at least authorized
                        if($payment->getStatus() != UnzerDirectPayment::PAYMENT_CREATED)
                        {
                            $this->log(Logger::DEBUG, 'persisting order in callback', [
                                'payment' => $payment->getId()
                            ]);
                            //Make sure the order is persisted
                            $this->checkAndPersistOrder($payment);
                            $this->log(Logger::DEBUG, 'order peristed in callback', [
                                'payment' => $payment->getId()
                            ]);
                            
                            $this->updateOrderStatus($payment);
                            $this->log(Logger::DEBUG, 'updating order status in callback', [
                                'payment' => $payment->getId()
                            ]);
                        }
                        
                        $responseCode = 200;
                        
                    }
                    else
                    {
                        
                        //Wrong test mode settings were used
                        $this->service->registerTestModeViolationCallback($payment, $data);
                        if($data->test_mode)
                            $this->log(Logger::WARNING, 'payment with wrong test card attempted', json_decode($requestBody, true));
                        else
                            $this->log(Logger::WARNING, 'payment with real data during test mode', json_decode($requestBody, true));
                        
                    }
                }
                else
                {
                    $this->service->registerFalseChecksumCallback($payment, $data);
                    $this->log(Logger::WARNING, 'Checksum mismatch', json_decode($requestBody, true));
                }
            }
            else
            {
                $this->log(Logger::INFO, 'Unkown payment id', json_decode($requestBody, true));
            }
        }

        $this->Response()->setHttpResponseCode($responseCode);
    }

    /**
     * Handle payment success
     */
    public function successAction()
    {
        $paymentId = $this->session->offsetGet('unzerdirect_payment_id');
        
        $this->log(Logger::DEBUG, 'success action called', ['payment' => $paymentId]);
        
        if(empty($paymentId))
        {
            $this->redirect(['controller' => 'checkout', 'action' => 'confirm']);    
            return;
        }

        $payment = $this->service->getPayment($paymentId);
        $this->log(Logger::DEBUG, 'persisting order in success action', ['payment' => $paymentId]);
        $this->checkAndPersistOrder($payment, true);
        $this->log(Logger::DEBUG, 'order persisted in success action', ['payment' => $paymentId]);
        
        //Remove ID from session
        $this->session->offsetUnset('unzerdirect_payment_id');
        
        //Redirect to finish
        $this->redirect(['controller' => 'checkout', 'action' => 'finish', 'sUniqueID' => $payment->getId()]);

        return;
    }

    /**
     * Handle payment cancel
     */
    public function cancelAction()
    {
        $this->redirect(['controller' => 'checkout', 'action' => 'confirm']);
    }

    /**
     * Get continue url
     *
     * @return mixed|string
     */
    private function getContinueUrl()
    {
        return $this->Front()->Router()->assemble([
            'controller' => 'UnzerDirect',
            'action' => 'success',
            'forceSecure' => true
        ]);
    }

    /**
     * Get cancel url
     *
     * @return mixed|string
     */
    private function getCancelUrl()
    {
        return $this->Front()->Router()->assemble([
            'controller' => 'UnzerDirect',
            'action' => 'cancel',
            'forceSecure' => true
        ]);
    }

    /**
     * Get callback url
     *
     * @return mixed|string
     */
    private function getCallbackUrl()
    {
        return $this->Front()->Router()->assemble([
            'controller' => 'UnzerDirect',
            'action' => 'callback',
            'forceSecure' => true
        ]);
    }

    /**
     * Returns a list with actions which should not be validated for CSRF protection
     *
     * @return string[]
     */
    public function getWhitelistedCSRFActions() {
        return ['callback'];
    }
    
    /**
     * Check if the test_mode property of the payment matches the shop configuration
     * 
     * @param mixed $payment
     * @return boolean
     */
    private function checkTestMode($payment)
    {
        //Check is test mode is enabled
        $testmode = Shopware()->Config()->getByNamespace('UnzerDirectPayment', 'testmode');

        //Check if test_mode property matches the configuration
        return (boolval($testmode) == boolval($payment->test_mode));
    }
    
    /**
     * Checks wether the associated order has been persisted.
     * If not the order will be saved and the temporary entries will be removed.
     * 
     * @param UnzerDirectPayment $payment
     * @param boolean $removeTemporaryOrder flag to remove the temporary order even if a persisted order already exists
     */
    private function checkAndPersistOrder($payment, $removeTemporaryOrder = false)
    {
        $this->log(Logger::DEBUG, 'checkAndPersistOrder: called', [
            'payment' => $payment->getId(),
            'removeTemporaryOrder' => $removeTemporaryOrder
        ]);
        if(empty($payment->getOrderNumber()))
        {
            $this->log(Logger::DEBUG, 'checkAndPersistOrder: order number not set', [
                'payment' => $payment->getId(),
                'signature' => $payment->getBasketSignature()
            ]);
            //Restore the temporary basket
            $this->loadBasketFromSignature($payment->getBasketSignature());
            $this->log(Logger::DEBUG, 'checkAndPersistOrder: basket loaded', [
                'payment' => $payment->getId(),
                'signature' => $payment->getBasketSignature()
            ]);
            //Finally persist the order
            $orderNumber = $this->saveOrder($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_OPEN);
            $this->log(Logger::DEBUG, 'checkAndPersistOrder: order saved', [
                'payment' => $payment->getId(),
                'order_number' => $orderNumber
            ]);
            //Update the payment object
            $payment->setOrderNumber($orderNumber);
            $this->log(Logger::DEBUG, 'checkAndPersistOrder: order_number updated', [
                'payment' => $payment->getId(),
            ]);
            $payment->setBasketSignature(null);
            $this->log(Logger::DEBUG, 'checkAndPersistOrder: basket nulled', [
                'payment' => $payment->getId(),
            ]);
            //Save the changes
            Shopware()->Models()->flush($payment);
            $this->log(Logger::DEBUG, 'checkAndPersistOrder: payment saved to database', [
                'payment' => $payment->getId(),
            ]);
            
            $this->log(Logger::INFO, 'order process finished', [
                'payment' => $payment->getId(),
                'order_number' => $orderNumber,
            ]);
        }
        else if($removeTemporaryOrder)
        {
            $this->log(Logger::DEBUG, 'checkAndPersistOrder: removing temporary order', [
                'payment' => $payment->getId(),
            ]);
            Shopware()->Modules()->Order()->sDeleteTemporaryOrder();
            $this->log(Logger::DEBUG, 'checkAndPersistOrder: temporary order removed', [
                'payment' => $payment->getId(),
            ]);
            Shopware()->Db()->executeUpdate(
                'DELETE FROM s_order_basket WHERE sessionID=?',
                [$this->session->offsetGet('sessionId')]
            );
            
            if ($this->session->offsetExists('sOrderVariables')) {
                $variables = $this->session->offsetGet('sOrderVariables');
                $variables['sOrderNumber'] = $payment->getOrderNumber();
                $this->session->offsetSet('sOrderVariables', $variables);
                $this->log(Logger::DEBUG, 'checkAndPersistOrder: order number in session updated', [
                    'payment' => $payment->getId(),
                    'order_number' => $payment->getOrderNumber()
                ]);
            }
        }
        $this->log(Logger::DEBUG, 'checkAndPersistOrder: finished');
    }
    
    /**
     * Check the payment status and update the order accordingly
     * 
     * @param UnzerDirectPayment $payment
     */
    private function updateOrderStatus($payment)
    {
        switch($payment->getStatus())
        {
            case UnzerDirectPayment::PAYMENT_FULLY_AUTHORIZED:
                $this->savePaymentStatus($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_RESERVED);
                break;
            case UnzerDirectPayment::PAYMENT_PARTLY_CAPTURED:
            case UnzerDirectPayment::PAYMENT_FULLY_CAPTURED:
                $order = $payment->getOrder();
                if($payment->getAmountCaptured() >= $order->getInvoiceAmount())
                {
                    $this->savePaymentStatus($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_COMPLETELY_PAID);
                    if(empty($order->getClearedDate()))
                    {
                        $order->setClearedDate(new DateTime());
                        Shopware()->Models()->flush($order);
                    }
                }
                else
                {
                    $this->savePaymentStatus($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_PARTIALLY_PAID);
                }
                break;
            case UnzerDirectPayment::PAYMENT_CANCELLED:
                $this->savePaymentStatus($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED);
                break;
            case UnzerDirectPayment::PAYMENT_FULLY_REFUNDED:
                $this->savePaymentStatus($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED);
                break;
            case UnzerDirectPayment::PAYMENT_INVALIDATED:
                $this->savePaymentStatus($payment->getOrderId(), $payment->getId(), Status::PAYMENT_STATE_REVIEW_NECESSARY);
                break;
        }
    }
    
    private function log($level, $message, $context = [])
    {
        $this->service->log($level, $message, $context);
    }
    
}