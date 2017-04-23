<?php

use Nsi\Model\Controllers\AbstractPositionController;
use Nsi\Model\DbMapper\Config;

/**
 *
 * @property Zend_View view
 */
class Nsi_ConfigController extends AbstractPositionController
{
    /**
     * Список параметров
     *
     * @remotable
     */
    public function listAction()
    {
        $config = Config::getObjectClass();
        $configParams = $config->getParams();
        $this->view->rows = $configParams;
        $this->view->success = true;
    }

    /**
     * Обновление
     *
     * @param array $params Параметры запроса
     *   array("rows" => array(
     *      param_name,
     *      param_value
     * ).
     *
     * @remotable
     * @denyMulti
     */
    public function updateAction(array $params)
    {
        if (isset($params['rows'][0]) && is_array($params['rows'][0])) {
            $rows = $params['rows'];
        } else {
            $rows = array($params['rows']);
        }
        $rows = Config::updateBatch($rows);
        $this->view->rows = $rows;
        $this->view->success = true;
    }

    /**
     * Получить значение по наименованию.
     *
     * @param string $configName Наименование параметра.
     *
     * @return void
     *
     * @remotable
     */
    public function getAction($configName)
    {
        $config = Config::getObjectClass();
        $value = $config->getParam($configName);
        $this->view->success = true;
        $this->view->value = $value ? $value->getValue() : null;
    }

    /**
     * Изменить значение парметра по наименованию.
     *
     * @param string $configName Наименование параметра.
     * @param string $value      Новое значение параметра.
     *
     * @return void
     *
     * @remotable
     */
    public function setAction($configName, $value)
    {
        Config::setParam($configName, $value);
        $this->view->success = true;
        $this->view->message = 'Установлено';
    }
}
