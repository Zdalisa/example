<?php

namespace Nsi\Model\DbMapper;

use DbTable_Users;
use Nsi\Model\DbMapper\Core\Mapper;
use Nsi\Model\DbMapper\PriceOrderPositionDocument as MapperPriceOrderPositionDocument;
use Nsi\Model\DbMapper\PositionAttribute;
use Nsi\Model\DbMapper\PriceOrderPosition;
use Nsi\Model\Exception\OrderException;
use Nsi\Model\PriceOrder\CustomerSuppliersGrid;
use Nsi\Model\PriceOrder\Notifier;
use Nsi\Model\Template\EmailTemplate;
use Zend_Db_Select;
use Nsi\Model\PriceOrder\Procurement as ProcurementService;
use Nsi\Model\DbMapper\Config;
use Nsi\Model\DbMapper\PriceOrderPositionAttribute;

/**
 * ценовой заказ
 *
 * @method int getId();
 * @method int getCustomerId();
 * @method int getUserId();
 * @method int getSupplierId();
 * @method string getDateCreated();
 * @method string getDateAgreed();
 * @method string getDateDelivery();
 * @method string getDateApproved();
 * @method string getDateClosed();
 * @method string getDeliveryAddress();
 * @method string getTitle();
 * @method int getStatus();
 * @method string getDeliveryConditions();
 * @method string getDecisionBasis();
 * @method int getWinnerSupplierId();
 * @method bool getIsManufacturer();
 * @method bool getInnovativeProduct();
 * @method int getType();
 * @method string getRejectReason();
 * @method string getApproveReason();
 *
 * @method PriceOrder setId(int);
 * @method PriceOrder setTitle(string);
 * @method PriceOrder setStatus(int);
 * @method PriceOrder setCustomerId(int);
 * @method PriceOrder setUserId(int);
 * @method PriceOrder setSupplierId(int);
 * @method PriceOrder setDateCreated(string $value);
 * @method PriceOrder setDateSent(string $value);
 * @method PriceOrder setDateResponse(string $value);
 * @method PriceOrder setDateDelivery(string $value);
 * @method PriceOrder setDeliveryRegions(string $value);
 * @method PriceOrder setDeliveryAddress(string $value);
 * @method PriceOrder setDeliveryConditions(string $value);
 * @method PriceOrder setDecisionBasis(string $value);
 * @method PriceOrder setWinnerSupplierId(int $value);
 * @method PriceOrder setIsManufacturer(bool $value);
 * @method PriceOrder setInnovativeProduct(bool $value);
 * @method PriceOrder setType(int $type);
 * @method PriceOrder setRejectReason(string $reason)
 * @method PriceOrder setApproveReason(string $reason)
 *
 * @method \Model_Contragent getCustomer();
 * @method \Model_Contragent getSupplier();
 * @method PriceOrderPosition[] getPositions();
 * @method PriceOrderSupplier getWinnerSupplier();
 * @method PriceOrderSupplier[] getSuppliers();
 * @method PriceOrderRegion[] getRegions();
 * @method \Nsi\Model\DbMapper\VocabPriceOrderType getVocabType();
 *
 * @method deleteBatchSuppliers()
 *
 */
class PriceOrder extends Mapper
{
    const TABLE_NAME = 'nsi_price_order';

    const STATUS_PROJECT = 1;
    /** Черновик. */
    const STATUS_SUPPLIER_REVIEW = 2;
    /** На рассмотрении поставщика. */
    const STATUS_SUPPLIER_DECLINED = 3;
    /** Отклонено поставщиком. */
    const STATUS_CUSTOMER_REVIEW = 4;
    /** На рассмотрении заказчика. */
    const STATUS_CUSTOMER_CANCELLED = 5;
    /** Отменено заказчиком. */
    const STATUS_IN_PROCESS = 6;
    /** На оформлении заказа. */
    const STATUS_OVERDUE = 7;
    /** Просрочено. */
    const STATUS_COMPLETED = 8;
    /** Исполнено. */
    const STATUS_NOT_COMPLETED = 9;
    /** Не исполнено. */
    const STATUS_CANCELED = 10;
    /** Аннулировано. */
    const STATUS_PENDING = 11;
    /** Ожидание снижения цен. */
    const STATUS_CHOOSE_WINNERS = 12;
    /** Выбор победителей. */

    const PARAM_ID = 'id';
    const PARAM_TYPE = 'type';
    const PARAM_STATUS = 'status';
    const PARAM_REJECT_REASON = 'reject_reason';
    const PARAM_APPROVE_REASON = 'approve_reason';
    const PARAM_TITLE = 'title';
    const PARAM_DELIVERY_CONDITIONS = 'delivery_conditions';
    const PARAM_WINNER_SUPPLIER_ID = 'winner_supplier_id';

    /**
     * Name of the log table
     *
     * @access public
     * @var String
     */
    //@codingStandardsIgnoreLine
    public $_logTable = 'nsi_price_order_history';

    /**
     * Конструктор.
     *
     * @param array $options Входные параметры.
     *
     * @return PriceOrder
     */
    protected function __construct(array $options = null)
    {
        parent::__construct($options);
        $this->_parameters[self::PARAM_DELIVERY_CONDITIONS]['default_value'] = $this->getDefaultDeliveryConditions();
    }

    /**
     * list of table fields which shall be mapped as model properties
     *
     * @var Array
     */
    protected $_parameters = array(
        self::PARAM_ID => array(
            'pseudo' => 'Идентификатор'
        ),
        'customer_id' => array(
            'pseudo' => 'ID заказчика'
        ),
        'user_id' => array(
            'pseudo' => 'ID пользователя, создавшего ЦЗ'
        ),
        'date_created' => array(
            'type' => self::TYPE_TIMESTAMP,
            'pseudo' => 'Дата создания'
        ),
        'title' => array(
            'pseudo' => 'Наименование'
        ),
        self::PARAM_STATUS => array(
            'pseudo' => 'Статус'
        ),
        'date_sent' => array(
            'type' => self::TYPE_TIMESTAMP,
            'pseudo' => 'Дата отправки, дата, когда ЦЗ был направлен Поставщикам'
        ),
        'date_response' => array(
            'type' => self::TYPE_TIMESTAMP,
            'pseudo' => 'Дата к которой необходимы ответы от поставщиков'
        ),
        'date_delivery' => array(
            'type' => self::TYPE_TIMESTAMP,
            'pseudo' => 'Дата к которой заказчик планирует получить заказ.'
        ),
        'delivery_address' => array(
            'pseudo' => 'Адрес поставки'
        ),
        self::PARAM_DELIVERY_CONDITIONS => array(
            'pseudo' => 'Условия доставки и оплаты'
        ),
        'decision_basis' => array(
            'pseudo' => 'Обоснование выбора поставщика'
        ),
        'winner_supplier_id' => array(
            'pseudo' => 'Идентификатор ЦЗ поставщика победителя'
        ),
        'is_manufacturer' => array(
            'pseudo' => 'Является производителем продукции'
        ),
        'innovative_product' => array(
            'pseudo' => 'Инновационная продукция'
        ),
        self::PARAM_TYPE => array(
            'pseudo' => 'Тип ценового запроса'
        ),
        self::PARAM_REJECT_REASON => array(
            'pseudo' => 'Причина отмены'
        ),
        self::PARAM_APPROVE_REASON => array(
            'pseudo' => 'Обоснование проведения закупки'
        )
    );

    /**
     * Table class for model data storage
     *
     * @var String
     */
    protected $_dbClass = 'Nsi\Model\DbTable\PriceOrder';

    /**
     * Текущие данные зависимостей
     *
     * @var array
     */
    protected $dependencies
        = array(
            'customer' => array(
                self::DEPENDENCE_CLASS => 'Model_Contragent',
                self::DEPENDENCE_IS_SINGLE => true,
                self::DEPENDENCE_TABLE_REFERENCE_NAME => 'Nsi\Model\DbTable\PriceOrder',
                self::DEPENDENCE_TABLE_REFERENCE_FIELD => 'Customer',
            ),
            'user' => array(
                self::DEPENDENCE_CLASS => 'Model_User',
                self::DEPENDENCE_IS_SINGLE => true,
                self::DEPENDENCE_TABLE_REFERENCE_NAME => 'Nsi\Model\DbTable\PriceOrder',
                self::DEPENDENCE_TABLE_REFERENCE_FIELD => 'User',
            ),
            'supplier' => array(
                self::DEPENDENCE_CLASS => 'Nsi\Model\DbMapper\PriceOrderSupplier',
                self::DEPENDENCE_IS_SINGLE => false,
                self::DEPENDENCE_TABLE_REFERENCE_NAME => 'Nsi\Model\DbTable\PriceOrderSupplier',
                self::DEPENDENCE_TABLE_REFERENCE_FIELD => 'PriceOrder',
            ),
            'positions' => array(
                self::DEPENDENCE_CLASS => 'Nsi\Model\DbMapper\PriceOrderPosition',
                self::DEPENDENCE_IS_SINGLE => false,
                self::DEPENDENCE_TABLE_REFERENCE_NAME => 'Nsi\Model\DbTable\PriceOrderPosition',
                self::DEPENDENCE_TABLE_REFERENCE_FIELD => 'PriceOrder',
            ),
            'winner_supplier' => array(
                self::DEPENDENCE_CLASS => 'Nsi\Model\DbMapper\PriceOrderSupplier',
                self::DEPENDENCE_IS_SINGLE => true,
                self::DEPENDENCE_TABLE_REFERENCE_NAME => 'Nsi\Model\DbTable\PriceOrderSupplier',
                self::DEPENDENCE_TABLE_REFERENCE_FIELD => 'WinnerSupplier',
            ),
            'vocab_type' => array(
                self::DEPENDENCE_CLASS => 'Nsi\Model\DbMapper\VocabPriceOrderType',
                self::DEPENDENCE_IS_SINGLE => true,
                self::DEPENDENCE_TABLE_REFERENCE_NAME => 'Nsi\Model\DbTable\PriceOrder',
                self::DEPENDENCE_TABLE_REFERENCE_FIELD => 'Type'
            ),
            'suppliers' => array(
                self::DEPENDENCE_CLASS => 'Nsi\Model\DbMapper\PriceOrderSupplier',
                self::DEPENDENCE_IS_SINGLE => false,
                self::DEPENDENCE_TABLE_REFERENCE_NAME => 'Nsi\Model\DbTable\PriceOrderSupplier',
                self::DEPENDENCE_TABLE_REFERENCE_FIELD => 'PriceOrder'
            ),
            'regions' => array(
                self::DEPENDENCE_CLASS => 'Nsi\Model\DbMapper\PriceOrderRegion',
                self::DEPENDENCE_IS_SINGLE => false,
                self::DEPENDENCE_TABLE_REFERENCE_NAME => 'Nsi\Model\DbTable\PriceOrderRegion',
                self::DEPENDENCE_TABLE_REFERENCE_FIELD => 'PriceOrder'
            )
        );

    protected $notifier = null;

    /**
     * Построение фильтров на основе переданных параметров
     *
     * @param $select
     * @param $params
     *
     * @return mixed
     */
    public static function createParamsCondition($select, $params)
    {
        if ($status = getParamAsInt($params, 'status')) {
            $select->where('"order".status = ?', $status);
        }
        if ($title = getParamAsString($params, 'title')) {
            $select->where('"order".title ilike ?', $title . '%');
        }
        if ($query = getParamAsString($params, 'query')) {
            $select->where('"order".title ilike ?', '%' . $query . '%');
        }

        $params_fields = array(
            'date_created_from',
            'date_sent_from',
            'date_response_from',
            'date_delivery_from',
            'date_created_till',
            'date_sent_till',
            'date_response_till',
            'date_delivery_till'
        );
        $filtered_params = array();
        foreach ($params_fields as $param) {
            if (isset($params[$param])) {
                $filtered_params[$param] = $params[$param];
            }
        }
        foreach ($params_fields as $item) {
            if (!empty($filtered_params[$item]) && (false !== strpos($item, '_till'))) {
                $tmp_date = getParamAsDate($filtered_params, $item);
                $tmp_date->addHour(23);
                $tmp_date->addMinute(59);
                $tmp_date->addSecond(59);
                $filtered_params[$item] = $tmp_date->toString(TIME_FORMAT_SQL);
            }
        }
        $select = prepareWhereStatement($select, $filtered_params, array(
            '"order"' => new PriceOrder()
        ));
        return $select;
    }

    /**
     * Проверка заполненности полей Информации о поставке ЦЗ
     *
     * @throws \ResponseException
     */
    protected function validatePriceOrderInfoFields()
    {
        $fields = array(
            'delivery_address' => 'Адрес поставки',
            'delivery_conditions' => 'Условия оплаты и доставки',
            'date_response' => 'Дата предоставления ответа на запрос',
            'date_delivery' => 'Дата поставки'
        );

        $price_order_data = $this->toArray();

        foreach ($fields as $field => $label) {
            expect(!empty($price_order_data[$field]), 'Не заполнено поле: ' . $label);
        }
        $deliveryRegions = PriceOrderRegion::loadByPriceOrder($this->getId());
        expect($deliveryRegions, 'Не заполнено поле: Регионы поставки');
    }

    /**
     * Отправка ЦЗ поставщикам.
     *
     * @param array $contragentIds ID контрагентов, которым будет отправлено цз.
     *
     * @return bool
     */
    public function send(array $contragentIds = null)
    {
        expect($this->getStatus() == self::STATUS_PROJECT, 'Ценовой запрос не в статусе Черновика');

        // Проверим заполненность полей Информации о поставке
        $this->validatePriceOrderInfoFields();
        //Прежде чем что-то делать, проверяем у всех ли позийий стоит поле quantity
        $this->validatePositionsQuantity();
        $suppliers = $this->findPriceOrderSuppliers($contragentIds);
        expect(count($suppliers), 'Нет поставщиков, которым можно отправить ценовой запрос');
        $data = array(
            'orderId' => $this->id
        );
        $priceOrderPositions = $this->getPositions();
        $factory = PriceOrderSupplier::getObjectClass();
        $factorySupplierPosition = PriceOrderSupplierPosition::getObjectClass();
        foreach ($suppliers as $supplier) {
            // Для каждого поставщика имеющего хотя бы одну позицию в ЦЗ создаем ЦЗ-поставщика.
            $data['contragent_id'] = $supplier['id'];
            $supplierOrder = $factory->update($data);

            foreach ($priceOrderPositions as $position) {
                /** @var PriceOrderPosition $position */
                if ($position->getDictionaryPositionId()) {
                    /**
                     * Каждую позицию ЦЗ-заказчика ищем в прайслисте поставщика,
                     * и если такая находится укажем ее в позиции ЦЗ-поставщика.
                     */
                    $supplierPricePosition = $this->getSupplierPriceItem(
                        $position->getDictionaryPositionId(),
                        $supplier['id']
                    );
                    $supplierPositionData = array(
                        'price_order_supplier_id' => $supplierOrder->getId(),
                        'price_order_position_id' => $position->getId(),
                        // По умолчанию считаем, что поставщик может доставить нам данную позицию.
                        'deliverable' => true,
                    );
                    if ($supplierPricePosition) {
                        $supplierPositionData['supplier_position_id'] = $supplierPricePosition['id'];
                        $supplierPositionData['price'] = $supplierPricePosition['price'];
                        $supplierPositionData['nds'] = $supplierPricePosition['nds'];
                        $supplierPositionData['minimum_quantity'] = $supplierPricePosition['minimum_quantity'];
                    } else {
                        // Если позиции нет у поставщика в прайсе, то он не может ее поставить.
                        $supplierPositionData['deliverable'] = false;
                    }
                } elseif ($position->getCategoryCode()) {
                    /**
                     * Если указан код категории, то также в позиции ЦЗ поставщика
                     * создаем одну запись, без указания идентификатора позиции поставщика.
                     * Соответствие с позициями поставщика, будет указана позже при выборе альтернативы.
                     */
                    $supplierPositionData = array(
                        'price_order_supplier_id' => $supplierOrder->getId(),
                        'price_order_position_id' => $position->getId(),
                        // По умолчанию считаем, что поставщик может доставить нам данную позицию.
                        'deliverable' => true,
                    );
                }
                $factorySupplierPosition->update($supplierPositionData);
            }
        }

        // Меняем статус ЦЗ-заказчика
        $this->setStatus(self::STATUS_SUPPLIER_REVIEW);
        $this->setDateSent(date('c'));
        $this->save();

        // Уведомляем участников о смене статуса
        $this->getNotifier()->statusChanged(self::STATUS_SUPPLIER_REVIEW);
        return true;
    }

    /**
     * Оформление заказа
     * @param int $winnerSupplierId Идкентификтор выбранного поставщика (он же считается победителем в ЦЗ).
     * @param array $priceOrderSupplierPositions Массив закупаемых позиций.
     *    array(
     *        id => int,
     *        quantity => int,
     *    ).
     *
     * @return bool
     * @throws OrderException
     * @throws \Exception
     * @throws \ResponseException
     */
    public function makeOrder($winnerSupplierId = null, array $priceOrderSupplierPositions = array())
    {
        if ($this->getType() != VocabPriceOrderType::PRICE_ORDER_TYPE_PROCUREMENT) {
            expect(
                $this->getStatus() == self::STATUS_CUSTOMER_REVIEW,
                'Ценовой запрос не в статусе Рассмотрения у заказчика'
            );
        }

        // Флаг разрешающий создавать несколько заказов для одного ЦЗ.
        $flagMultiOrder = ($winnerSupplierId && $priceOrderSupplierPositions);

        // Если мультизаказ.
        if ($flagMultiOrder) {
            $winner_supplier = PriceOrderSupplier::loadByPrimary($winnerSupplierId);

            // Иначе выбирается победитель из ЦЗ.
        } else {
            expect($this->getWinnerSupplierId(), 'Не выбран поставщик');

            /** @var PriceOrderSupplier $winner_supplier */
            $winner_supplier = $this->getWinnerSupplier();
        }

        // создаем заказ
        $order = Order::create();
        $order->setCustomerId($this->getCustomerId())
            ->setStatus(Order::STATUS_PROJECT)
            ->setDateCreated(new \DateTime())
            ->setPriceOrderId($this->getId())
            ->setTitle($this->getTitle())
            ->setPurchaseTarget($this->getTitle())
            ->setDateDelivery($this->getDateDelivery())
            ->setAddress($this->getDeliveryAddress())
            ->setChoiceReason($this->getDecisionBasis())
            ->setDeliveryCost($winner_supplier->getDeliveryPrice())
            ->setSupplierId($winner_supplier->getContragentId());

        if (!$flagMultiOrder) {
            $priceOrderSupplierPositions = $winner_supplier->getPositions();
        }
        // Создаем позиции заказа.
        $items = array();
        /** @var PriceOrderSupplierPosition $item */
        foreach ($priceOrderSupplierPositions as $item) {
            // Если мультизаказ.
            if ($flagMultiOrder) {
                $quantity = $item['quantity'];
                $item = PriceOrderSupplierPosition::loadByPrimary($item['id']);
            } else {
                $quantity = $item->getPriceOrderPosition()->getQuantity();
            }
            // Не добавляем в заказ позиции, которые поставщик отказался доставить
            // непонятна причина, но было так , что deliverable true, но пусто supplier_position_id
            if (!$item->getDeliverable() || !$item->getSupplierPositionId()) {
                continue;
            }
            //создаем позиции заказа
            $orderItem = OrderItem::create();
            $orderItem->setSupplierPositionId($item->getSupplierPositionId());
            $orderItem->setQuantity($quantity);
            $orderItem->setQuantityAccept($quantity);
            $orderItem->setPrice($item->getPrice());
            $orderItem->setNds($item->getNds());
            $orderItem->setOkeiCode($item->getPriceOrderPosition()->getOkeiCode());

            $items[] = $orderItem;
        }
        $order->setItems($items);
        $order->save();

        // Флаг на разрешение завершить формирование заказа(ов).
        $flagFinishExecuteOrder = true;

        // Если мультизаказ.
        if ($flagMultiOrder) {
            $totalQuantityOrder = $order->getTotalQuantity($this->getId());
            $totalQuantityPop = $item->getPriceOrderPosition()->getTotalQuantity($this->getId());
            $flagFinishExecuteOrder = ($totalQuantityOrder >= $totalQuantityPop);
        }

        if ($flagFinishExecuteOrder) {
            // Завершить формирование заказа.
            $this->finishExecuteOrder($flagMultiOrder);
        }
        return array('order_id' => $order->getId());
    }

    /**
     * Отмена ЦЗ заказчиком
     *
     * @return bool
     *
     * @throws \ResponseException
     */
    public function cancel()
    {
        expect($this->getCustomerId() == getActiveCompany(), 'Вы не являетесь автором ценового запроса');

        $valid_statuses = array(
            self::STATUS_SUPPLIER_REVIEW,
            self::STATUS_CUSTOMER_REVIEW
        );
        expect(in_array($this->getStatus(), $valid_statuses), 'В текущем статусе отмена ценового запроса недопустима');

        $this->setStatus(self::STATUS_CUSTOMER_CANCELLED);
        $this->save();

        // Уведомляем участников
        $this->getNotifier()->statusChanged(self::STATUS_CUSTOMER_CANCELLED);

        // Обновляем статусы ЦЗ поставщиков
        $suppliers = $this->getSupplier();
        /** @var PriceOrderSupplier $supplier */
        foreach ($suppliers as $supplier) {
            if ($supplier->getStatus() !== PriceOrderSupplier::STATUS_OVERDUE) {
                $supplier->setStatus(PriceOrderSupplier::STATUS_CLOSED);
                $supplier->save();
            }
        }

        return true;
    }

    /**
     * Поиск позиции в прайсе поставщика.
     *
     * @param int $dictionary_position_id Идентификатор справочника позиций.
     * @param int $supplier_id Идентификатор поставщика.
     *
     * @return mixed
     */
    protected function getSupplierPriceItem($dictionary_position_id, $supplier_id)
    {
        $db = getDbInstance();
        $db->setFetchMode(\Zend_Db::FETCH_ASSOC);

        $select = $db
            ->select()
            ->from(
                ['supplier_position' => SupplierPosition::TABLE_NAME],
                null
            )
            ->columns(
                [
                    'id' => 'supplier_position.' . SupplierPosition::PARAM_ID,
                    'nds' => 'supplier_position.' . SupplierPosition::PARAM_NDS,
                    'price' => 'COALESCE(supplier_position_cond.' . CondSupplierPosition::PRICE . ', 0)',
                    'minimum_quantity' => 'COALESCE(supplier_position_cond.' . CondSupplierPosition::MINIMUM_QUANTITY . ', 0)',
                ]
            )
            /**
             * Если нет условий поставок, то такие позиции поставщика,
             * нужно, все равно учитывать.
             */
            ->joinLeft(
                ['supplier_position_cond' => CondSupplierPosition::TABLE_NAME],
                'supplier_position_cond.' . CondSupplierPosition::POSITION_ID . ' = supplier_position.' . SupplierPosition::PARAM_ID,
                null
            )
            ->where('supplier_position.' . SupplierPosition::PARAM_DICTIONARY_POSITION_ID . ' = ?', $dictionary_position_id)
            ->where('supplier_position.' . SupplierPosition::PARAM_CONTRAGENT_ID . ' = ?', $supplier_id)
            ->where('supplier_position.' . SupplierPosition::PARAM_DELETED . ' = ?', 'FALSE')
            ->where('supplier_position.' . SupplierPosition::PARAM_IS_ACTUAL . ' = ?', 'TRUE')
            ->limit(1);

        $result = $db->fetchRow($select);
        return $result;
    }

    /**
     * Завершить формирование заказа(ов).
     *
     * @param bool $flagMultiOrder Флаг разрешающий создавать несколько заказов для одного ЦЗ.
     *
     * @return bool
     */
    public function finishExecuteOrder($flagMultiOrder = false)
    {
        expect($this->getCustomerId() == getActiveCompany(), 'Вы не являетесь автором ценового запроса');

        $winnderSupplierIds = array();
        // Если мультизаказ.
        if ($flagMultiOrder) {
            // Получение списка поставщиков у прямых заказов ЦЗ.
            $priceOrderSupplier = PriceOrderSupplier::getObjectClass();
            list($priceOrderSupplierIds) = $priceOrderSupplier->getSupplierOrder($this->getId());

            foreach ($priceOrderSupplierIds as $item) {
                array_push($winnderSupplierIds, $item['id']);
            }
        } else {
            array_push($winnderSupplierIds, $this->getWinnerSupplierId());
        }

        // Получаем статус ценового заказа.
        $priceOrderStatus = $this->getStatus();
        // Обновляем статус ЦЗ.
        $this->setStatus(self::STATUS_IN_PROCESS)->save();

        // Уведомляем участников.
        $this->getNotifier()->statusChanged(self::STATUS_IN_PROCESS);

        // Обновляем статусы ЦЗ поставщиков.
        $suppliers = $this->getSupplier();
        /** @var PriceOrderSupplier $supplier */
        foreach ($suppliers as $item) {
            if (in_array($item->getId(), $winnderSupplierIds)) {
                $status = PriceOrderSupplier::STATUS_IN_PROCESS;
            } elseif ($priceOrderStatus == PriceOrder::STATUS_CUSTOMER_REVIEW) {
                /**
                 * Если ценовой запрос в статусе "На рассмотрении у заказчика",
                 * то при завершении заказа переводим заказ в статус "Ответ"
                 */
                $status = PriceOrderSupplier::STATUS_RESPONSE;
            } elseif ($item->getStatus() !== PriceOrderSupplier::STATUS_OVERDUE) {
                $status = PriceOrderSupplier::STATUS_CLOSED;
            } else {
                $status = PriceOrderSupplier::STATUS_OVERDUE;
            }
            $item->setStatus($status)->save();
        }
        return true;
    }

    /**
     * Получение запроса для выборки id прямых заказов
     *
     * @param string $select Селект.
     *
     * @return Zend_Db_Select $select
     */
    private function getSelectOrderIds($select)
    {
        $db = getDbInstance();
        $db->setFetchMode(\Zend_Db::FETCH_ASSOC);

        $selectOrderIds = $db
            ->select()
            ->from(
                array('ord' => Order::TABLE_NAME),
                array(Order::PARAM_ID)
            )
            ->where('ord.' . Order::PARAM_PRICE_ORDER_ID . ' = "order".' . self::PARAM_ID);

        $select->columns(
            array(
                'order_id' => new \Zend_Db_Expr(
                    "ARRAY(" . $selectOrderIds->__toString() . ")"
                ),
            )
        );

        return $select;
    }

    /**
     * Получить суммарное количество неисполненных и отклоненных поставщиком заказов.
     *
     * @param int $id Идентификатор процедуры закупки.
     *
     * @return int Количество неисполненных заказов.
     */
    public function getNotExecutedOrDeclinedOrdersCount($id)
    {
        $db = getDbInstance();

        $select = $db->select()
            ->from(Order::TABLE_NAME, array('count' => 'COUNT(id)'))
            ->where(Order::PARAM_PRICE_ORDER_ID . ' = ?', (int)$id);

        $orWhere = array(
            $db->quoteInto(Order::PARAM_STATUS . ' = ?', Order::STATUS_NOT_MADE),
            $db->quoteInto(Order::PARAM_STATUS . ' = ?', Order::STATUS_DECLINED_BY_SUPPLIER)
        );
        $select->where(join(' OR ', $orWhere));
        $result = $select->query()->fetch();

        return $result['count'];
    }

    /**
     * Выставление статуса поставщикам в процедуре закупки.
     *
     * @param int $id Идентификатор процедуры закупки.
     * @param int $suppliersStatus Статус записей во вкладке поставщиков.
     *
     * @return $this
     */
    public function updateSuppliersStatus($id, $suppliersStatus)
    {
        $db = getDbInstance();

        $db->update(
            PriceOrderSupplier::TABLE_NAME,
            array(PriceOrderSupplier::PARAM_STATUS => $suppliersStatus),
            PriceOrderSupplier::PARAM_PRICE_ORDER_ID . ' = ' . (int)$id
        );

        return $this;
    }
}
