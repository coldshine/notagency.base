<?
if(!defined('B_PROLOG_INCLUDED')||B_PROLOG_INCLUDED!==true)die();

if(!CModule::IncludeModule('iblock'))
    return;

//select iblock's element's fields and properties
$filter = [
    'CODE' => $arCurrentValues['IBLOCK_CODE']
];
if ($iblock = CIBlock::GetList([], $filter)->fetch())
{
    //select fields
    $fields = [];
    $rawFields = CIBlock::GetFields($iblockId);
    foreach ($rawFields as $fieldCode=>$field)
    {
        $fields[$fieldCode] = $field['NAME'];
    }
    
    //element properties
    $elementProperties = [];
    $filter = [
        'ACTIVE'=>'Y', 
        'IBLOCK_ID'=> $iblockId,
    ];
    $rsProp = CIBlockProperty::GetList([], $filter);
    while ($item = $rsProp->Fetch())
    {
        $elementProperties[$item['CODE']] = '['.$item['CODE'].'] '.$item['NAME'];
    }
}

$arComponentParameters = CComponentUtil::GetComponentProps('notagency:elements.list', $arCurrentValues);

$arComponentParameters['PARAMETERS']['ELEMENT_CODE'] = array(
    'PARENT' => 'BASE',
    'NAME' => 'Переменная, в которой передается код элемента',
    'TYPE' => 'STRING',
    'DEFAULT' => '$_GET["element_code"]',
);

if ($arCurrentValues['SELECT_SECTIONS'] == 'Y')
{
    $arComponentParameters['PARAMETERS']['INCLUDE_SECTIONS_INTO_CHAIN'] = array(
        'PARENT' => 'ADDITIONAL_SETTINGS',
        'NAME' => 'Добавлять название раздела в навигационную цепочку на детальной станице',
        'TYPE' => 'CHECKBOX',
        'DEFAULT' => 'Y',
    );
}

$arComponentParameters['PARAMETERS']['INCLUDE_INTO_CHAIN'] = array(
    'PARENT' => 'ADDITIONAL_SETTINGS',
    'NAME' => 'Добавить сущность в навигационную цепочку на детальной станице',
    'TYPE' => 'LIST',
    'VALUES' => [
        '' => 'Нет',
        'FIELD' => 'Значение поля элемента инфоблока',
        'PROPERTY' => 'Значение свойства элемента инфоблока',
    ],
    'REFRESH' => 'Y',
);

if ($arCurrentValues['INCLUDE_INTO_CHAIN'] == 'FIELD')
{
    $arComponentParameters['PARAMETERS']['INCLUDE_FIELD_INTO_CHAIN'] = array(
        'PARENT' => 'ADDITIONAL_SETTINGS',
        'NAME' => 'Добавить поле в навигационную цепочку на детальной станице',
        'TYPE' => 'LIST',
        'VALUES' => [
            'NAME' => $fields['NAME'],
            'DATE_ACTIVE_FROM' => $fields['ACTIVE_FROM'],
        ],
        'ADDITIONAL_VALUES' => 'Y',
    );
}
else if($arCurrentValues['INCLUDE_INTO_CHAIN'] == 'PROPERTY')
{
    $arComponentParameters['PARAMETERS']['INCLUDE_PROPERTY_INTO_CHAIN'] = array(
        'PARENT' => 'ADDITIONAL_SETTINGS',
        'NAME' => 'Добавить свойство в навигационную цепочку на детальной станице',
        'TYPE' => 'LIST',
        'VALUES' => $elementProperties,
        'ADDITIONAL_VALUES' => 'Y',
    );
}

CIBlockParameters::Add404Settings($arComponentParameters, $arCurrentValues);

unset($arComponentParameters['PARAMETERS']['ELEMENTS_COUNT']);
unset($arComponentParameters['PARAMETERS']['ELEMENT_SORT_BY1']);
unset($arComponentParameters['PARAMETERS']['ELEMENT_SORT_BY2']);
unset($arComponentParameters['PARAMETERS']['ELEMENT_SORT_ORDER1']);
unset($arComponentParameters['PARAMETERS']['ELEMENT_SORT_ORDER2']);
unset($arComponentParameters['PARAMETERS']['SECTION_SORT_BY1']);
unset($arComponentParameters['PARAMETERS']['SECTION_SORT_BY2']);
unset($arComponentParameters['PARAMETERS']['SECTION_SORT_ORDER1']);
unset($arComponentParameters['PARAMETERS']['SECTION_SORT_ORDER2']);