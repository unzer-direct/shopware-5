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

/**
 * Unzerdirect Payment Gateway
 * @category Unzerdirect Plugin
 * @package UnzerDirectPayment
 * @author Dev1
 * @copyright Dev1
 * @version 2.2.1
 *
 */
class UnzerDirectPayment extends Plugin
{
    /**
     * Install plugin
     *
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        /** @var \Shopware\Components\Plugin\PaymentInstaller $installer */
        $installer = $this->container->get('shopware.plugin_payment_installer');
        $options = [
            'name' => 'unzerdirect_payment_creditcard',
            'description' => 'UnzerDirect - creditcard',
            'action' => 'UnzerDirect/redirect',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
            '<img src="' . substr($urlResponse, 0, -8) . 'custom/plugins/UnzerDirectPayment/Resources/images/UnzerDirect_logo_creditcard.png"/>'
                . '<div id="payment_desc">'
                . 'Pay using the UnzerDirect payment service provider.'
                . '</div>'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);
        $options = [
            'name' => 'unzerdirect_payment_klarnapayments',
            'description' => 'UnzerDirect - klarna Payments',
            'action' => 'UnzerDirect/redirect',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
            '<img src="' . substr($urlResponse, 0, -8) . 'custom/plugins/UnzerDirectPayment/Resources/images/UnzerDirect_logo_klarnapayments.png"/>'
                . '<div id="payment_desc">'
                . 'Pay using the UnzerDirect payment service provider.'
                . '</div>'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);
        $options = [
            'name' => 'unzerdirect_payment_paypal',
            'description' => 'UnzerDirect - paypal',
            'action' => 'UnzerDirect/redirect',
            'active' => 0,
            'position' => 0,
            'additionalDescription' =>
            '<img src="' . substr($urlResponse, 0, -8) . 'custom/plugins/UnzerDirectPayment/Resources/images/UnzerDirect_logo_paypal.png"/>'
                . '<div id="payment_desc">'
                . 'Pay using the UnzerDirect payment service provider.'
                . '</div>'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);

        
        $this->createTables();
        $this->createAttributes();
        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
    }
    /**
     * Update plugin
     * 
     * @param UpdateContext $context
     */
    public function update(UpdateContext $context)
    {
        $currentVersion = $context->getCurrentVersion();
        if (version_compare($currentVersion, '2.0.0', '<')) {
            if (version_compare($currentVersion, '1.1.0', '>=')) {
                $crud = $this->container->get('shopware_attribute.crud_service');
                try {
                    $crud->delete('s_order_attributes', 'unzerdirect_payment_link');
                } catch (\Exception $e) {
                }
                Shopware()->Models()->generateAttributeModels(
                    array('s_order_attributes')
                );
            }

            if (version_compare($currentVersion, '1.2.0', '>=')) {
                /** @var ModelManager $entityManager */
                $entityManager = $this->container->get('models');
                $tool = new SchemaTool($entityManager);
                $classMetaData = [
                    $entityManager->getClassMetadata(PaymentModel::class)
                ];
                $tool->dropSchema($classMetaData);
            }

            $this->createTables();

            if (version_compare($currentVersion, '1.1.0', '>=')) {
                $this->createPaymentsRetroactively();
            }
        } else if (version_compare($currentVersion, '2.0.1', '<')) {
            $this->createTables();
            $this->updateOperationsStaus();
        }
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
        if (!$context->keepUserData()) {
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
            ->where('orders.cleared = ' . OrderStatus::PAYMENT_STATE_OPEN)
            ->orWhere('orders.cleared = ' . OrderStatus::PAYMENT_STATE_RESERVED);
        $orders = $builder->getQuery()->execute();
        $service = new Components\UnzerDirectService();
        foreach ($orders as $order) {
            $service->createPaymentRetroactively($order);
        }
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

    private function updateOperationsStaus()
    {
        $payments = Shopware()->Models()->getRepository(PaymentModel::class)->findAll();
        $service = new Components\UnzerDirectService();
        foreach ($payments as $payment) {
            $service->loadPaymentOperations($payment);
        }
    }
}
