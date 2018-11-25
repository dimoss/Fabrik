<?php
/**
 * List Advanced Search Model
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2016  Media A-Team, Inc. - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

namespace Joomla\Component\Fabrik\Site\Model;

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Helpers\Html;
use Fabrik\Helpers\ArrayHelper as FArrayHelper;
use Fabrik\Helpers\StringHelper as FStringHelper;
use Fabrik\Helpers\Worker;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\String\StringHelper;

/**
 * List Advanced Search Class
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @since       3.3.4
 */
class ListAdvancedSearchModel extends FabSiteModel
{
	/**
	 * @var ListModel
	 *
	 * @since 4.0
	 */
	protected $model;

	/**
	 * Previously submitted advanced search data
	 *
	 * @var array
	 *
	 * @since 4.0
	 */
	protected $advancedSearchRows = null;

	/**
	 * Set list model
	 *
	 * @param  ListModel  $model
	 *
	 * @return void
	 *
	 * @since 4.0
	 */
	public function setModel(ListModel $model)
	{
		$this->model = $model;
	}
	/**
	 * Called from index.php?option=com_fabrik&view=list&layout=_advancedsearch&tmpl=component&listid=4
	 * advanced search popup view
	 *
	 * @return  object	advanced search options
	 *
	 * @since 4.0
	 */
	public function opts()
	{
		$model = $this->model;
		$params = $model->getParams();
		$opts = new \stdClass;

		// $$$ rob - 20/208/2012 if list advanced search off return nothing
		if ($params->get('advanced-filter') == 0)
		{
			return $opts;
		}

		$defaultStatement = $params->get('advanced-filter-default-statement', '<>');
		$opts->defaultStatement = $defaultStatement;

		$list = $model->getTable();
		$listRef = $model->getRenderContext();
		$opts->conditionList = Html::conditionList($listRef, '');
		list($fieldNames, $firstFilter) = $this->getAdvancedSearchElementList();
		$statements = $this->getStatementsOpts();
		$opts->elementList = HTMLHelper::_('select.genericlist', $fieldNames, 'fabrik___filter[list_' . $listRef . '][key][]',
			'class="inputbox key" size="1" ', 'value', 'text');
		$opts->statementList = HTMLHelper::_('select.genericlist', $statements, 'fabrik___filter[list_' . $listRef . '][condition][]',
			'class="inputbox" size="1" ', 'value', 'text', $defaultStatement);
		$opts->listid = $list->id;
		$opts->listref = $listRef;
		$opts->ajax = $model->isAjax();
		$opts->counter = count($this->getadvancedSearchRows()) - 1;
		$elements = $model->getElements();
		$arr = array();

		foreach ($elements as $e)
		{
			$key = $e->getFilterFullName();
			$arr[$key] = array('id' => $e->getId(), 'plugin' => $e->getElement()->plugin);
		}

		$opts->elementMap = $arr;

		return $opts;
	}

	/**
	 * Get a list of elements that are included in the advanced search drop-down list
	 *
	 * @return  array  list of fields names and which is the first filter
	 *
	 * @since 4.0
	 */
	private function getAdvancedSearchElementList()
	{
		$model = $this->model;
		$first = false;
		$firstFilter = false;
		$fieldNames[] = HTMLHelper::_('select.option', '', Text::_('COM_FABRIK_PLEASE_SELECT'));
		$elementModels = $model->getElements();

		foreach ($elementModels as $elementModel)
		{
			if (!$elementModel->canView('list'))
			{
				continue;
			}

			$element = $elementModel->getElement();
			$elParams = $elementModel->getParams();

			if ($elParams->get('inc_in_adv_search', 1))
			{
				$elName = $elementModel->getFilterFullName();

				if (!$first)
				{
					$first = true;
					$firstFilter = $elementModel->getFilter(0, false);
				}

				$fieldNames[] = HTMLHelper::_('select.option', $elName, strip_tags(Text::_($element->label)));
			}
		}

		return array($fieldNames, $firstFilter);
	}

	/**
	 * Build an array of html data that gets inserted into the advanced search popup view
	 *
	 * @return  array	html lists/fields
	 *
	 * @since 4.0
	 */
	public function getAdvancedSearchRows()
	{
		if (isset($this->advancedSearchRows))
		{
			return $this->advancedSearchRows;
		}

		$model = $this->model;
		$statements = $this->getStatementsOpts();
		$input = $this->app->input;
		$rows = array();
		$elementModels = $model->getElements();
		list($fieldNames, $firstFilter) = $this->getAdvancedSearchElementList();
		$prefix = 'fabrik___filter[list_' . $model->getRenderContext() . '][';
		$type = '<input type="hidden" name="' . $prefix . 'search_type][]" value="advanced" />';
		$grouped = '<input type="hidden" name="' . $prefix . 'grouped_to_previous][]" value="0" />';
		$filters = $this->filterValues();
		$counter = 0;

		if (array_key_exists('key', $filters))
		{
			foreach ($filters['key'] as $key)
			{
				foreach ($elementModels as $elementModel)
				{
					$testKey = FStringHelper::safeColName($elementModel->getFullName(false, false));

					if ($testKey == $key)
					{
						break;
					}
				}

				$join = $filters['join'][$counter];
				$condition = $filters['condition'][$counter];
				$value = $filters['origvalue'][$counter];
				$v2 = $filters['value'][$counter];
				$jsSel = '=';

				switch ($condition)
				{
					case 'NOTEMPTY':
						$jsSel = 'NOTEMPTY';
						break;
					case 'EMPTY':
						$jsSel = 'EMPTY';
						break;
					case "<>":
						$jsSel = '<>';
						break;
					case "=":
						$jsSel = 'EQUALS';
						break;
					case "<":
						$jsSel = '<';
						break;
					case ">":
						$jsSel = '>';
						break;
					default:
						$firstChar = StringHelper::substr($v2, 1, 1);
						$lastChar = StringHelper::substr($v2, -2, 1);

						switch ($firstChar)
						{
							case '%':
								$jsSel = ($lastChar == '%') ? 'CONTAINS' : $jsSel = 'ENDS WITH';
								break;
							default:
								if ($lastChar == '%')
								{
									$jsSel = 'BEGINS WITH';
								}
								break;
						}
						break;
				}

				if (is_string($value))
				{
					$value = trim(trim($value, '"'), '%');
				}

				if ($counter == 0)
				{
					$join = Text::_('COM_FABRIK_WHERE') . '<input type="hidden" value="WHERE" name="' . $prefix . 'join][]" />';
				}
				else
				{
					$join = Html::conditionList($model->getRenderContext(), $join);
				}

				$lineElName = FStringHelper::safeColName($elementModel->getFullName(true, false));
				$orig = $input->get($lineElName);
				$input->set($lineElName, array('value' => $value));
				$filter = $elementModel->getFilter($counter, false);
				$input->set($lineElName, $orig);
				$key = HTMLHelper::_('select.genericlist', $fieldNames, $prefix . 'key][]', 'class="inputbox key input-small" size="1" ', 'value', 'text', $key);
				$jsSel = HTMLHelper::_('select.genericlist', $statements, $prefix . 'condition][]', 'class="inputbox input-small" size="1" ', 'value', 'text', $jsSel);
				$rows[] = array('join' => $join, 'element' => $key, 'condition' => $jsSel, 'filter' => $filter, 'type' => $type,
					'grouped' => $grouped);
				$counter++;
			}
		}

		if ($counter == 0)
		{
			$params = $model->getParams();
			$join = Text::_('COM_FABRIK_WHERE') . '<input type="hidden" name="' . $prefix . 'join][]" value="WHERE" />';
			$key = HTMLHelper::_('select.genericlist', $fieldNames, $prefix . 'key][]', 'class="inputbox key" size="1" ', 'value', 'text', '');
			$defaultStatement = $params->get('advanced-filter-default-statement', '<>');
			$jsSel = HTMLHelper::_('select.genericlist', $statements, $prefix . 'condition][]', 'class="inputbox" size="1" ', 'value', 'text', $defaultStatement);
			$rows[] = array('join' => $join, 'element' => $key, 'condition' => $jsSel, 'filter' => $firstFilter, 'type' => $type,
				'grouped' => $grouped);
		}

		$this->advancedSearchRows = $rows;

		return $rows;
	}

	/**
	 * Get a list of submitted advanced filters
	 *
	 * @return array advanced filter values
	 *
	 * @since 4.0
	 */
	public function filterValues()
	{
		$model = $this->model;
		$filters = $model->getFilterArray();
		$advanced = array();
		$iKeys = array_keys(FArrayHelper::getValue($filters, 'key', array()));

		foreach ($iKeys as $i)
		{
			$searchType = FArrayHelper::getValue($filters['search_type'], $i);

			if (!is_null($searchType) && $searchType == 'advanced')
			{
				foreach (array_keys($filters) as $k)
				{
					if (array_key_exists($k, $advanced))
					{
						// some keys may not exist for all filters
						$kf             = FArrayHelper::getValue($filters, $k, array());
						if (is_array($advanced[$k]))
						{
							$advanced[$k][] = FArrayHelper::getValue($kf, $i, '');
						}
					}
					else
					{
						$advanced[$k] = array_key_exists($i, $filters[$k]) ? array(($filters[$k][$i])) : '';
					}
				}
			}
		}

		return $advanced;
	}

	/**
	 * Build the advanced search link
	 *
	 * @return  string  <a href...> link
	 *
	 * @since 4.0
	 */
	public function link()
	{
		$model = $this->model;
		$params = $model->getParams();

		if ($params->get('advanced-filter', '0'))
		{
			$displayData = new \stdClass;
			$displayData->url = $this->url();
			$displayData->tmpl = $model->getTmpl();
			$layout = Html::getLayout('list.fabrik-advanced-search-button');

			return $layout->render($displayData);
		}
		else
		{
			return '';
		}
	}

	/**
	 * Get the URL used to open the advanced search window
	 *
	 * @return  string
	 *
	 * @since 4.0
	 */
	public function url()
	{
		$model = $this->model;
		$table = $model->getTable();
		$url = COM_FABRIK_LIVESITE . 'index.php?option=com_' . $this->package .
			'&amp;format=partial&amp;view=list&amp;layout=_advancedsearch&amp;tmpl=component&amp;listid='
			. $table->id . '&amp;nextview=' . $this->app->input->get('view', 'list');

		// Defines if we are in a module or in the component.
		$url .= '&amp;scope=' . $this->app->scope;
		$url .= '&amp;tkn=' . Session::getFormToken();

		return $url;
	}

	/**
	 * Called via advanced search to load in a given element filter
	 *
	 * @return string html for filter
	 *
	 * @since 4.0
	 */
	public function elementFilter()
	{
		$model = $this->model;
		$input = $this->app->input;
		$elementId = $input->getId('elid');
		$pluginManager = Worker::getPluginManager(true);
		$className = $input->get('plugin');
		$plugin = $pluginManager->getPlugIn($className, 'element');
		$plugin->setId($elementId);
		$plugin->getElement();

		if ($input->get('context') == 'visualization')
		{
			$container = $input->get('parentView');
		}
		else
		{
			$container = 'listform_' . $model->getRenderContext();
		}

		$script = $plugin->filterJS(false, $container);
		Html::addScriptDeclaration($script);

		echo $plugin->getFilter($input->getInt('counter', 0), false);
	}

	/**
	 * Get a list of advanced search options
	 *
	 * @return array of HTMLHelper options
	 *
	 * @since 4.0
	 */
	protected function getStatementsOpts()
	{
		$statements = array();
		$statements[] = HTMLHelper::_('select.option', '=', Text::_('COM_FABRIK_EQUALS'));
		$statements[] = HTMLHelper::_('select.option', '<>', Text::_('COM_FABRIK_NOT_EQUALS'));
		$statements[] = HTMLHelper::_('select.option', 'BEGINS WITH', Text::_('COM_FABRIK_BEGINS_WITH'));
		$statements[] = HTMLHelper::_('select.option', 'CONTAINS', Text::_('COM_FABRIK_CONTAINS'));
		$statements[] = HTMLHelper::_('select.option', 'ENDS WITH', Text::_('COM_FABRIK_ENDS_WITH'));
		$statements[] = HTMLHelper::_('select.option', '>', Text::_('COM_FABRIK_GREATER_THAN'));
		$statements[] = HTMLHelper::_('select.option', '<', Text::_('COM_FABRIK_LESS_THAN'));
		$statements[] = HTMLHelper::_('select.option', 'EMPTY', Text::_('COM_FABRIK_IS_EMPTY'));
		$statements[] = HTMLHelper::_('select.option', 'NOTEMPTY', Text::_('COM_FABRIK_IS_NOT_EMPTY'));

		return $statements;
	}
}