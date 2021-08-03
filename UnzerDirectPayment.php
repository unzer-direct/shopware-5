<?php
namespace UnzerDirectPayment;

use Doctrine\ORM\Tools\SchemaTool;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Models\Payment\Payment;
use UnzerDirectPayment\Models\UnzerDirectPayment as PaymentModel;
use UnzerDirectPayment\Models\UnzerDirectPaymentOperation as PaymentOperationModel;
use Shopware\Models\Order\Status as OrderStatus;

class UnzerDirectPayment extends Plugin
{
    /**
     * Install plugin
     *
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        $this->createOrUpdatePaymentOption(
            $context,
            'creditcard',
            'Unzer direct - Credit Card',
            '<div id="payment_desc">'
                . '  Pay by credit card using the UnzerDirect payment service provider.'
                . '</div>'
        );
        
        $this->createOrUpdatePaymentOption(
            $context,
            'paypal',
            'Unzer direct - PayPal',
            '<div id="payment_desc">'
                . '  Pay via PayPal using the UnzerDirect payment service provider.'
                . '</div>'
        );
        
        $this->createOrUpdatePaymentOption(
            $context,
            'klarna',
            'Unzer direct - Klarna',
            '<div id="payment_desc">'
                . '  Pay via Klarna using the UnzerDirect payment service provider.'
                . '</div>'
        );
        
        $this->createTables();
        
        $this->createAttributes();
        
        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
    }

    private function createOrUpdatePaymentOption(InstallContext $context, $name, $description, $additionalDescription)
    {
        /** @var \Shopware\Components\Plugin\PaymentInstaller $installer */
        $installer = $this->container->get('shopware.plugin_payment_installer');

        $options = [
            'name' => "unzerdirect_payment_$name",
            'description' => $description,
            'action' => "unzerdirect/$name",
            'active' => 0,
            'position' => 0,
            'additionalDescription' => $additionalDescription
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);
        
    }
    
    /**
     * Update plugin
     * 
     * @param UpdateContext $context
     */
    public function update(UpdateContext $context)
    {
        $currentVersion = $context->getCurrentVersion();

        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
        
    }
    
    /**
     * Uninstall plugin
     *
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
        
        if(!$context->keepUserData())
        {
            $this->removeAttributes();

            $this->removeTables();
        }
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), false);
    }

    /**
     * Activate plugin
     *
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $this->setActiveFlag($context->getPlugin()->getPayments(), true);
    }

    /**
     * Change active flag
     *
     * @param Payment[] $payments
     * @param $active bool
     */
    private function setActiveFlag($payments, $active)
    {
        $em = $this->container->get('models');

        foreach ($payments as $payment) {
            $payment->setActive($active);
        }
        $em->flush();
    }
    
    /**
     * Create or update all Attributes
     * 
     */
    private function createAttributes()
    {
        
    }
    
    /**
     * Remove all attributes
     */
    private function removeAttributes()
    {
        
    }
    
    /**
     * Create all tables
     */
    private function createTables()
    {
        /** @var ModelManager $entityManager */
        $entityManager = $this->container->get('models');
        
        $tool = new SchemaTool($entityManager);
        
        $classMetaData = [
            $entityManager->getClassMetadata(PaymentModel::class),
            $entityManager->getClassMetadata(PaymentOperationModel::class)
        ];
        
        $tool->updateSchema($classMetaData, true);
    }
    
    /**
     * Remove all tables
     */
    private function removeTables()
    {
        /** @var ModelManager $entityManager */
        $entityManager = $this->container->get('models');
        
        $tool = new SchemaTool($entityManager);
        
        $classMetaData = [
            $entityManager->getClassMetadata(PaymentModel::class),
            $entityManager->getClassMetadata(PaymentOperationModel::class)
        ];
        
        $tool->dropSchema($classMetaData);
    }

    private function createPaymentsRetroactively()
    {
        $entity = Shopware()->Models()->getRepository(\Shopware\Models\Order\Order::class);
        $builder = $entity->createQueryBuilder('orders')
                ->where('orders.cleared = '. OrderStatus::PAYMENT_STATE_OPEN)
                ->orWhere('orders.cleared = '. OrderStatus::PAYMENT_STATE_RESERVED);
        
        $orders = $builder->getQuery()->execute();
        
        $service = new Components\UnzerDirectService();
        
        foreach ($orders as $order)
        {
            $service->createPaymentRetroactively($order);
        }
        
    }
    
    private function updateOperationsStaus()
    {
        $payments = Shopware()->Models()->getRepository(PaymentModel::class)->findAll();
        
        $service = new Components\UnzerDirectService();
        
        foreach ($payments as $payment)
        {
            $service->loadPaymentOperations($payment);
        }        
    }
    
}