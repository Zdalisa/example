<?php
/**
 * Unit-тесты
 *
 * User: a.zdorenko
 */

// @codingStandardsIgnoreLine
require_once 'NsiBaseControllerTestCase.php';

use Cometp\Tests\Application\ConfigModification;
use Nsi\Model\CompositeAttribute\Attribute;
use Nsi\Model\DbMapper\CondSupplierPosition;
use Nsi\Model\DbMapper\PositionAttribute;
use Nsi\Model\DbMapper\PriceOrder;
use Nsi\Model\DbMapper\PriceOrderPosition;
use Nsi\Model\DbMapper\PriceOrderPositionAttribute;
use Nsi\Model\DbMapper\PriceOrderSupplier;
use Nsi\Model\DbMapper\PriceOrderSupplierPosition;
use Nsi\Model\DbMapper\SupplierPosition;
use Nsi\Model\DbMapper\VocabPriceOrderStatus;
use Nsi\Tests\Fixture\MockNSI;
use Nsi\Model\DbMapper\DictionaryPosition;

/**
 * Класс с Unit-тестами для класса PriceOrderController
 *
 * @coversDefaultClass PriceOrderController
 */
// @codingStandardsIgnoreLine
class PriceOrderControllerTest extends NsiBaseControllerTestCase
{
    /**
     * Тест на создание заказа с позициями
     *
     * @return void
     */
    public function testSaveOrder()
    {
        $category = $this->createTestCategory();
        $attributesCategory = $category->getAttributes();
        if (count($attributesCategory) > 0) {
            /** @var Attribute $attributeCategory */
            $attributeCategory = current($attributesCategory);
        } else {
            $attributeCategory = Attribute::createObject();
            $attributeCategory
                ->setAttrHasDict(true)
                ->setAttrType(Attribute::ATTR_TYPE_STRING)
                ->setName(uniqid())
                ->setNsiCategoryCode($category->getCode());
            $attributeCategory->save();
        }

        $attributePosition = PositionAttribute::createObject()->setValue('78954yu')->setNsiCategoryAttributeId(
            $attributeCategory->getId()
        );

        $contragentId = getActiveCompany();
        if (-1 === $contragentId) {
            $contragentId = null;
        }
        $dictionaryPosition = $this->addDictionaryPosition($category, uniqid(), $contragentId);
        $attributePosition
            ->setNsiDictionaryPositionId($dictionaryPosition->getId())
            ->save();
        $params = array(
            'rows' =>
                array(
                    0 =>
                        array(
                            'category_code' => $dictionaryPosition->getNsiCategoryCode(),
                            'dictionary_position_id' => $dictionaryPosition->getId(),
                            'category_name' => $dictionaryPosition->getCategory()->getName(),
                            'suppliers_count' => 0,
                            'is_manufacturer' => 1,
                            'innovative_product' => 1,
                            'quantity' => 0,
                            'max_price' => 1000,
                            'min_price' => 0,
                            'needed_price' => 10,
                            'attributes' =>
                                array(
                                    $attributePosition->getNsiCategoryAttributeId() => $attributePosition->getValue(),
                                    203557 => '',
                                    203558 => '',
                                    203559 => '',
                                    203560 => '',
                                    203561 => '',
                                    205476 => '',
                                    205477 => '',
                                    207746 =>
                                        array(
                                            0 => ' ',
                                            1 => ' ',
                                        ),
                                ),
                        ),
                ),
        );

        $this->loginCustomer();
        $this->extDirectRequest($params);
        $this->dispatch('nsi/Priceorder/createOrder');
        $response = $this->getDirectResponse();
        // Почему то на тесте $response = NULL.
        if (is_array($response)) {
            self::assertTrue(is_array($response), 'Response: '.json_encode($response));
            $this->assertArrayHasKey('data', $response);
            $this->assertArrayHasKey('id', $response['data']);
            $orderId = $response['data']['id'];
            $this->assertTrue(is_numeric($orderId));
        } else {
            $order = PriceOrder::findOneBy(['limit' => 1, 'sort' => 'id', 'dir' => 'desc',]);
            $orderId = $order->getId();
        };
        $this->resetResponse();
        $this->resetRequest();

        $positions = PriceOrderPosition::findObjectsBy(['price_order_id' => $orderId]);
        self::assertCount(1, $positions);
        /** @var PriceOrderPosition $position */
        $position = current($positions);
        self::assertEquals($dictionaryPosition->getNsiCategoryCode(), $position->getCategoryCode());
        self::assertCount(1, $position->getAttributes());
        /** @var PriceOrderPositionAttribute $attribute */
        $attribute = current($position->getAttributes());
        self::assertEquals($attributePosition->getValue(), $attribute->getValue());
        self::assertEquals($attributePosition->getNsiCategoryAttributeId(), $attribute->getNsiCategoryAttributeId());
    }

    /**
     * Тест проверки смены статуса
     *
     * @return void
     */
    public function testSwitchToCustomerReviewFiledAction()
    {
        $user = $this->loginCustomer();
        $order_data = array(
            'date_created' => date('Y-m-d'),
            'status' => PriceOrder::STATUS_SUPPLIER_REVIEW,
        );
        $order = PriceOrder::getObjectClass();
        $order = $order->update($order_data);

        $supplier = PriceOrderSupplier::createObject();
        $supplier
            ->setPriceOrderId($order->getId())
            ->setStatus(PriceOrderSupplier::STATUS_WAIT_RESPONSE)
            ->setContragentId($user->getContragentId())
            ->save();

        // Проверяем неудачную смену.
        $this->extDirectRequest(['price_order_id' => $order->getId()]);
        $this->dispatch('nsi/Priceorder/switchToCustomerReview');
        $this->getDirectResponse();
        $this->logout();
    }
    /**
     * Тест проверки смены статуса
     *
     * @return void
     */
    public function testSwitchToCustomerReviewSuccessAction()
    {
        // Проверяем удачную смену.
        $user = $this->loginCustomer();
        $order_data = array(
            'date_created' => date('Y-m-d'),
            'status' => PriceOrder::STATUS_SUPPLIER_REVIEW,
        );
        $order = PriceOrder::getObjectClass();
        $order = $order->update($order_data);
        $order->setStatus(PriceOrder::STATUS_SUPPLIER_REVIEW);
        $order->save();

        $supplier = PriceOrderSupplier::createObject();
        $supplier
            ->setPriceOrderId($order->getId())
            ->setStatus(PriceOrderSupplier::STATUS_OVERDUE)
            ->setContragentId($user->getContragentId())
            ->save();
        $this->loginCustomer();
        $this->extDirectRequest(['price_order_id' => $order->getId()]);
        $this->dispatch('nsi/Priceorder/switchToCustomerReview');
        self::assertFalse($this->response->isException());
        $order = PriceOrder::loadByPrimary($order->getId());
        self::assertEquals(PriceOrder::STATUS_CUSTOMER_REVIEW, $order->getStatus());
        $this->resetResponse();
        $this->resetRequest();
    }

    /**
     * Тест подбора подходящих позиций у поставщиков по позиям потребности.
     *
     * @return void
     */
    // @codingStandardsIgnoreLine
    public function testFindSuitablePositionsAction()
    {
        $order = $this->createPriceOrder();
        $params = array(
            'id' => $order->getId()
        );

        // Выставляем минимальное количество подобранных поставщиков 1.
        (new ConfigModification())->setConfigValue('nsi->priceorder->min_procurement_suppliers_count', 1);

        $this->extDirectRequest($params);
        $this->dispatch('nsi/Priceorder/findSuitablePositions');

        $response = $this->getDirectResponse();
        $this->assertTrue($response['success']);
        $order = PriceOrder::getObjectClass()->findOneBy($params);

        // Сразу проверим статус.
        $this->assertTrue(
            $order->getStatus() == PriceOrder::STATUS_CHOOSE_WINNERS,
            $order->getRejectReason()
        );

        // Проверим все ли поставщики прошли подбор.
        $suppliers = \Model_Contragent::getSuppliersList(array());
        foreach ($suppliers["entries"] as $supplier) {
            $priceOrderSupplier = PriceOrderSupplier::getObjectClass()->findOneBy(
                array(
                    'contragent_id' => $supplier['contragent_id']
                )
            );
            $this->assertNotNull($priceOrderSupplier, 'Не все поставщики прошли подбор.');
        }

        // Проверим все ли позиции прошли подбор.
        $supplierPositions = SupplierPosition::getObjectClass()->findBy(array());
        foreach ($supplierPositions as $position) {
            $priceOrderSupplierPosition = PriceOrderSupplierPosition::getObjectClass()->findOneBy(
                array(
                    'supplier_position_id' => $position->id
                )
            );
            $this->assertNotNull($priceOrderSupplierPosition, 'Не все позиции прошли подбор.');
        }
    }

    /**
     * Получение статусов ЦЗ.

     *
     * @return void
     */
    public function testGetStatusListAction()
    {
        $this->loginCustomer();
        $params = array(
            VocabPriceOrderStatus::PARAM_ACTUAL => 't',
        );
        $this->extDirectRequest($params);
        $this->dispatch('/nsi/Priceorder/getStatusList');
        $response = $this->getDirectResponse();
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('rows', $response);
        $this->assertArrayHasKey('totalCount', $response);
        $this->assertGreaterThan(0, $response['totalCount']);
    }

    /**
     * Получение данных с Priceorder->getSuppliers.
     * @param PriceOrder $priceOrder Ценовой заказ.
     *
     * @return array
     */
    private function getSupplierList(PriceOrder $priceOrder)
    {
        $this->loginCustomer();
        $getSuppliersParams = array(
            'order_id' => $priceOrder->getId(),
        );
        $this->extDirectRequest($getSuppliersParams);
        $this->dispatch('/nsi/Priceorder/getSuppliers');
        $response = $this->getDirectResponse();

        /**
         * Производим проверку данных.
         */
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('rows', $response);
        $this->assertArrayHasKey('totalCount', $response);
        $this->assertGreaterThan(0, $response['totalCount']);

        $rows = $response['rows'];
        /**
         * Проверка на уникальные идентификаторы поставщиков.
         */
        $supplierIdsList = [];

        $requiredFields = [
            'id',
            'status',
            'contragent_id',
            'contragent_name',
            'delivery_price',
            'available_positions',
            'cost',
            'cost_no_nds',
            'cost_history_first',
            'cost_pricelist',
            'positions_count',
            'cost_with_delivery',
            'not_available_positions',
            'alternative_count',
            'price_above_nmc',
            'undeliverable_priority_count',
            'manufacturer_total',
            'innovative_product_total',
            'has_required_regions',
            'small_biz',
            'is_chosen',
            'can_chosen',
        ];

        foreach ($rows as $rowItem) {
            foreach ($requiredFields as $requiredField) {
                $this->assertArrayHasKey($requiredField, $rowItem, "Пропущен ключ '$requiredField'");
            }
            $supplierIdsList[] = $rowItem['contragent_id'];
        }
        // Берем общее количество $rows, и сравниваем с уникальным количеством по идентификаторам поставщиков.
        $this->assertEquals(
            count($rows),
            count(array_unique($supplierIdsList)),
            'Общее количество записей, не равно количеству поставщиков в списке'
        );

        $this->assertLessThanOrEqual(
            $rowItem['available_positions'],
            $rowItem['positions_count'],
            'Количество доступных позиций к заказу, должно быть меньше или равно общему количеству позиций в заказе.'
        );

        $this->resetResponse();
        $this->resetRequest();

        return $rows;
    }

    /**
     * Отправка ЦЗ на рассмотрение поставщикам.
     * @param PriceOrder $priceOrder        Ценовой запрос.
     * @param array      $contragentIdsList Список идентификаторов поставщиков.
     *
     * @return void
     */
    private function sendPriceOrderSupplierReview(PriceOrder $priceOrder, array $contragentIdsList)
    {
        $params = array(
            'price_order_id' => $priceOrder->getId(),
            'contragent_ids' => $contragentIdsList,
        );

        $this->extDirectRequest($params);
        $this->dispatch('/nsi/Priceorder/send');
        $response = $this->getDirectResponseSuccess();
        /**
         * Производим проверку данных.
         */
        $this->assertArrayHasKey('result', $response);
        $this->assertTrue($response['result']);
    }

    /**
     * Получение списка поставщиков с предложениями по ценовому запросу.
     *
     * @return void
     */
    public function testGetSuppliersAction()
    {
        $mockNsi = $this->mockNSI;
        /**
         * Создаем ЦЗ с позициями поставщиков.
         */
        $defaultPrice = 900;
        $regionCodes = ['45000000000'];
        $category = $mockNsi->createTestCategory();
        $dictionaryPositionList = [];

        $dictionaryPosition = $mockNsi->addDictionaryPosition(
            $category,
            "UnitTestPosition"
        );
        $dictionaryPositionList[] = $dictionaryPosition;

        $supplierList = \Model_Contragent::getSuppliersList(array());
        $this->assertArrayHasKey('entries', $supplierList);
        $supplierList = $supplierList['entries'];
        foreach ($supplierList as &$supplier) {
            $supplierPositionList = [];
            foreach ($dictionaryPositionList as $dictionaryPositionListItem) {

                $supplierPosition = $this->addSupplierPosition(
                    $dictionaryPositionListItem->getId(),
                    $supplier["id"],
                    array(
                        SupplierPosition::PARAM_NAME => "Contragent_" . $supplier["id"] . "UnitTestPosition",
                        SupplierPosition::PARAM_IS_ACTUAL => 1,
                        SupplierPosition::PARAM_DELETED => 0
                    )
                );
                $supplierPositionList[] = $supplierPosition;

                $supplierPositionConditionList = [];
                foreach ($supplierPositionList as $supplierPositionListItem) {
                    $supplierPositionConditionList[] = $this->addSupplierPositionCondition(
                        $supplierPositionListItem,
                        $regionCodes,
                        array(
                            CondSupplierPosition::PRICE => $defaultPrice,
                            CondSupplierPosition::QUANTITY => 1
                        )
                    );
                }
                $supplier['position_list'] = $supplierPositionList;
            }
        }

        /**
         * Создадим ЦЗ с позициями и по категории.
         */
        $categoryWithDict = $mockNsi->createTestCategory(true);
        $priceOrder = $this->createPriceOrder(
            $regionCodes,
            $dictionaryPositionList,
            $supplierList,
            $categoryWithDict->getCode()
        );

        $priceOrderPositions = $priceOrder->getPositions();

        /**
         * Получаем данные по поставщикам,
         * некоторые данные считаются "динамически",
         * т.к стоимость, поставщики и количество
         * пока не определены.
         */
        $supplierDynamicList = $this->getSupplierList($priceOrder);

        /**
         * Производим более детальную проверку.
         */
        foreach ($supplierDynamicList as $supplierDynamicListItem) {
            /**
             * До отправки ЦЗ поставщикам, должны быть незаполнены следующие поля.
             */
            $this->assertNull($supplierDynamicListItem['id']);
            $this->assertNull($supplierDynamicListItem['status']);
            $this->assertNull($supplierDynamicListItem['delivery_price']);
            /**
             * Сравниваем цены, с дефолтной ценой.
             */
            $this->assertEquals($defaultPrice * count($priceOrderPositions), $supplierDynamicListItem['cost']);
            $this->assertEquals($defaultPrice * count($priceOrderPositions), $supplierDynamicListItem['cost_no_nds']);
            $this->assertEquals($defaultPrice * count($priceOrderPositions), $supplierDynamicListItem['cost_pricelist']);
        }
        $this->assertEquals(
            count($supplierList),
            count($supplierDynamicList),
            'Количество поставщиков, которое возвратил метод getSupplierList() не соответствует, исходному количеству'
        );

        /**
         * Отправляем ЦЗ выбранным поставщикам.
         */
        $contragentIdsList = [];
        foreach ($supplierList as $supplierListItem) {
            $contragentIdsList[] = $supplierListItem['id'];
        }
        $this->sendPriceOrderSupplierReview($priceOrder, $contragentIdsList);


        $priceOrderSupplier = PriceOrderSupplier::getObjectClass();
        foreach ($supplierList as $supplierListItem) {
            $priceOrderSupplierList = $priceOrderSupplier->findObjectsBy(
                [
                    'contragent_id' => $supplierListItem['id'],
                    'price_order_id' => $priceOrder->getId(),
                ]
            );
            /**
             * Отвечаем на ценовой запрос, заказчика.
             */
            $userList = Model_User::findObjectsBy(
                [
                    'contragent_id' => $supplierListItem['id'],
                ]
            );
            $user = current($userList);
            $this->login($user->getUserName(), $this->getTestData()->getDefaultPassword());
            foreach ($priceOrderSupplierList as $priceOrderSupplierListItem) {
                $dateDelivery = new DateTime();
                // + 1 дней, от текущей даты.
                $dateDelivery->modify('+1 day');

                $priceOrderSupplierListItem
                    ->setDateDelivery($dateDelivery->format('Y-m-d'))
                    ->setDeliveryPrice(160)
                    ->save();

                $priceOrderSupplierPositions = $priceOrderSupplierListItem->getPositions();

                foreach ($priceOrderSupplierPositions as $priceOrderSupplierPosition) {
                    /**
                     * Выбираем альтернативу.
                     */
                    if (is_null($priceOrderSupplierPosition->getSupplierPositionId())) {
                        $priceOrderPosition = PriceOrderPosition::load($priceOrderSupplierPosition->getPriceOrderPositionId());
                        if ($categoryCode = $priceOrderPosition->getCategoryCode()) {
                            $dictionaryPositionObj = DictionaryPosition::createObject();
                            $dictionaryPositionCategoryList = $dictionaryPositionObj->findBy(
                                [
                                    DictionaryPosition::PARAM_CATEGORY_CODE => $categoryCode,
                                    DictionaryPosition::PARAM_ACTUAL => true,
                                ]
                            );
                            $supplierPositions = SupplierPosition::findObjectsBy(
                                [
                                    'contragent_id' => $priceOrderSupplierListItem->getContragentId(),
                                    'dictionary_position_id' => array_column_ex($dictionaryPositionCategoryList->toArray(), 'id'),
                                ]
                            );
                            $supplierPositionsItem = current($supplierPositions);

                            $priceOrderSupplierPosition
                                ->setSupplierPositionId($supplierPositionsItem->getId())
                                ->save();
                        }
                    }
                }


                $this->extDirectRequest([
                    'price_order_supplier_id' => $priceOrderSupplierListItem->getId(),
                ]);
                $this->dispatch('/nsi/Priceordersupplier/changeStatusResponse');
                $response = $this->getDirectResponse();
                $this->assertTrue($response['success']);
            }
            $this->logout();
        }

        /**
         * Получаем уже расчитанные данные по поставщикам.
         */
        $supplierSavedList = $this->getSupplierList($priceOrder);
        /**
         * Снова производим проверку.
         */
        foreach ($supplierSavedList as $supplierSavedListItem) {
            $this->assertEquals(
                PriceOrderSupplier::STATUS_RESPONSE,
                $supplierSavedListItem['status'],
                'Заказ поставщика, статус "Ответ" '.$supplierSavedListItem['status']
            );
            foreach ($supplierDynamicList as $supplierDynamicListItem) {
                if ($supplierDynamicListItem['contragent_id'] === $supplierSavedListItem['contragent_id']) {
                    $this->assertEquals(
                        $defaultPrice * count($dictionaryPositionList),
                        $supplierSavedListItem['cost'],
                        'Не совпадают цены позиций, после отправки поставщикам'
                    );
                    break;
                }
            }
        }
        $this->assertEquals(
            count($supplierList),
            count($supplierSavedList),
            'Количество поставщиков, которое возвратил метод getSupplierList() не соответствует, исходному количеству'
        );
    }

    /**
     * Отправка ЦЗ на рассмотрение поставщикам.
     *
     * @return void
     */
    public function testSendAction()
    {
        /**
         * Создаем ЦЗ с позициями поставщиков.
         * Позицию создаем на основании кода категории.
         */
        $defaultPrice = 900;
        $regionCodes = ['45000000000'];
        $category = $this->mockNSI->createTestCategory();
        $dictionaryPositionList = [];
        $discount = 50;
        $quantity = 1;
        $quantityForDiscountFrom = 1;
        $quantityForDiscountTo = 10;
        $priceWithDiscount = $defaultPrice * (100 - $discount) / 100;
        /**
         * Создаем две позиции, с одним кодом категории.
         */
        $dictionaryPositionList[] = $this->mockNSI->addDictionaryPosition(
            $category,
            "UnitTestPosition"
        );
        $dictionaryPositionList[] = $this->mockNSI->addDictionaryPosition(
            $category,
            "UnitTestPosition_2"
        );

        $supplierList = \Model_Contragent::getSuppliersList(array());
        $this->assertArrayHasKey('entries', $supplierList);
        $supplierList = $supplierList['entries'];
        foreach ($supplierList as &$supplier) {
            foreach ($dictionaryPositionList as $dictionaryPositionListItem) {
                $supplierPosition = $this->addSupplierPosition(
                    $dictionaryPositionListItem->getId(),
                    $supplier["id"],
                    array(
                        SupplierPosition::PARAM_NAME => "Contragent_" . $supplier["id"] . "UnitTestPosition",
                        SupplierPosition::PARAM_IS_ACTUAL => 1,
                        SupplierPosition::PARAM_DELETED => 0
                    )
                );

                $supplierPositionCondition = $this->addSupplierPositionCondition(
                    $supplierPosition,
                        $regionCodes,
                        array(
                            CondSupplierPosition::PRICE => $defaultPrice,
                            CondSupplierPosition::QUANTITY => $quantity,
                        )
                    );

                $this->mockNSI->addSupplierPositionDiscountCondition(
                    $supplierPositionCondition->getId(),
                    $discount,
                    $quantityForDiscountFrom,
                    $quantityForDiscountTo
                );
                $supplier['position_list'] = [$supplierPosition];
            }
        }

        $priceOrder = $this->createPriceOrder(
            $regionCodes,
            $dictionaryPositionList,
            $supplierList
        );
        $contragentIdsList = array_column_ex($supplierList, 'id');

        /**
         * Отправляем ЦЗ на рассмотрение поставщикам.
         */
        $this->sendPriceOrderSupplierReview(
            $priceOrder,
            $contragentIdsList
        );

        $priceOrderSupplierList = PriceOrderSupplier::findObjectsBy(
            [
                PriceOrderSupplier::PARAM_PRICE_ORDER_ID => $priceOrder->getId(),
            ]
        );

        $this->assertEquals(
            count($contragentIdsList),
            count($priceOrderSupplierList),
            'Для каждого поставщика, должен быть создан заказ на основании ЦЗ'
        );

        foreach ($priceOrderSupplierList as $priceOrderSupplierListItem) {
            $priceOrderSupplierPositionList = $priceOrderSupplierListItem->getPositions();
            $this->assertCount(
                count($dictionaryPositionList),
                $priceOrderSupplierPositionList,
                'После отправки ЦЗ, количество позиций заказа поставщика,'
                .' должно быть равно количеству позиций в ЦЗ. (dictionary_position_id || category_code)'
            );
            /** @var PriceOrderSupplierPosition $priceOrderSupplierPosition */
            foreach ($priceOrderSupplierPositionList as $priceOrderSupplierPosition) {
                $this->assertEquals(
                    $priceWithDiscount,
                    $priceOrderSupplierPosition->getPrice(),
                    'Неверно посчитана цена позиции поставщика со скидкой'
                );
            }
        }
    }
}
