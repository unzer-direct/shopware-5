<?php

namespace UnzerDirectPayment\Components;

use Exception;
use UnzerDirectPayment\Models\UnzerDirectPayment;
use UnzerDirectPayment\Models\UnzerDirectPaymentOperation;
use Shopware\Components\Logger;
use Shopware\Components\Random;
use Shopware\Models\Customer\Customer;
use function Shopware;

class UnzerDirectService
{
    private $baseUrl = 'https://api.unzerdirect.com';

    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_GET = 'GET';
    const METHOD_PATCH = 'PATCH';

    /**
     * @var Shopware\Components\Logger
     */
    private $logger;
    
    public function __construct($logger)
    {
        $this->logger = $logger;
    }
    
    public function log($level, $message, $context = [])
    {
        if(!is_array($context))
            $context = get_object_vars ($context);
        
        $this->logger->log($level, $message, $context);
    }
    
    /**
     * Create payment
     *
     * @param integer $userId Id of the ordering user
     * @param mixed $basket Basket of the order
     * @param string $currency Short name of the used currency
     * @param integer $amount Amount to pay in cents
     * @return \UnzerDirectPayment\Models\UnzerDirectPayment
     */
    public function createPayment($userId, $basket, $amount, $variables, $currency)
    {
        $orderId = $this->createOrderId();
        
        $parameters = [
            'currency' => $currency,
            'order_id' => $orderId,
            'variables' => $variables,
            'branding_id' => $this->getBrandingId(),
            'basket' => $this->getBasketParameter($basket),
            'shipping' => $this->getShippingParameter($basket),
            'shopsystem' => [
                'name' => 'Shopware 5',
                'version' => $this->getPluginVersion()
            ]
        ];

        $this->log(Logger::DEBUG, 'payment creation requested', $parameters);
        //Create payment
        $paymentData = $this->request(self::METHOD_POST, '/payments', $parameters);
        $this->log(Logger::INFO, 'payment created', $paymentData);
        
        //Register payment in database 
        $customer = Shopware()->Models()->find(Customer::class, $userId);
        
        $payment = new UnzerDirectPayment($paymentData->id, $orderId, $customer, $amount);
        
        Shopware()->Models()->persist($payment);
        Shopware()->Models()->flush($payment);
        
        $this->handleNewOperation($payment, (object) array(
            'type' => 'create',
            'id' => null,
            'amount' => 0,
            'created_at' => date(),
            'payload' => $paymentData
        ));
        
        return $payment;
    }

    /**
     * Get payment data for orders created with a previous version of the plugin
     * 
     * @param Shopware\Models\Order\Order $order
     */
    public function createPaymentRetroactively($order)
    {
        try {
            $paymentId = $order->getTemporaryId();
            
            $parameters = [];

            $resource = sprintf('/payments/%s', $paymentId);

            //Get payment
            $paymentData = $this->request(self::METHOD_GET, $resource, $parameters);
            
            $payment = new UnzerDirectPayment($paymentData->id, $paymentData->order_id, $order->getCustomer(), $paymentData->link->amount);
            
            $payment->setLink($paymentData->link->url);
            $payment->setOrderNumber($order->getNumber());
            
            Shopware()->Models()->persist($payment);
            Shopware()->Models()->flush($payment);

            $this->handleNewOperation($payment, (object) array(
                'type' => 'create',
                'id' => null,
                'amount' => 0,
                'created_at' => $paymentData->created_at,
                'payload' => $paymentData
            ));
            
            $this->registerCallback($payment, $paymentData);
            
            return $payment;
        }
        catch(Exception $e)
        {
            return null;
        }
    }
    
    /**
     * Load the payment data through the UnzerDirect API and update the operations
     * 
     * @param UnzerDirectPayment $payment
     */
    public function loadPaymentOperations($payment)
    {
        $resource = sprintf('/payments/%s', $payment->getId());
        
        try{
            //Get payment data
            $paymentData = $this->request(self::METHOD_GET, $resource, []);

            $this->registerCallback($payment, $paymentData);
        }
        catch (Exception $e)
        {
            
        }
    }
    
    /**
     * Update payment
     *
     * @param string $paymentId Id of the UnzerDirectPayment
     * @param mixed $basket The current basket
     * @param integer $amount Amount to pay in cents
     * @return \UnzerDirectPayment\Models\UnzerDirectPayment
     */
    public function updatePayment($paymentId, $basket, $amount, $variables)
    {
        $parameters = [
            'variables' => $variables,
            'branding_id' => $this->getBrandingId(),
            'basket' => $this->getBasketParameter($basket),
            'shipping' => $this->getShippingParameter($basket),
            'shopsystem' => [
                'name' => 'Shopware 5',
                'version' => $this->getPluginVersion()
            ]
        ];
        
        $resource = sprintf('/payments/%s', $paymentId);
        
        $this->log(Logger::DEBUG, 'payment update requested', $parameters);
        //Update payment
        $paymentData = $this->request(self::METHOD_PATCH, $resource, $parameters);
        $this->log(Logger::INFO, 'payment updated', $paymentData);
        
        $payment = $this->getPayment($paymentId);
        
        //Update amount to pay
        $payment->setAmount($amount);
        Shopware()->Models()->flush($payment);
        
        return $payment;
    }
    
    private function getBasketParameter($basket)
    {
        if ($basket === null)
            return [];
        
        $result = [];
        
        foreach($basket['content'] as $item)
        {
            $result[] = [
                'qty' => $item['quantity'] * 1,
                'item_no' => $item['ordernumber'],
                'item_name' => $item['articlename'],
                'item_price' => $item['price'] * 100,
                'vat_rate' => $item['tax_rate'] / 100.0
            ];
        }

        return $result;
    }
    
    private function getShippingParameter($basket)
    {
        if ($basket === null)
            return [];
        
        return [
            'amount' => $basket['sShippingcostsWithTax'] * 100,
            'vat_rate' => $basket['sShippingcostsTax'] / 100.0,
        ];
    }
    
    /**
     * Get payment by id
     * 
     * @param integer $paymentId Id of the current basket
     * @return \UnzerDirectPayment\Models\UnzerDirectPayment
     */
    public function getPayment($paymentId)
    {
        /** @var UnzerDirectPayment $payment */
        $payment = Shopware()->Models()->find(UnzerDirectPayment::class, $paymentId);
        
        if(empty($payment))
            return null;
        
        return $payment;
    }
    
    /**
     * Register a callback
     * 
     * @param UnzerDirectPayment $payment the linked payment object
     * @param mixed $data data contained in the request body
     */
    public function registerCallback($payment, $data)
    {
        $operations = $payment->getOperations();
        
        //Sort Operations by Id
        $operationsById = array();
        /** @var UnzerDirectPaymentOperation $operation */
        foreach($operations as $operation)
        {
            if($operation->getOperationId() != null)
                $operationsById[$operation->getOperationId()] = $operation;
        }

        //update operations with data from the callback
        foreach($data->operations as $operation)
        {
            if(!isset($operationsById[$operation->id]))
            {
                $operationsById[$operation->id] = $this->handleNewOperation($payment, $operation, false);
            }
            else{
                $operationsById[$operation->id]->update($operation);
            }
        }
        
        //save changes made to the operations
        Shopware()->Models()->flush($operationsById);
        
        $this->updateStatus($payment);
    }

    /**
     * Create a UnzerDirect payment operation
     * 
     * @param UnzerDirectPayment $payment
     * @param mixed $data
     * @param boolean $updateStatus
     * @return UnzerDirectPaymentOperation
     */
    public function handleNewOperation($payment, $data, $updateStatus = true)
    {
        $operation = new UnzerDirectPaymentOperation($payment, $data);
        //Persist the new operation
        Shopware()->Models()->persist($operation);
        Shopware()->Models()->flush($operation);
        
        if($updateStatus)
        {
            $this->updateStatus($payment);
        }
        
        return $operation;
    }
            
    /**
     * Update the status of the UnzerDirect payment according to the operations
     * 
     * @param UnzerDirectPayment $payment
     */
    public function updateStatus($payment)
    {
        $amount = $payment->getAmount();
        $amountAuthorized = 0;
        $amountCaptured = 0;
        $amountRefunded = 0;
        $status = UnzerDirectPayment::PAYMENT_CREATED;
        
        $repository = Shopware()->Models()->getRepository(UnzerDirectPaymentOperation::class);
        $operations = $repository->findBy(['payment' => $payment], ['createdAt' => 'ASC', 'id' => 'ASC']);
        
        /** @var UnzerDirectPaymentOperation $operation */
        foreach($operations as $operation)
        {
            
            switch ($operation->getType())
            {
                case 'authorize':
                    if($operation->isSuccessfull())
                    {
                        $amountAuthorized += $operation->getAmount();

                        if($amount <= $amountAuthorized)
                        {
                            $status = UnzerDirectPayment::PAYMENT_FULLY_AUTHORIZED;
                        }
                    }
                    break;

                case 'capture_request':
                    
                    $status = UnzerDirectPayment::PAYMENT_CAPTURE_REQUESTED;

                    break;

                case 'capture':
                    if($operation->isSuccessfull())
                    {
                        $amountCaptured += $operation->getAmount();

                        if($amount <= $amountCaptured)
                        {
                            $status = UnzerDirectPayment::PAYMENT_FULLY_CAPTURED;
                        }
                        else
                        {
                            $status = UnzerDirectPayment::PAYMENT_PARTLY_CAPTURED;
                        }
                    }
                    else if($operation->isFinished())
                    {
                        if($amountCaptured > 0)
                        {
                            $status = UnzerDirectPayment::PAYMENT_PARTLY_CAPTURED;
                        }
                        else
                        {
                            $status = UnzerDirectPayment::PAYMENT_FULLY_AUTHORIZED;
                        }
                    }
                    break;

                case 'cancel_request':
                    $status = UnzerDirectPayment::PAYMENT_CANCEL_REQUSTED;

                    break;

                case 'cancel':
                    if($operation->isSuccessfull())
                    {
                        $status = UnzerDirectPayment::PAYMENT_CANCELLED;
                    }
                    else if($operation->isFinished())
                    {
                        $status = UnzerDirectPayment::PAYMENT_FULLY_AUTHORIZED;
                    }

                    break;

                case 'refund_request':

                    $status = UnzerDirectPayment::PAYMENT_REFUND_REQUSTED;

                    break;

                case 'refund':
                    if($operation->isSuccessfull())
                    {
                        $amountRefunded += $operation->getAmount();

                        if($amountCaptured <= $amountRefunded)
                        {
                            $status = UnzerDirectPayment::PAYMENT_FULLY_REFUNDED;
                        }
                        else
                        {
                            $status = UnzerDirectPayment::PAYMENT_PARTLY_REFUNDED;
                        }
                    }
                    else
                    {
                        if($amountRefunded > 0)
                        {
                            $status = UnzerDirectPayment::PAYMENT_PARTLY_REFUNDED;
                        }
                        else
                        {
                            if($amountCaptured < $amount)
                            {
                                $status = UnzerDirectPayment::PAYMENT_PARTLY_CAPTURED;
                            }
                            else
                            {
                                $status = UnzerDirectPayment::PAYMENT_FULLY_CAPTURED;
                            }
                        }
                    }

                    break;

                case 'checksum_failure':
                case 'test_mode_violation':
                    $status = UnzerDirectPayment::PAYMENT_INVALIDATED;
                    break;

                default:
                    break;
            }

        }
        
        $payment->setAmountAuthorized($amountAuthorized);
        $payment->setAmountCaptured($amountCaptured);
        $payment->setAmountRefunded($amountRefunded);
        $payment->setStatus($status);
        
        //Save updates to the payment object
        Shopware()->Models()->flush($payment);
    }
    
    /**
     * Register a callback containing a bad checksum
     * 
     * @param UnzerDirectPayment $payment the linked payment object
     * @param mixed $data data contained in the request body
     */
    public function registerFalseChecksumCallback($payment, $data)
    {        
        $this->handleNewOperation($payment, (object) array(
            'type' => 'checksum_failure',
            'id' => null,
            'amount' => 0,
            'payload' => $data
        ));
    }
    
    /**
     * Register a callback containing wrong test mode settings
     * 
     * @param UnzerDirectPayment $payment the linked payment object
     * @param mixed $data data contained in the request body
     */
    public function registerTestModeViolationCallback($payment, $data)
    {
        $this->handleNewOperation($payment, (object) array(
            'type' => 'test_mode_violation',
            'id' => null,
            'amount' => 0,
            'payload' => $data
        ));
    }
    
    /**
     * Create payment link
     *
     * @param UnzerDirectPayment $payment UnzerDirect payment
     * @param double $amount invoice amount of the order
     * @param string $email Mail-address of the customer
     * @param string $continueUrl redirect URL in case of success
     * @param string $cancelUrl redirect URL in case of cancellation
     * @param string $callbackUrl URL to send callback to
     *
     * @return string link for UnzerDirect payment
     */
    public function createPaymentLink($payment, $paymentMethods, $email, $continueUrl, $cancelUrl, $callbackUrl)
    {
        $resource = sprintf('/payments/%s/link', $payment->getId());
        $parameters = [
            'amount'             => $payment->getAmount(),
            'continueurl'        => $continueUrl,
            'cancelurl'          => $cancelUrl,
            'callbackurl'        => $callbackUrl,
            'customer_email'     => $email,
            'language'           => $this->getLanguageCode(),
            'payment_methods'    => $paymentMethods
        ];
        $this->log(Logger::DEBUG, 'payment link creation requested', $parameters);
        $paymentLink = $this->request(self::METHOD_PUT, $resource, $parameters);
        $this->log(Logger::INFO, 'payment link created', $paymentLink);

        return $paymentLink->url;
    }

    /**
     * send a capture request to the UnzerDirect API
     * 
     * @param UnzerDirectPayment $payment
     * @param integer $amount
     */
    public function requestCapture($payment, $amount)
    {
        if($payment->getStatus() != UnzerDirectPayment::PAYMENT_FULLY_AUTHORIZED
            && $payment->getStatus() != UnzerDirectPayment::PAYMENT_PARTLY_CAPTURED)
        {
            throw new Exception('Invalid payment state');
        }
        
        if($amount <= 0 || $amount > $payment->getAmountAuthorized() - $payment->getAmountCaptured())
        {
            throw new Exception('Invalid amount');
        }
        
        $operation = $this->handleNewOperation($payment, (object) array(
            'type' => 'capture_request',
            'id' => null,
            'amount' => $amount
        ));
        
        try
        {
        
            $resource = sprintf('/payments/%s/capture', $payment->getId());
            $this->log(Logger::DEBUG, 'payment capture requested');
            $paymentData = $this->request(self::METHOD_POST, $resource, [
                    'amount' => $amount
                ], 
                [
                    'UnzerDirect-Callback-Url' => Shopware()->Front()->Router()->assemble([
                        'controller' => 'UnzerDirect',
                        'action' => 'callback',
                        'forceSecure' => true,
                        'module' => 'frontend'
                    ])
                ]);
            $this->log(Logger::INFO, 'payment captured', $paymentData);
        }
        catch (Exception $e)
        {
            Shopware()->Models()->remove($operation);
            Shopware()->Models()->flush($operation);
            
            $this->log(Logger::Error, 'exception during capture', ['message' => $ex->getMessage()]);
            
            throw $e;
        }

    }

    /**
     * send a capture request to the UnzerDirect API
     * 
     * @param UnzerDirectPayment $payment
     */
    public function requestCancel($payment)
    {
        if($payment->getStatus() != UnzerDirectPayment::PAYMENT_FULLY_AUTHORIZED
            && $payment->getStatus() != UnzerDirectPayment::PAYMENT_CREATED
            && $payment->getStatus() != UnzerDirectPayment::PAYMENT_ACCEPTED)
        {
            throw new Exception('Invalid payment state');
        }
        
        if($payment->getAmountCaptured() > 0)
        {
            throw new Exception('Payment already (partly) captured');
        }
        
        $operation = $this->handleNewOperation($payment, (object) array(
            'type' => 'cancel_request',
            'id' => null,
            'amount' => 0
        ));
        
        try
        {

            $resource = sprintf('/payments/%s/cancel', $payment->getId());
            $this->log(Logger::DEBUG, 'payment cancellation requested');
            $paymentData = $this->request(self::METHOD_POST, $resource, [], 
                [
                    'UnzerDirect-Callback-Url' => Shopware()->Front()->Router()->assemble([
                        'controller' => 'UnzerDirect',
                        'action' => 'callback',
                        'forceSecure' => true,
                        'module' => 'frontend'
                    ])
                ]);
            $this->log(Logger::DEBUG, 'payment canceled', $paymentData);
            
        } catch (Exception $ex) {
            Shopware()->Models()->remove($operation);
            Shopware()->Models()->flush($operation);
            
            $this->log(Logger::Error, 'exception during cancellation', ['message' => $ex->getMessage()]);
            
            throw $e;
        }        
    }

    /**
     * send a capture request to the UnzerDirect API
     * 
     * @param UnzerDirectPayment $payment
     * @param integer $amount
     */
    public function requestRefund($payment, $amount)
    {
        if($payment->getStatus() != UnzerDirectPayment::PAYMENT_FULLY_CAPTURED
            && $payment->getStatus() != UnzerDirectPayment::PAYMENT_PARTLY_CAPTURED
            && $payment->getStatus() != UnzerDirectPayment::PAYMENT_PARTLY_REFUNDED)
        {
            throw new Exception('Invalid payment state');
        }
        
        if($amount <= 0 || $amount > $payment->getAmountCaptured() - $payment->getAmountRefunded())
        {
            throw new Exception('Invalid amount');
        
        }
        
        $operation = $this->handleNewOperation($payment, (object) array(
            'type' => 'refund_request',
            'id' => null,
            'amount' => $amount
        ));
        
        try
        {
            
            $resource = sprintf('/payments/%s/refund', $payment->getId());
            $this->log(Logger::DEBUG, 'payment refund requested');
            $paymentData = $this->request(self::METHOD_POST, $resource, [
                    'amount' => $amount
                ], 
                [
                    'UnzerDirect-Callback-Url' => Shopware()->Front()->Router()->assemble([
                        'controller' => 'UnzerDirect',
                        'action' => 'callback',
                        'forceSecure' => true,
                        'module' => 'frontend'
                    ])
                ]);
            $this->log(Logger::DEBUG, 'payment refunded', $paymentData);

        } catch (Exception $ex) {
            Shopware()->Models()->remove($operation);
            Shopware()->Models()->flush($operation);
            
            $this->log(Logger::Error, 'exception during refund', ['message' => $ex->getMessage()]);
            
            throw $e;
        }
        
    }
    
    /**
     * Perform API request
     *
     * @param string $method
     * @param $resource
     * @param array $params
     * @param bool $headers
     */
    private function request($method = self::METHOD_POST, $resource, $params = [], $headers = [])
    {
        $ch = curl_init();

        $baseUrl = $this->getUrlConfig() ?? $this->baseUrl;
        
        $url = $baseUrl . $resource;
        
        //Set CURL options
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER     => $this->getHeaders($headers),
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => json_encode($params),
        ];

        curl_setopt_array($ch, $options);

        $this->log(Logger::DEBUG, 'request sent', $options);
        //Get response
        $result = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->log(Logger::DEBUG, 'request finished', ['code' => $responseCode, 'response' => $result]);
        
        curl_close($ch);

        //Validate reponsecode
        if (! in_array($responseCode, [200, 201, 202])) {
            throw new Exception('Invalid gateway response ' . $result);
        }

        $response = json_decode($result);

        //Check for JSON errors
        if (! $response || (json_last_error() !== JSON_ERROR_NONE)) {
            throw new Exception('Invalid json response');
        }

        return $response;
    }

    /**
     * Get CURL headers
     *
     * @param array $headers list of additional headers
     * @return array
     */
    private function getHeaders($headers)
    {
        $result = [
            'Authorization: Basic ' . base64_encode(':' . $this->getApiKey()),
            'Accept-Version: v10',
            'Accept: application/json',
            'Content-Type:application/json'
        ];
        
        foreach ($headers as $key => $value)
        {
            $result[] = $key. ': '. $value;
        }
        
        return $result;
    }

    /**
     * Get API key from config
     *
     * @return mixed
     */
    private function getApiKey()
    {
        return Shopware()->Config()->getByNamespace('UnzerDirectPayment', 'public_key');
    }

    /**
     * Get branding id key from config
     *
     * @return mixed
     */
    private function getBrandingId()
    {
        return Shopware()->Config()->getByNamespace('UnzerDirectPayment', 'branding_id');
    }

    /**
     * Get branding id key from config
     *
     * @return mixed
     */
    private function getUrlConfig()
    {
        return Shopware()->Config()->getByNamespace('UnzerDirectPayment', 'alternative_url', null);
    }

    /**
     * Get branding id key from config
     *
     * @return mixed
     */
    private function getPluginVersion()
    {
        return '1.0.0';
    }

    /**
     * Get language code
     *
     * @return string
     */
    private function getLanguageCode()
    {
        $locale = Shopware()->Shop()->getLocale()->getLocale();

        return substr($locale, 0, 2);
    }
    
    /**
     * Creates a unique order id
     * 
     * @return string
     */
    public function createOrderId()
    {
        return Random::getAlphanumericString(20);
    }
    
    
}
