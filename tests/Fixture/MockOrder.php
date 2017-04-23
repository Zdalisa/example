<?php
/**
 * MockOrder.php
 *
 * @package nsi
 * @author  a.zdorenko
 */

namespace Nsi\Tests\Fixture;

use Nsi\Tests\Fixture\MockNSI;
use Nsi\Model\DbMapper\SupplierPosition;
use Nsi\Model\DbMapper\CondSupplierPosition;
use Nsi\Model\DbMapper\SupplierPositionRegions;
use Nsi\Model\DbMapper\Order;
use Nsi\Model\DbMapper\OrderItem;

/**
 * Class MockOrder
 * @package Nsi\Tests\Fixture
 */
class MockOrder
{
    /** @var MockNSI */
    protected $mockNSI;

    /**
     * Код региона используемого для тестов.
     * @var string
     */
    protected $testRegionCode = '45000000000';

    /**
     * MockOrder constructor.
     *
     * @return void Void.
     */
    public function __construct()
    {
        $this->mockNSI = new MockNSI();
    }

    /**
     * Создание тестового заказа.
     *
     * @return Order Заказ.
     */
    public function createTestOrder()
    {
        $category = $this->mockNSI->createTestCategory();
        $dictionaryPosition = $this->mockNSI->addDictionaryPosition($category, "UnitTestPosition");

        $supplierList = \Model_Contragent::getSuppliersList(['start' => 0, 'limit' => 1]);
        if ($supplierList['totalCount'] < 1) {
            throw new \Exception('Для создания тестовых данных нужен хотябы один поставщик.');
        }
        $supplier = \Model_Contragent::loadByPrimary(array_shift($supplierList['entries'])['id']);

        $supplierPosition = $this->mockNSI->addSupplierPosition(
            $dictionaryPosition->getId(),
            $supplier->getId(),
            array(
                SupplierPosition::PARAM_NAME => "Contragent_" . $supplier->getId() . "UnitTestPosition",
                SupplierPosition::PARAM_IS_ACTUAL => 1,
                SupplierPosition::PARAM_DELETED => 0
            )
        );

        $this->mockNSI->addSupplierPositionCondition($supplierPosition, array($this->testRegionCode), array(
            CondSupplierPosition::PRICE => 101.99,
            CondSupplierPosition::QUANTITY => 1
        ));

        $customerList = \Model_Contragent::getContragentsByType(['type' => 'customer', 'start' => 0, 'limit' => 1]);
        if ($customerList['totalCount'] < 1) {
            throw new \Exception('Для создания тестовых данных нужен хотябы один заказчик.');
        }
        $customer = \Model_Contragent::loadByPrimary(array_shift($customerList['entries'])['id']);

        $order = Order::createObject();
        $order->setTitle('UnitTestOrder');
        $order->setPurchaseTarget('UnitTestOrderPurchaseTarget');
        $order->setCustomerId($customer->getId());
        $order->setSupplierId($supplier->getId());
        $order->setStatus(Order::STATUS_PROJECT);
        $order->save();

        if ($order->getId()) {
            $orderItem = OrderItem::createObject();
            $orderItem->setOrderId($order->getId());
            $orderItem->setSupplierPositionId($supplierPosition->getId());
            $orderItem->setPrice($supplierPosition->getPrice());
            $orderItem->setNds(0);
            $orderItem->setQuantity(1);
            $orderItem->save();
        }

        return $order;
    }
}
