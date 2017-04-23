<?php
use Cometp\Library\Core\Helper\MultiUploadPanel;
use Nsi\Model\DbMapper\PriceOrder;
use Nsi\Model\DbMapper\PriceOrderDocument;
use Nsi\Model\DbMapper\PriceOrderPosition;
use Nsi\Model\DbMapper\PriceOrderPositionDocument;
use Nsi\Model\DbMapper\PriceOrderRegion;
use Nsi\Model\DbMapper\PriceOrderSupplier;
use Nsi\Model\DbMapper\PriceOrderSupplierDocument;
use Nsi\Model\DbMapper\PriceOrderSupplierPosition;
use Nsi\Model\DbMapper\SupplierPosition;
use Nsi\Model\DbMapper\Syslog;
use Nsi\Model\PriceOrder\Cron;
use Nsi\Model\PriceOrder\CustomerPositionsGrid;
use Nsi\Model\PriceOrder\CustomerSuppliersGrid;
use Nsi\Model\PriceOrder\Procurement as ProcurementService;
use Nsi\Model\PriceOrder\SupplierPositionsGrid;
use Nsi\Model\DbMapper\VocabPriceOrderStatus;
use Nsi\Model\PriceOrder\Workflow;

/**
 * Работа с заказами
 *
 * @property Zend_View view
 */
class Nsi_PriceorderController extends Core_Controller_Action
{

    /**
     * Получение списка ЦЗ
     *
     * @remotable
     */
    public function getListAction(array $params)
    {
        list($result, $count) = PriceOrder::getList($params);
        $this->view->rows = $result;
        $this->view->totalCount = $count;
        $this->view->success = true;
    }

    /**
     * Обновление  ЦЗ
     *
     * @remotable
     *
     * @param array $params
     *
     * @throws ResponseException
     */
    public function updateAction(array $params)
    {
        $order_data = !empty($params['rows']) ? $params['rows'] : $params;
        $order = PriceOrder::getObjectClass();
        $order = $order->update($order_data);
        $this->view->rows = $order->toArray();
        $this->view->success = true;
    }

    /**
     * Удаление ЦЗ
     *
     * @remotable
     *
     * @param array $params
     *
     * @throws ResponseException
     */
    public function deleteAction(array $params)
    {
        $order = PriceOrder::loadByPrimary($params['rows']);
        $order->remove();
        $this->view->rows = array();
        $this->view->success = true;
    }


    /**
     * Отмена ЦЗ заказчиком
     *
     * @remotable
     *
     * @param array $params Параметры запроса.
     *
     * @return void
     *
     * @throws ResponseException
     */
    public function cancelAction(array $params)
    {
        $order_id = getParamAsInt($params, 'price_order_id');
        expect($order_id, 'Не передан идентификатор ценового запроса');
        $order = PriceOrder::loadByPrimary($order_id);
        expect($order, 'Ошибка при загрузке объекта ценового запроса');
        $this->view->result = $order->cancel();
    }

    /**
     * Отправление ЦЗ поставщикам
     *
     * @remotable
     *
     * @param array $params
     *
     * @throws ResponseException
     */
    public function sendAction(array $params)
    {
        $order_id = getParamAsInt($params, 'price_order_id');
        expect($order_id, 'Не передан идентификатор ценового запроса');
        $contragentIds = getParamAsArray($params, 'contragent_ids', null);
        $order = PriceOrder::loadByPrimary($order_id);
        $this->view->result = $order->send($contragentIds);
        $this->view->success = true;
    }

    /**
     * Оформление заказа
     *
     * @remotable
     *
     * @param array $params
     *
     *
     * @throws ResponseException
     */
    public function makeOrderAction(array &$params)
    {
        $order_id = getParamAsInt($params, 'id');
        expect($order_id, 'Не передан идентификатор ценового запроса');
        $order = PriceOrder::loadByPrimary($order_id);
        $result = $order->makeOrder();
        $this->view->result = $result;
        $this->view->success = true;
        $params[Syslog::PARAM_ORDER_ID_ALIAS] = $result[Syslog::PARAM_ORDER_ID_ALIAS];
    }

    /**
     * Получение всех  Items поставщика для  заказа
     *
     * @remotable
     *
     * @param array $params
     *
     * @throws ResponseException
     */
    public function getSupplierItemsAction(array $params)
    {
        $orderId = getParamAsInt($params, 'order_id');
        expect($orderId, 'Не передан идентификатор ценового запроса');
        unset($params['order_id']);
        $order = PriceOrder::loadByPrimary($orderId);
        if (isAdminEtp() || $order->isCustomerOrCuratorKim(getActiveCompany())) {
            $supplierId = getParamAsInt($params, 'supplier_id');
            expect($supplierId, 'Не передан идентификатор поставщика');
            expect($order->getStatus() !== PriceOrder::STATUS_PROJECT, 'Информация о позициях поставщика недоступна на данном этапе');
        } else {
            $supplierId = getActiveCompany();
        }
        $grid = new SupplierPositionsGrid($orderId, $supplierId);
        list($result, $count) = $grid->getList($params);
        $this->view->rows = $result;
        $this->view->totalCount = $count;
        $this->view->success = true;
    }

    /**
     * Если даны все ответы от поставщиков, у заказчика появляется кнопка для перевода на следующий этап, не дожидаясь
     * окончания отпущенного времени.
     *
     * @param array $params Параметы заказа.
     *
     * @remotable
     *
     * @return void
     * @throws ResponseException Выводим ошибку пользователю.
     */
    public function switchToCustomerReviewAction(array $params)
    {
        $orderId = getParamAsInt($params, 'price_order_id');
        $cron = new Cron();
        $isAllow = $cron->isSupplierReviewAllow($orderId);
        if ($isAllow !== true) {
            throw  new ResponseException(
                sprintf(
                    'Вы не можете вязть на рассмотрение Заказ %d, так как он не расмотрен всеми поставщиками',
                    $orderId
                )
            );
        }
        $cron->switchToCustomerReview($orderId);
        $this->view->success = true;
    }

    /**
     * Производит поиск подходящих позиций у поставщиков по позиям потребности.
     *
     * @param array $params Параметры запроса.
     *
     * @return void
     *
     * @remotable
     */
    public function findSuitablePositionsAction(array $params = array())
    {
        \DbTransaction::start();
        try {
            /** @var PriceOrder */
            $priceOrder = PriceOrder::findOneBy($params);
            $regions = $priceOrder->getRegions();

            expect(!empty($regions), 'Не заполнено обязательно поле "Регионы".');

            /** @var PriceOrderPosition[] */
            $priceOrderPositions = $priceOrder->getPositions();
            foreach ($priceOrderPositions as $position) {
                expect(($position->getQuantity() > 0), 'Количество у позиций должно быть установлено.');
            }

            $procurementService = new ProcurementService();
            $procurementService->process($priceOrder);

            if ($procurementService->priceReduceRequestSended) {
                $this->view->message = 'Поставщикам отправлены дозапросы на снижение цен.';
            }

            \DbTransaction::commit();

            \fireEvent('priceOrderStatusChanged', $priceOrder);

            $this->view->success = true;
        } catch (\Exception $e) {
            \DbTransaction::rollback(false);
            $this->view->success = false;
            $this->view->message = $e->getMessage();
        }
    }

    /**
     * Создаёт заказ исходя из выбранного победителя
     *
     * @param array $params Параметры запроса.
     *
     * @return void
     *
     * @remotable
     */
    public function createOrderFromProcurementAction(array $params = array())
    {
        \DbTransaction::start();
        try {
            /** @var PriceOrder */
            $priceOrder = PriceOrder::findOneBy($params);

            $priceOrder->setWinnerSupplierId($params["supplierWinnerId"]);

            $data = $priceOrder->makeOrder();
            $this->view->orderId = $data["order_id"];

            $procurementService = new ProcurementService();
            $procurementService->changeStatus(PriceOrder::STATUS_COMPLETED, $priceOrder);
            $priceOrder->save();

            \fireEvent('priceOrderStatusChanged', $priceOrder);

            $this->view->success = true;
            \DbTransaction::commit();
        } catch (\Exception $e) {
            \DbTransaction::rollback(false);
            $this->view->success = false;
            $this->view->message = $e->getMessage();
        }
    }

    /**
     * Получает данные о множетсвенных заказах.
     *
     * @param array $params Параметры запроса.
     *    array(
     *      priceOrderId => int,
     *      contragentIds => array(),
     *    ).
     *
     * @return void
     *
     * @remotable
     */
    public function getMultiOrderDataAction(array $params = array())
    {

        $priceOrderId = getParamAsInt($params, 'priceOrderId');
        expect($priceOrderId, 'Не передан идентификатор ценового запроса');
        unset($params['priceOrderId']);

        $contragentIds = getParamAsArray($params, 'contragentIds');
        expect($contragentIds, 'Не переданы идентификаторы организаций');
        unset($params['contragentIds']);

        $priceOrderPosition = PriceOrderPosition::getObjectClass();
        list($priceOrderPositionList,) = $priceOrderPosition->getList($priceOrderId, $params);

        $f = 0;
        $supplierPosition = PriceOrderSupplierPosition::getObjectClass();
        foreach ($priceOrderPositionList as $indexList => $priceOrderPosition) {

            if ($priceOrderPosition['quantity'] <= $priceOrderPosition['buy_quantity']) {
                continue;
            }

            $resultPriceOrderPositionList[$f] = $priceOrderPosition;
            list($supplierPositionList,) = $supplierPosition->getList(
                $priceOrderId,
                $priceOrderPosition['id'],
                $contragentIds,
                $params
            );

            foreach ($supplierPositionList as $item) {
                $addColumn = array(
                    'posp_price_' . $item['contragent_id'] => $item['price'],
                    'posp_deliverable_' . $item['contragent_id'] => $item['deliverable'],
                    'posp_id_' . $item['contragent_id'] => $item['id'],
                    'pos_id_' . $item['contragent_id'] => $item['pos_id']
                );
                $resultPriceOrderPositionList[$f] = array_merge($resultPriceOrderPositionList[$f], $addColumn);
            }
            $f++;
        };

        $this->view->rows = $resultPriceOrderPositionList;
        $this->view->success = true;
    }

    /**
     * Оформление мультизаказа
     *
     * @remotable
     *
     * @param array $params Параметры запроса.
     * array(rows => array(
     *  array(
     *    priceOrderId => int,
     *    posId => int,
     *    positions => array(
     *      )
     *  )
     *
     * @throws ResponseException
     */
    public function makeMultiOrderAction(array $params)
    {
        $priceOrderId = getParamAsInt($params, 'priceOrderId');
        expect($priceOrderId, 'Не передан идентификатор ценового запроса');
        $posId = getParamAsInt($params, 'posId');
        expect($posId, 'Не передан идентификатор выбранного поставщика');
        $posp = getParamAsArray($params, 'posp');
        expect($posp, 'Не переданы данные о закупаемых позиях ценнового запроса');
        $order = PriceOrder::loadByPrimary($priceOrderId);
        $this->view->rows = $order->makeOrder($posId, $posp);
        $this->view->success = true;
    }

    /**
     * Завершить формирование заказов.
     *
     * @remotable
     *
     * @param array $params Параметры запроса.
     * array(rows => array(
     *  array(
     *    priceOrderId => int,
     *  )
     *
     * @throws ResponseException
     *
     */
    public function finishExecuteMultiOrderAction(array $params)
    {
        $priceOrderId = getParamAsInt($params, 'priceOrderId');
        expect($priceOrderId, 'Не передан идентификатор ценового запроса');
        $order = PriceOrder::loadByPrimary($priceOrderId);
        $this->view->rows = $order->finishExecuteOrder(true);
        $this->view->success = true;
    }

    /**
     * Получение доступных статусов ЦЗ.
     *
     * @param array $params Параметры запроса.
     *
     * @return void
     *
     * @remotable
     */
    public function getStatusListAction($params)
    {
        try {
            /** @var \Zend_Db_Table_Rowset */
            $rowsetParams = [
                VocabPriceOrderStatus::PARAM_ACTUAL => true,
            ];
            $rowset = VocabPriceOrderStatus::getObjectClass()->findBy($rowsetParams);
            $list = $rowset->toArray();

            $this->view->success = true;
            $this->view->totalCount = count($list);
            $this->view->rows = $list;
        } catch (\Exception $e) {
            logException($e);
            $this->view->success = false;
            $this->view->message = $e->getMessage();
        }
    }

    /**
     * Получение списка экшнов, связанных со сменой статуса процедуры закупки,
     * доступных для перехода из текущего состояния процедуры закупки.
     *
     * @param array $params Параметры запроса.
     *    array(
     *      priceOrderId => int
     *    ).
     *
     * @return void
     *
     * @remotable
     */
    public function getNextActionListAction(array $params = array())
    {
        $priceOrderId = getParamAsInt($params, 'priceOrderId');
        expect($priceOrderId, 'Не передан идентификатор процедуры закупки');

        $workflow = new Workflow($priceOrderId);
        try {
            $actionList = $workflow->getNextActionList();
            $this->view->success = true;
            $this->view->actionList = $actionList;
        } catch (\Exception $e) {
            logException($e);
            $this->view->success = false;
            $this->view->message = $e->getMessage();
        }
    }

    /**
     * Откат процедуры закупки из состояния "Исполнено" на Выбор победителей.
     *
     * @param array $params Параметры запроса.
     *    array(
     *      priceOrderId => int
     *    ).
     *
     * @return void
     *
     * @remotable
     */
    public function refundToChooseWinnersAction(array $params = array())
    {
        $priceOrderId = getParamAsInt($params, 'priceOrderId');
        expect($priceOrderId, 'Не передан идентификатор процедуры закупки');

        \DbTransaction::start();
        try {
            $priceOrder = PriceOrder::loadByPrimary($priceOrderId);
            // меняем статус процедуры закупки
            $priceOrder->setStatus(PriceOrder::STATUS_CHOOSE_WINNERS);
            $priceOrder->save();
            // меняем статус поставщиков в процедуре закупки
            $priceOrder->updateSuppliersStatus($priceOrderId, PriceOrderSupplier::STATUS_RESPONSE);
            $this->view->success = true;
            \DbTransaction::commit();
        } catch (\Exception $e) {
            \DbTransaction::rollback();
            $this->view->success = false;
            $this->view->message = $e->getMessage();
            logException($e);
        }
    }
}
