<?php

namespace Nsi\Model\DbTable;

use Nsi\Model\DbMapper\PriceOrder as PriceOrderMapper;

class PriceOrder extends \Zend_Db_Table_Abstract
{
  protected $_name = PriceOrderMapper::TABLE_NAME;
  protected $_primary = 'id';
  protected $_dependentTables
    = array(
      'Nsi\Model\DbTable\PriceOrderSupplier',
        'Nsi\Model\DbTable\PriceOrderRegion'
    );
  protected $_sequence = true;
  protected $_referenceMap
    = array(
      'User' => array(
        'columns' => array(\Nsi\Model\DbMapper\PriceOrder::PARAM_USER_ID),
        'refTableClass' => 'DbTable_Users',
        'refColumns' => array(\Nsi\Model\DbMapper\User::PARAM_ID)
      ),
      'Type' => array(
        'columns' => array(\Nsi\Model\DbMapper\PriceOrder::PARAM_TYPE),
        'refTableClass' => 'Nsi\Model\DbTable\VocabPriceOrderType',
        'refColumns' => array(\Nsi\Model\DbMapper\VocabPriceOrderType::PARAM_CODE)
      )
    );
}