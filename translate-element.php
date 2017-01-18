<?php
/**
 * @author Meshcherinov N.(mnikolayw@gmail.com)
 * @category Bitrix
 * @package TranslateElement
 *
 * Класс получает перевод элемента по ID и коду языка.
 *
 * Структура таблиц состояит из основной таблицы, таблицы с переводом и списком языков.
 * Таблица с переводом содержит ID основной таблицы и ID языка для которого переводятся фразы.
 *
 * Код таблицы с переводом формируется из кода основной таблицы + _translate(event -> event_translate)
 * за это отвечает константа PREFIX_TRANSLATE_IBLOCK
 */

namespace Logicasoft;

use \Bitrix\Main;
use \Bitrix\Main\Loader;
use \Bitrix\Iblock;

if (!Loader::includeModule('iblock')) {
    ShowError('Don\'t install iblock module');
}

class TranslateElement
{
    /**
     * ID инфоблока хранящего список языков
     */
    const LANGUAGE_IBLOCK_ID = 3;

    /**
     * Окончание входящее в состав кода таблицы с переводом
     * формируется из CODE основной таблицы + PREFIX
     */
    const PREFIX_TRANSLATE_IBLOCK = '_translate';
    /**
     * Переведенный результат
     * @var array
     */
    private $arResult = array();

    /**
     * Текущий язык
     * @var array
     */
    private $arLanguage = array();

    /**
     * Элемент для которого ищутся результаты перевода
     * @var array
     */
    private $arElement = array();

    /**
     * TranslateIblock constructor.
     *
     * @param int $ELEMENT_ID
     * @param string $LANGUAGE_CODE
     */
    public function __construct($ELEMENT_ID, $LANGUAGE_CODE = '')
    {
        try {
            $this->setElement($ELEMENT_ID);
            $this->setLanguageCode($LANGUAGE_CODE);
            $this->setTranslate();
        } catch(\Exception $e) {
            ShowError($e->getMessage());
        }
    }

    /**
     * Метод устанавливает в $arResult найденный перевод. В случае, когда ничего не найдено подставит false.
     *
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Exception
     */
    private function setTranslate()
    {
        if ($sIblockCode = $this->getIblockCode()) {
            $sIblockTranslateCode = $sIblockCode . self::PREFIX_TRANSLATE_IBLOCK;

            if (!($arIblock = Iblock\IblockTable::getList(array('filter' => array('CODE' => $sIblockTranslateCode)))->fetch())) {
                throw new \Exception("Ошибка! У инфоблока $sIblockCode нет инфоблока с переводами");
            }

            $arElement = \CIBlockElement::GetList(
                array('SORT' => 'DESC'),
                array('IBLOCK_ID' => $arIblock['ID'], 'PROPERTY_ELEMENT' => $this->arElement['ID'], 'PROPERTY_LANGUAGE' => $this->arLanguage['ID']),
                false,
                array('nPageSize' => 1)

            )->Fetch();

            if (!$arElement || !isset($arElement['ID'])) {
                $this->arResult = false;
            }

            $this->arResult = $arElement;
        }
    }

    /**
     * Возвращает результат с переводом элемента
     *
     * @return array
     */
    public function getTranslate()
    {
        return $this->arResult;
    }

    /**
     * Возвращает код инфоблока для элемента которого ищется перевод.
     * Учавствует в формировании CODE инфоблока с переводами
     *
     * @return bool|string
     */
    private function getIblockCode()
    {
        if (!$this->arElement['IBLOCK_ID']) {
            return false;
        }

        $arIblock = Iblock\IblockTable::getByPrimary(
            $this->arElement['IBLOCK_ID'],
            array(
                'select' => array('CODE')
            )
        )->fetch();

        if (is_null($arIblock) || !isset($arIblock['CODE'])) {
            return false;
        }

        return $arIblock['CODE'];
    }

    /**
     * Заполняет массив с данными элемента для которого ищется перевод
     *
     * @param int $ELEMENT_ID
     * @throws \Bitrix\Main\ArgumentException
     */
    public function setElement($ELEMENT_ID)
    {
        if (empty($ELEMENT_ID) ||
            !($arElement = Iblock\ElementTable::getById($ELEMENT_ID)->fetch())) {
            throw new Main\ArgumentException('ELEMENT ID is empty or not exist');
        }

        $this->arElement = $arElement;
    }

    /**
     * заполняет массив с данными по выбранному языку.
     * Если ничего не передано в параметры возвращается язык по умолчанию(PROPERTY_DEFAULT).
     * Если язык по умолчанию не доступен возбудит исключение.
     *
     * @param string $LANGUAGE_CODE
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Exception
     */
    public function setLanguageCode($LANGUAGE_CODE)
    {
        if (!$LANGUAGE_CODE ||
            !($arResult = Iblock\ElementTable::getList(array('filter' => array('CODE' => $LANGUAGE_CODE)))->fetch())) {
            $arResult = \CIBlockElement::GetList(
                array('SORT' => 'DESC'),
                array('IBLOCK_ID' => self::LANGUAGE_IBLOCK_ID, '=PROPERTY_DEFAULT_VALUE' => 'Y', 'ACTIVE' => 'Y'),
                false,
                array('nPageSize' => 1),
                array('IBLOCK_ID', 'ID', 'PROPERTY_DEFAULT', 'CODE')
            )->Fetch();
        }

        if (!isset($arResult['CODE'])) {
            throw new \Exception('Ошибка! Не передан идентификатор языка и не установлен язык по умолчанию');
        }

        $this->arLanguage = $arResult;
    }

}