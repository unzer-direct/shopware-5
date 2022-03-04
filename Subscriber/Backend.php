<?php

namespace UnzerDirectPayment\Subscriber;
use Enlight\Event\SubscriberInterface;
use Shopware\Components\DependencyInjection\Container;
class Backend implements SubscriberInterface
{
    /**
     *  @var Container $container
     */
    protected $container;
    protected $pluginDirectory;
    
    /**
     * @param DIContainer $container
     */
    public function __construct(Container $container, $pluginDirectory)
    {
        $this->container = $container;
        $this->pluginDirectory = $pluginDirectory;
    }
    
    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            "Enlight_Controller_Action_PostDispatchSecure_Backend_Order" => "onPostDispatchBackendOrder"
        ];
    }
    
    /**
     * onPostDispatchBackendOrder:
     *
     * @access public
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchBackendOrder(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Order $controller */
        $controller = $args->getSubject();
        $view = $controller->View();
        $request = $controller->Request();
        $view->addTemplateDir($this->pluginDirectory . '/Resources/views');
        switch ($request->getActionName())
        {
            case 'index' :
                $view->extendsTemplate('backend/order/unzerdirect/app.js');
                break;
            case 'load' :
                $view->extendsTemplate("backend/order/unzerdirect/model/order_fields.js");
                break;
            
            case "getList":
                $arrAssignedData = $view->getAssign('data');
                $db = Shopware()->Db();
                foreach($arrAssignedData as $key => $order) {
                    $row = $db->fetchRow('SELECT id as unzerdirect_payment_id, status as unzerdirect_payment_status, amount_authorized as unzerdirect_amount_authorized, amount_captured as unzerdirect_amount_captured, amount_refunded as unzerdirect_amount_refunded FROM unzerdirect_payments WHERE order_number = ?', [$order["number"]], \Zend_Db::FETCH_ASSOC);
                    if($row)
                    {
                        $arrAssignedData[$key] = array_merge($arrAssignedData[$key], $row);    
                    }
                    
                }
                $view->data = $arrAssignedData;
                break;
            case "batchProcess":
                
                $this->onBatchProcessAction($request, $view);
                
                break;
        }
    }
    
    /**
     * 
     * @param \Enlight_Controller_Request_Request $request
     * @param \Enlight_View_Default $view
     */
    public function onBatchProcessAction($request, $view)
    {
        $orders = $view->getAssign('data');
        $action = $request->getParam('unzerdirectAction');
        if(empty($action))
        
        $service = $this->container->get('unzerdirect_payment.unzerdirect_service');
        /** @var Enlight_Components_Snippet_Namespace $namespace */
        $namespace = $this->container->get('snippets')->getNamespace('plugins/unzerdirect/backend/order');
        
        foreach ($orders as &$data) {
            //Check if the batch processing for this order already failed
            if(!$data['success'])
                continue;
            try{
                /** @var \UnzerDirectPayment\Models\UnzerDirectPayment $payment */
                $payment = Shopware()->Models()->find(\UnzerDirectPayment\Models\UnzerDirectPayment::class, $data['unzerdirect_payment_id']);
                if(empty($payment))
                {
                    $data['success'] = false;
                    $data['errorMessage'] = $namespace->get('invalid_unzerdirect_payment', 'The order has no associated UnzerDirect Payment');
                }
                else
                {
                    switch ($action)
                    {
                        case 'capture':
                            $amount = $data['invoiceAmount'] * 100 - $payment->getAmountCaptured();
                            $service->requestCapture($payment, $amount);
                            break;
                        case 'refund':
                            $amount = $payment->getAmountCaptured();
                            $service->requestRefund($payment, $amount);
                            break;
                        case 'cancel':
                            $service->requestCancel($payment);
                            break;
                        default:
                            $data['success'] = false;
                            $data['errorMessage'] = $namespace->get('invalid_unzerdirect_action', 'Invalid UnzerDirect payment action submitted');
                            break;
                    }
                
                    $data['unzerdirect_payment_status'] = $payment->getStatus();
                }
                
            } catch (\Exception $ex) {
                $data['success'] = false;
                $data['errorMessage'] = sprintf(
                    $namespace->get('unzerdirect_action_failed', 'Error when performing UnzerDirect action. Error: %s'),
                    $ex->getMessage()
                );
            }
        }
        $view->data = $orders;
    }
}
