<?php

use UnzerDirectPayment\Models\UnzerDirectPayment;
use UnzerDirectPayment\Models\UnzerDirectPaymentOperation;
class Shopware_Controllers_Backend_UnzerDirect extends Shopware_Controllers_Backend_Application
{
    
    protected $model = UnzerDirectPayment::class;
    
    protected $alias = "unzerdirect";
    
    function getList($offset, $limit, $sort = array(), $filter = array(), array $wholeParams = array()): array
    {
        $list = parent::getList($offset, $limit, $sort, $filter, $wholeParams);
        if($details['success'])
        {
            foreach ($list['data'] as &$entry)
            {
                $entry['operations'] = $this->getOperations($entry['id']);
            }
        }
        return $list;
    }
    
    public function refundAction()
    {
        try {
            $paymentId = $this->Request()->getParam('id');
            if(!$paymentId)
            {
                $this->View()->success = false;
                $this->View()->message = "No payment Id";
            }
            else
            {
                /** @var \UnzerDirectPayment\Components\UnzerDirectService $service */
                $service = $this->get('unzerdirect_payment.unzerdirect_service');
                
                $payment = $service->getPayment($paymentId);
                if($payment)
                {
                    $amount = $this->Request()->getParam('amount');
                    $amount = is_numeric($amount) ? intval($amount) : 0;
                    $service->requestRefund($payment, $amount);
                    $this->View()->success = true;
                }
                else
                {
                    $this->View()->success = false;
                    $this->View()->message = "Invalid payment id";                    
                }
            }
        }
        catch(Exception $e)
        {
            $this->View()->success = false;
            $this->View()->message = $e->getMessage();
        }
    }
    
    protected function getOperations($id)
    {
        try {
            $db = Shopware()->Db();
            return $db->fetchAll("SELECT id, created_at as createdAt, type, status, amount FROM unzerdirect_payment_operations WHERE payment_id = ? ORDER BY created_at DESC, id DESC", [$id], Zend_Db::FETCH_ASSOC);
        }
        catch(Exception $e)
        {
            return array();
        }
    }
    
    public function captureAction()
    {
        try {
            $paymentId = $this->Request()->getParam('id');
            if(!$paymentId)
            {
                $this->View()->success = false;
                $this->View()->message = "No payment Id";
            }
            else
            {
                /** @var \UnzerDirectPayment\Components\UnzerDirectService $service */
                $service = $this->get('unzerdirect_payment.unzerdirect_service');
                
                $payment = $service->getPayment($paymentId);
                if($payment)
                {
                    $amount = $this->Request()->getParam('amount');
                    $amount = is_numeric($amount) ? intval($amount) : 0;
                    $service->requestCapture($payment, $amount);
                    $this->View()->success = true;
                }
                else
                {
                    $this->View()->success = false;
                    $this->View()->message = "Invalid payment id";                    
                }
            }
        }
        catch(Exception $e)
        {
            $this->View()->success = false;
            $this->View()->message = $e->getMessage();
        }
    }
    
    function getDetail($id): array
    {
        $detail = parent::getDetail($id);
        if($detail['success'] && !empty($detail['data']))
        {
            $detail['data']['operations'] = $this->getOperations($id);
        }
        return $detail;
    }

    public function cancelAction()
    {
        try {
            $paymentId = $this->Request()->getParam('id');
            if(!$paymentId)
            {
                $this->View()->success = false;
                $this->View()->message = "No payment Id";
            }
            else
            {
                /** @var \UnzerDirectPayment\Components\UnzerDirectService $service */
                $service = $this->get('unzerdirect_payment.unzerdirect_service');
                
                $payment = $service->getPayment($paymentId);
                if($payment)
                {
                    $service->requestCancel($payment);
                    $this->View()->success = true;
                }
                else
                {
                    $this->View()->success = false;
                    $this->View()->message = "Invalid payment id";                    
                }
            }
        }
        catch(Exception $e)
        {
            $this->View()->success = false;
            $this->View()->message = $e->getMessage();
        }
    }
    
  
}
