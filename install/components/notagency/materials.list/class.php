<?
namespace Notagency\Components;

use Notagency\Base\ComponentsBase,
    Notagency\Base\Tools;

if (!\Bitrix\Main\Loader::includeModule('notagency.base')) return false;

class MaterialsList extends ComponentsBase
{
    const ENGLISH_ELEMENT_ACTIVE_PROPERTY = 'ACTIVE_EN';
    const ENGLISH_ELEMENT_NAME_PROPERTY = 'NAME_EN';

    protected $needModules = ['iblock'];

    protected $checkParams = [
        'IBLOCK_CODE' => ['type' => 'string']
    ];

    protected $elementsFilter = [];

    public function onPrepareComponentParams($arParams)
    {
        $arParams = parent::onPrepareComponentParams($arParams);
        $arParams['IBLOCK_CODE'] = htmlspecialchars(trim($arParams['IBLOCK_CODE']));
        $arParams['SECTION_CODE'] = htmlspecialchars(trim($arParams['SECTION_CODE']));
        $arParams['SECTION_ID'] = intval($arParams['SECTION_ID']);

        if (strlen($arParams['ELEMENT_SORT_BY1'])<=0) $arParams['ELEMENT_SORT_BY1'] = 'SORT';
        if ($arParams['ELEMENT_SORT_ORDER1']!='DESC') $arParams['ELEMENT_SORT_ORDER1']='ASC';

        if (strlen($arParams['ELEMENT_SORT_BY2'])<=0) $arParams['ELEMENT_SORT_BY2'] = 'ID';
        if ($arParams['ELEMENT_SORT_ORDER2']!='DESC') $arParams['ELEMENT_SORT_ORDER2']='ASC';

        if (strlen($arParams['ELEMENT_SORT_BY3'])<=0) $arParams['ELEMENT_SORT_BY3'] = 'ID';
        if ($arParams['ELEMENT_SORT_ORDER3']!='DESC') $arParams['ELEMENT_SORT_ORDER3']='ASC';
        
        if (strlen($arParams['SECTION_SORT_BY1'])<=0) $arParams['SECTION_SORT_BY1'] = 'SORT';
        if ($arParams['SECTION_SORT_ORDER1']!='DESC') $arParams['SECTION_SORT_ORDER1']='ASC';

        if (strlen($arParams['SECTION_SORT_BY2'])<=0) $arParams['SECTION_SORT_BY2'] = 'ID';
        if ($arParams['SECTION_SORT_ORDER2']!='DESC') $arParams['SECTION_SORT_ORDER2']='ASC';

        if ($arParams['PAGE']) $arParams['PAGE'] = intval($_GET['page']);
        if ($arParams['PAGING'] == 'Y')
        {
            $nav = \CDBResult::GetNavParams();
            if ($nav)  $arParams['PAGE'] = intval($nav['PAGEN']);
            else if ($arParams['PAGE']) $arParams['PAGE'] = intval($_GET['page']);
            //не сохраняем в сессии параметры пагинации потому что это сбивает с толку пользователей
            \CPageOption::SetOptionString("main", "nav_page_in_session", "N");
        }
        
        $arParams['PREPROD_SERVER'] = defined('PREPROD_SERVER') && PREPROD_SERVER;

        return $arParams;
    }

    /**
     * @inheritdoc
     */
    protected function executeMain()
    {
        $this->selectIblock();
        $this->selectSections();
        $this->selectElements();
        $this->buildTree();
        $this->prepareResult();
        $this->setPanelButtons();
    }

    /**
     * @inheritdoc
     */
    protected function executeEpilog()
    {
        $this->showPanelButtons();
    }

     /**
     * Выбирает поля инфоблока, результат в $arResult['IBLOCK']
     * @throws \Exception
     */
    protected function selectIblock()
    {
        $filter = [
            'CODE'=>$this->arParams['IBLOCK_CODE'],
            'SITE_ID' => SITE_ID,
        ];
        $result = \CIBlock::GetList([], $filter)->fetch();
        if (empty($result))
        {
            throw new \Exception('iblock with code "' . $this->arParams['IBLOCK_CODE'] . '" doesn\'t found');
        }
        $result['LIST_PAGE_URL'] = \CIBlock::ReplaceDetailUrl($result['LIST_PAGE_URL'], $result);
        $this->arResult['IBLOCK'] = $result;
    }

    /**
     * Выбирает поля разделов/раздела
     * Один раздел  - $arResult['SECTION']
     * Более одного - $arResult['SECTIONS']
     * @throws \Exception
     */
    protected function selectSections()
    {
        $sections = [];
        if ($this->arParams['SELECT_SECTIONS'] != 'Y')
        {
            return;
        }

        $sort = [
            $this->arParams['SECTION_SORT_BY1'] => $this->arParams['SECTION_SORT_ORDER1'],
            $this->arParams['SECTION_SORT_BY2'] => $this->arParams['SECTION_SORT_ORDER2'],
        ];
        $filter = [
            'GLOBAL_ACTIVE'=>'Y',
            'IBLOCK_ID' => $this->arResult['IBLOCK']['ID'],
        ];
        if ($this->arParams['SECTION_CODE'])
        {
            $filter['CODE'] = $this->arParams['SECTION_CODE'];
        }
        else if ($this->arParams['SECTION_ID'])
        {
            $filter['ID'] = $this->arParams['SECTION_CODE'];
        }
        $select = [
            'ID',
            'IBLOCK_ID',
            'NAME',
            'CODE',
        ];
        if (is_array($this->arParams['SECTION_FIELDS']) && count($this->arParams['SECTION_FIELDS']))
        {
            $select = array_merge($select, $this->arParams['SECTION_FIELDS']);
        }
        if (is_array($this->arParams['SECTION_PROPERTIES']) && count($this->arParams['SECTION_PROPERTIES']))
        {
            $select = array_merge($select, $this->arParams['SECTION_PROPERTIES']);
        }
        $rs = \CIBlockSection::GetList($sort, $filter, false, $select);
        if ($this->arParams['SECTION_CODE'])
        {
            if ($section = $rs->GetNext())
            {
                $sections[] = $section;
            }
            else
            {
                define("ERROR_404","Y");
                //throw new \Exception('section with code "' . $this->arParams['SECTION_CODE'] . '" doesn\'t found in IBLOCK_ID=' . $this->arResult['IBLOCK']['ID']);
            }
        }
        else if ($this->arParams['SECTION_ID'])
        {
            if ($section = $rs->Fetch())
            {
                $sections[] = $section;
            }
            else
            {
                throw new \Exception('section with id "' . $this->arParams['SECTION_ID'] . '" doesn\'t found in IBLOCK_ID=' . $this->arResult['IBLOCK']['ID']);
            }
        }
        else
        {
            while ($section = $rs->GetNext())
            {
                $sections[] = $section;
            }
        }
        $this->arResult['SECTIONS'] = $sections;
        if (count($sections) == 1)
        {
            $this->arResult['SECTION'] = current($sections);
        }
    }

    /**
     * Выбирает поля элемента, результат в $arResult['ELEMENTS']
     * Постраничная навигация - $arResult['NAV_STRING']
     * Постраничная навигация для json api -  $arResult['NAV']
     * @throws \Exception
     */
    protected function selectElements()
    {
        $elements = [];
        $sort = [
            $this->arParams['ELEMENT_SORT_BY1'] => $this->arParams['ELEMENT_SORT_ORDER1'],
            $this->arParams['ELEMENT_SORT_BY2'] => $this->arParams['ELEMENT_SORT_ORDER2'],
            $this->arParams['ELEMENT_SORT_BY3'] => $this->arParams['ELEMENT_SORT_ORDER3'],
        ];
        $filter = [
            'IBLOCK_ID'=>$this->arResult['IBLOCK']['ID'],
        ];

        if (!$this->arParams['PREPROD_SERVER'])
        {
            $filter['ACTIVE'] = 'Y';
        }
        if (SITE_ID == 'en')
        {
            //for english version
            if(
                !\CIBlockProperty::GetByID(self::ENGLISH_ELEMENT_ACTIVE_PROPERTY, $this->arResult['IBLOCK']['ID'])->fetch()
                && !\CIBlockProperty::GetByID(self::ENGLISH_ELEMENT_NAME_PROPERTY, $this->arResult['IBLOCK']['ID'])->fetch()
            )
            {
                return $elements;
            }
            $filter['!PROPERTY_' . self::ENGLISH_ELEMENT_ACTIVE_PROPERTY] = false;
            $filter['!PROPERTY_' . self::ENGLISH_ELEMENT_NAME_PROPERTY] = false;
        }

        if($this->arParams['SECTION_CODE'])
        {
            $filter['SECTION_CODE'] = $this->arParams['SECTION_CODE'];
            if($this->arParams['INCLUDE_SUBSECTIONS'] == 'Y')
            {
                $filter['INCLUDE_SUBSECTIONS'] = 'Y';
            }
        }
        else if($this->arParams['SECTION_ID'])
        {
            $filter['SECTION_ID'] = $this->arParams['SECTION_ID'];
            if($this->arParams['INCLUDE_SUBSECTIONS'] == 'Y')
            {
                $filter['INCLUDE_SUBSECTIONS'] = 'Y';
            }
        }
        if (is_array($this->elementsFilter))
        {
            $filter = array_merge($filter, $this->elementsFilter);
        }

        $nav = false;
        if ($this->arParams['PAGING'] == 'Y')
        {
            $nav = [
                'nPageSize' => $this->arParams['ELEMENTS_COUNT'],
                'iNumPage' => $this->arParams['PAGE'],
            ];
        }
        else if ($this->arParams['PAGING'] != 'Y' && $this->arParams['ELEMENTS_COUNT'])
        {
            $nav = [
                'nTopCount' => $this->arParams['ELEMENTS_COUNT'],
            ];
        }

        if (is_array($this->arParams['ELEMENT_FIELDS']) && count($this->arParams['ELEMENT_FIELDS']))
        {
            $select = array_merge($this->arParams['ELEMENT_FIELDS'], ['ID', 'IBLOCK_ID']);
        }
        else
        {
            $select = array(
                'ID',
                'NAME',
                'CODE',
                'IBLOCK_ID',
                'SECTION_ID',
                'PREVIEW_PICTURE',
                'PREVIEW_TEXT',
                'DETAIL_PAGE_URL',
            );
        }
        $res = \CIBlockElement::GetList($sort, $filter, false, $nav, $select);
        if (is_array($this->arParams['ELEMENT_PROPERTIES']) && count($this->arParams['ELEMENT_PROPERTIES']))
        {
            while ($element = $res->GetNext())
            {
                $element['PROPERTIES'] = $this->getElementProperties($element['ID']);
                $element = $this->processElement($element);
                $elements[] = $element;
            }
        }
        else
        {
            while ($ob = $res->GetNextElement())
            {
                $element = $ob->GetFields();
                $element['PROPERTIES'] = $ob->GetProperties();
                $element = $this->processElement($element);
                $elements[] = $element;
            }
        }
        $this->arResult['ELEMENTS'] = $elements;
        if ($this->arParams['PAGING'] == 'Y')
        {
            $this->arResult['NAV_RESULT'] = $res;
            $this->arResult['NAV_STRING'] = $res->GetPageNavString($navigationTitle='', $templateName = '.default', $showAlways=false, $parentComponent=$this);
        }
    }

    /**
     * Выбирает свойства элемента
     * @param int $elementId - ID элемента инфоблока
     * @throws \Exception
     * @return array - результат CIBlockElement::GetProperty()
     */
    protected function getElementProperties($elementId)
    {
        $props = [];
        foreach($this->arParams['ELEMENT_PROPERTIES'] as $propertyCode){
            if (empty($propertyCode))
                continue;
            $rs = \CIBlockElement::GetProperty($this->arResult['IBLOCK']['ID'], $elementId, array(), array('CODE'=>$propertyCode));
            while ($prop = $rs->Fetch())
            {
                $props[$prop['CODE']]['PROPERTY_TYPE'] = $prop['PROPERTY_TYPE'];
                $props[$prop['CODE']]['MULTIPLE'] = $prop['MULTIPLE'];
                if (!empty($prop['VALUE']))
                {
                    if ($prop['MULTIPLE']=='Y')
                    {
                        $props[$prop['CODE']]['DESCRIPTION'][] = $prop['DESCRIPTION'];
                        $props[$prop['CODE']]['VALUE'][] = $prop['VALUE'];
                        if ($prop['PROPERTY_TYPE'] == 'L')
                        {
                            $props[$prop['CODE']]['VALUE_XML_ID'][] = $prop['VALUE_XML_ID'];
                        }
                    }
                    else{
                        $props[$prop['CODE']]['DESCRIPTION'] = $prop['DESCRIPTION'];
                        $props[$prop['CODE']]['VALUE'] = $prop['VALUE'];
                        if ($prop['PROPERTY_TYPE'] == 'L')
                        {
                            $props[$prop['CODE']]['VALUE_XML_ID'] = $prop['VALUE_XML_ID'];
                        }
                    }
                }
            }
        }
        return $props;
    }


    /**
     * Строит дерево разделов и элементов, результат в $arResult['TREE']
     */
    protected function buildTree()
    {
        if (!empty($this->arResult['SECTIONS']) && !empty($this->arResult['ELEMENTS']))
        {
            foreach ($this->arResult['SECTIONS'] as $section)
            {
                $this->arResult['TREE'][$section['ID']] = $section;
            }
            foreach ($this->arResult['ELEMENTS'] as $element)
            {
                if (intval($element['IBLOCK_SECTION_ID']) > 0)
                {
                    $this->arResult['TREE'][$element['IBLOCK_SECTION_ID']]['ELEMENTS'][] = $element;
                }
            }
        }
    }

    /**
     * Подготавливает к выводу итоговый $arResult
     */
    protected function prepareResult() {}

    /**
     * Вызывается в цикле для каждого элемента
     * @param array $element - результат CIBlockElement::GetList()
     * @throws \Exception
     * @return array $element
     */
    protected function processElement($element)
    {
        $element['DISPLAY_ACTIVE_FROM'] = self::formatDisplayDate($element['DATE_ACTIVE_FROM'], $this->arParams['ACTIVE_DATE_FORMAT']);
        foreach ($element['PROPERTIES'] as &$property)
        {
            if ($property['PROPERTY_TYPE'] == 'F')
            {
                if ($property['MULTIPLE'] == 'Y' && count($property['VALUE']))
                {
                    foreach ($property['VALUE'] as $fileId)
                    {
                        if (!intval($fileId))
                        {
                            continue;
                        }
                        if ($file = \CFile::GetFileArray($fileId))
                        {
                            $file['DISPLAY_SIZE'] = Tools::formatFileSize($file['FILE_SIZE']);
                            $originalName = explode('.', $file['ORIGINAL_NAME']);
                            $file['FILE_EXTENSION'] = $originalName[count($originalName) - 1];
                            $file['FILE_NAME'] = str_replace('.' . $file['FILE_EXTENSION'], '', $originalName);
                            $property['VALUE_DETAILS'][] = $file;
                        }
                    }
                }
                else if ($property['MULTIPLE'] != 'Y' && intval($property['VALUE']))
                {
                    if ($file = \CFile::GetFileArray($property['VALUE']))
                    {
                        $file['DISPLAY_SIZE'] = Tools::formatFileSize($file['FILE_SIZE']);
                        list($file['FILE_NAME'], $file['FILE_EXTENSION']) = explode('.', $file['ORIGINAL_NAME']);
                        $property['VALUE_DETAILS'] = $file;
                    }
                }
            }
        }
        return $element;
    }

    /**
     * Устанавливает кнопки управления компонентом в публичной части в режиме редактирования
     */
    protected function setPanelButtons()
    {
        foreach ($this->arResult['ELEMENTS'] as $i=>$element)
        {
            $buttons = \CIBlock::GetPanelButtons(
                $element['IBLOCK_ID'],
                $element['ID'],
                $element['IBLOCK_SECTION_ID'],
                [
                    'SECTION_BUTTONS' => $this->arParams['ADD_PANEL_SECTION_BUTTONS'] == 'Y'
                ]
            );
            $this->arResult['ELEMENTS'][$i]['EDIT_LINK'] = $buttons['edit']['edit_element']['ACTION_URL'];
            $this->arResult['ELEMENTS'][$i]['DELETE_LINK'] = $buttons['edit']['delete_element']['ACTION_URL'];
        }
    }

    /**
     * Отображает кнопки управления компонентом в публичной части в режиме редактирования
     */
    protected function showPanelButtons()
    {
        $buttons = \CIBlock::GetPanelButtons(
            $this->arResult['IBLOCK']['ID'],
            0,
            0,
            [
                'SECTION_BUTTONS' => $this->arParams['ADD_PANEL_SECTION_BUTTONS'] == 'Y'
            ]
        );
        global $APPLICATION;
        if($this->arParams['SHOW_PANEL_BUTTONS'] == 'Y' && $APPLICATION->GetShowIncludeAreas())
        {
            $this->AddIncludeAreaIcons(\CIBlock::GetComponentMenu($APPLICATION->GetPublicShowMode(), $buttons));
        }
    }
}