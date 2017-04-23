<?php
/**
 * Created by PhpStorm.
 * User: a.zdorenko
 * Date: 24.01.17
 * Time: 22:54
 *
 * @package Nsi\Tests\Fixture
 */

namespace Nsi\Tests\Fixture;

use Cometp\Tests\Application\ConfigModification;
use \Nsi\Model\DbMapper\Category;
use Nsi\Model\CompositeAttribute\Attribute;
use \Nsi\Model\DbMapper\AttributeDictValue;
use Nsi\Model\DbMapper\CondSupplierPosition;
use \Nsi\Model\DbMapper\DictionaryPosition;
use Nsi\Model\DbMapper\DiscountCondSupplierPosition;
use Nsi\Model\DbMapper\SupplierPosition;
use Nsi\Model\DbMapper\SupplierPositionRegions;

/**
 * Class MockNSI
 */
class MockNSI
{
    /** @var string $test_category_code Код тестовой категории. */
    protected static $test_category_code = '1013100';

    /**
     * MockNSI constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->setInitConfig();
    }

    /**
     * Добавление позиции в словарь.
     *
     * @param Category $category     Категория.
     * @param string   $name         Иия.
     * @param int      $contragentId Идентификатор контрагента.
     *
     * @return DictionaryPosition
     */
    public function addDictionaryPosition(Category $category, $name, $contragentId = null)
    {
        $position = DictionaryPosition::createObject();
        $position->setName($name);
        $position->setNsiCategoryCode($category->getCode());
        $position->setNsiCategorySerial($position->getNextSerialAtCode($category->getCode()));
        $position->setSuggestedContragentId($contragentId);
        $position->setSuggestedDate(date('Y-m-d G:i:s P'));
        $position->save();

        return $position;
    }

    /**
     * Добавить случайную категорию с позицией (опционально).
     *
     * @param bool $with_position По-умолчанию истина - будет добавлена позиция.
     *
     * @return Category
     */
    public function makeRandomCategory($with_position = true)
    {
        $obCategory = Category::createObject()
            ->setCode((string)rand(9000000, 9900000))
            ->setName(uniqid())
            ->setParentCode('0');
        $obCategory->save();
        if ($with_position) {
            $this->addDictionaryPosition($obCategory, uniqid(), null);
        }
        return $obCategory;
    }

    /**
     * Cоздание тестовой категории.
     *
     * @param bool $withDict Атрибут категории будет со словарём и 2 значениями.
     *
     * @return \Nsi\Model\DbMapper\Category
     */
    public function createTestCategory($withDict = false)
    {
        $category = Category::createObject()
            ->setCode(static::$test_category_code)
            ->setName(uniqid())
            ->setParentCode('0')
            ->setDateAdded(date('Y-m-d G:i:s P'))
            ->setDateUpdated(date('Y-m-d G:i:s P'));
        $category->save();

        $attribute = Attribute::createObject()
            ->setName(uniqid())
            ->setAttrType(Attribute::ATTR_TYPE_STRING)
            // Словарь создастся автоматически, если "истина".
            ->setAttrHasDict($withDict)
            ->setNsiCategoryCode($category->getCode());
        $attribute->save();

        // Если со словарём, то добавим словарь и 2 значения.
        if ($withDict) {
            AttributeDictValue::createObject(array(
                AttributeDictValue::PARAM_NAME => uniqid(),
                AttributeDictValue::PARAM_BASE_ATTRIBUTE_ID => $attribute->getNsiBaseAttributeId(),
                AttributeDictValue::PARAM_ATTRIBUTE_DICT_ID => $attribute->getNsiAttributeDictId(),
            ))->save();
            AttributeDictValue::createObject(array(
                AttributeDictValue::PARAM_NAME => uniqid(),
                AttributeDictValue::PARAM_BASE_ATTRIBUTE_ID => $attribute->getNsiBaseAttributeId(),
                AttributeDictValue::PARAM_ATTRIBUTE_DICT_ID => $attribute->getNsiAttributeDictId(),
            ))->save();
        }
        return $category;
    }

    /**
     * Добавить атрибут к категории.
     *
     * @param string $categoryCode Код категори.
     *
     * @return Attribute
     */
    public function addCategoryAttribute($codeCategory)
    {
        $attribute = Attribute::createObject()
            ->setName(uniqid())
            ->setAttrType(Attribute::ATTR_TYPE_STRING)
            ->setNsiCategoryCode($codeCategory);
        $attribute->save();

        return $attribute;
    }

    /**
     * Устанавливаем параметры конфигурации по умолчанию для тестов.
     *
     * @return void
     */
    public function setInitConfig()
    {
        (new ConfigModification())->setConfigValue('nsi->category->root', '0');
    }

    /**
     * Добавление условия предоставления скидки
     *
     * @param int $condPositionId ID связанного CondSupplierPosition.
     * @param int $discount       Величина скидки в процентах.
     * @param int $quantityFrom   Минимальное кол-во для предоставления скидки.
     * @param int $quantityTo     Максимальное кол-во для предоставления скидки.
     *
     * @return DiscountCondSupplierPosition
     */
    public function addSupplierPositionDiscountCondition($condPositionId, $discount, $quantityFrom, $quantityTo)
    {
        $discount = DiscountCondSupplierPosition::createObject(
            [
                DiscountCondSupplierPosition::QUANTITY_FROM => $quantityFrom,
                DiscountCondSupplierPosition::QUANTITY_TO => $quantityTo,
                DiscountCondSupplierPosition::DISCOUNT => $discount,
                DiscountCondSupplierPosition::COND_POSITION_ID => $condPositionId,
            ]
        );
        $discount->save();

        return $discount;
    }

    /**
     * Добавления условия поставки для позиции поставщика.
     *
     * @param SupplierPosition $position    Позиция поставщика.
     * @param array            $regionCodes Код региона поставки.
     * @param array            $params      Массив с полями условия поставки.
     *
     * @return CondSupplierPosition
     */
    public function addSupplierPositionCondition(SupplierPosition $position, array $regionCodes, array $params)
    {
        $params[CondSupplierPosition::POSITION_ID] = $position->getId();
        $spCondition = CondSupplierPosition::createObject($params);
        $spCondition->save();

        foreach ($regionCodes as $regionCode) {
            $spRegions = SupplierPositionRegions::createObject(array(
                SupplierPositionRegions::PARAM_SUPPLIER_POSITION_ID => $spCondition->getId(),
                SupplierPositionRegions::PARAM_REGION_CODE => $regionCode
            ));
            $spRegions->save();
        }

        return $spCondition;
    }

    /**
     * Добавление позиции поставщику в прайс-лист
     *
     * @param int   $dictionaryPositionId ID позиции в справочнике.
     * @param int   $supplierId           ID контрагента.
     * @param array $params               Массив с полями позиции.
     * @param int   $price                Цена позиции.
     *
     * @return SupplierPosition
     */
    public function addSupplierPosition($dictionaryPositionId, $supplierId, array $params = array(), $price = null)
    {
        $supPosition = SupplierPosition::createObject($params);
        $supPosition->setDictionaryPositionId($dictionaryPositionId);
        $supPosition->setContragentId($supplierId);
        $supPosition->setDateCreated(date("Y-m-d G:i:s P"));
        $supPosition->setDateUpdate(date("Y-m-d G:i:s P"));
        $supPosition->setPrice($price);
        $supPosition->save();

        return $supPosition;
    }
}
