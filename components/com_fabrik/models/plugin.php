<?php
/**
 * Base Fabrik Plugin Model
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2015 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

namespace Fabrik\Plugins;

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Helpers\ArrayHelper;
use \Fabrik\Admin\Models\Connection as Connection;
use \Fabrik\Admin\Models\Lists as Lists;
use \Fabrik\Admin\Models\Lizt as Lizt;
use Fabrik\Helpers\Worker;
use \JFactory as JFactory;
use \JForm as JForm;
use \JFile as JFile;
use Fabrik\Helpers\Text;
use \stdClass as stdClass;
use \Joomla\Registry\Registry as Registry;
use Fabrik\Helpers\String;
use \JHTML as JHTML;
use \FabTable as FabTable;
use \Fabrik\Helpers\HTML;

/**
 * Base Fabrik Plugin Model
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @since       3.0
 */

class Plugin extends \JPlugin
{
	/**
	 * path to xml file
	 *
	 * @var string
	 */
	public $xmlPath = null;

	/**
	 * Params (must be public)
	 *
	 * @var Registry
	 */
	public $params = null;

	/**
	 * Plugin id
	 *
	 * @var int
	 */
	protected $id = null;

	/**
	 * Plugin data
	 *
	 * //@var JTable
	 * @var Registry
	 */
	//protected $row = null;
	protected $item;

	/**
	 * Order that the plugin is rendered
	 *
	 * @var int
	 */
	public $renderOrder = null;

	/**
	 * Form
	 *
	 * @var JForm
	 */
	public $jform = null;

	/**
	 * Model
	 *
	 * @var \Fabrik\Admin\Models\Form
	 */
	public $model = null;

	/**
	 * Set the plugin id
	 *
	 * @param   int  $id  id to use
	 *
	 * @return  void
	 */

	public function setId($id)
	{
		$this->id = $id;
	}

	/**
	 * Get plugin id
	 *
	 * @return  int  id
	 */

	public function getId()
	{
		return $this->id;
	}

	/**
	 * Set model
	 *
	 * @param   \Fabrik\Admin\Models\Form  &$model  Plugin model
	 *
	 * @return  void
	 */
	public function setModel(&$model)
	{
		$this->model = $model;
	}

	/**
	 * Get Model
	 *
	 * @return \Fabrik\Admin\Models\Form
	 */
	public function getModel()
	{
		return $this->model;
	}

	/**
	 * Get the plugin name
	 *
	 * @return string
	 */
	public function getName()
	{
		return isset($this->name) ? $this->name : get_class($this);
	}

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An array that holds the plugin configuration
	 */
	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);
		$this->app = ArrayHelper::getValue($config, 'app', JFactory::getApplication());
		$this->user = ArrayHelper::getValue($config, 'user', JFactory::getUser());
		$this->config = ArrayHelper::getValue($config, 'config', JFactory::getConfig());
		$this->language = ArrayHelper::getValue($config, 'language', JFactory::getLanguage());
		$this->db = ArrayHelper::getValue($config, 'db', JFactory::getDbo());
		$this->loadLanguage();
	}

	/**
	 * Get the JForm object for the plugin
	 *
	 * @return \JForm jform
	 */
	public function getJForm()
	{
		if (!isset($this->jform))
		{
			$type = str_replace('fabrik_', '', $this->_type);
			$formType = $type . '-options';
			$formName = 'com_fabrik.' . $formType;
			$controlName = 'jform';
			$this->jform = new JForm($formName, array('control' => $controlName));
			$this->jform->model = $this;
		}

		return $this->jform;
	}

	/**
	 * Create bootstrap horizontal tab headings from fieldset labels
	 * Used for rendering viz plugin options
	 *
	 * @param   JForm  $form           Plugin form
	 * @param   array  &$output        Plugin render output
	 * @param   int    $repeatCounter  Repeat count for plugin
	 *
	 * @since   3.1
	 *
	 * @return  void
	 */

	protected function renderFromNavTabHeadings($form, &$output, $repeatCounter = 0)
	{
		$fieldsets = $form->getFieldsets();

		if (count($fieldsets) <= 1)
		{
			return;
		}

		$output[] = '<div class="row-fluid">';
		$output[] = '<ul class="nav nav-tabs">';
		$i = 0;

		foreach ($fieldsets as $fieldset)
		{
			if (isset($fieldset->modal) && $fieldset->modal)
			{
				continue;
			}

			$class = $i === 0 ? ' class="active"' : '';
			$id = 'tab-' . $fieldset->name;
			$id .= '-' . $repeatCounter;
			$output[] = '<li' . $class . '>
				<a data-toggle="tab" href="#' . $id . '">
					' . Text::_($fieldset->label) . '
						</a>
		    </li>';
			$i ++;
		}

		$output[] = '</ul>';
		$output[] = '</div>';
	}

	/**
	 * Render the element admin settings
	 *
	 * @param   array   $data           Admin data
	 * @param   int     $repeatCounter  Repeat plugin counter
	 * @param   string  $mode           How the fieldsets should be rendered currently support 'nav-tabs' (@since 3.1)
	 *
	 * @return  string	admin html
	 */
	public function onRenderAdminSettings($data = array(), $repeatCounter = null, $mode = null)
	{
		$this->makeDbTable();
		$type = str_replace('fabrik_', '', $this->_type);
		JForm::addFormPath(JPATH_SITE . '/plugins/' . $this->_type . '/' . $this->_name);

		$folder = $this->_type == 'fabrik_validation' ? 'fabrik_validationrule' : $this->_type;
		$xmlFile = JPATH_SITE . '/plugins/' . $folder . '/' . $this->_name . '/forms/fields.xml';
		$form = $this->getJForm();
		$repeatScript = '';

		// Used by fields when rendering the [x] part of their repeat name
		// see administrator/components/com_fabrik/classes/formfield.php getName()
		$form->repeatCounter = $repeatCounter;

		// Add the plugin specific fields to the form.
		$form->loadFile($xmlFile, false);

		// Copy over the data into the params array - plugin fields can have data in either
		// jform[params][name] or jform[name]
		$dontMove = array('width', 'height');

		if (!array_key_exists('params', $data))
		{
			$data['params'] = array();
		}

		foreach ($data as $key => $val)
		{
			if (is_object($val))
			{
				$val = isset($val->$repeatCounter) ? $val->$repeatCounter : '';
				$data['params'][$key] = $val;
			}
			else
			{
				if (is_array($val))
				{
					$data['params'][$key] = ArrayHelper::getValue($val, $repeatCounter, '');
				}
				else
				{
					// Textarea now stores width/height in params, don't want to copy over old w/h values into the params array
					if (!in_array($key, $dontMove))
					{
						$data['params'][$key] = $val;
					}
				}
			}
		}
		// Bind the plugins data to the form
		$form->bind($data);

		// $$$ rob 27/04/2011 - listfields element needs to know things like the group_id, and
		// as bind() only saves the values from $data with a corresponding xml field we set the raw data as well
		$form->rawData = $data;
		$str = array();

		// Paul - If there is a string for plugin_DESCRIPTION then display this as a legend
		$iniStr = strtoupper('PLG_' . $type . '_' . $this->_name . '_DESCRIPTION');
		$iniVal = Text::_($iniStr);

		if ($iniStr != $iniVal)
		{
			// Handle strings with HTML
			$iniVal2 = '';
			$p_re = '#^\s*(<p\s*\S*\s*>.*?</p>)#i';
			$matches = array();

			if (preg_match($p_re, $iniVal, $matches))
			{
				$iniVal2 = preg_replace($p_re, '', $iniVal);
				$iniVal = $matches[1];
			}
			elseif (substr($iniVal, 0, 1) != '<' && strpos($iniVal, '<br') > 0)
			{
				// Separate first part for legend and convert rest to paras
				$lines = preg_split('/<br\s*\/\s*>/', $iniVal, PREG_SPLIT_NO_EMPTY);
				$iniVal = $lines[0];
				unset($lines[0]);
				$iniVal2 = '<b><p>' . implode('</p>\n<p>', $lines) . '<br/><br/></p></b>';
			}

			$str[] = '<legend>' . $iniVal . '</legend>';

			if ($iniVal2 != '')
			{
				$str[] = $iniVal2;
			}
		}

		if ($mode === 'nav-tabs')
		{
			$this->renderFromNavTabHeadings($form, $str, $repeatCounter);
			$str[] = '<div class="tab-content">';
		}

		$c = 0;
		$fieldsets = $form->getFieldsets();

		if (count($fieldsets) <= 1)
		{
			$mode = null;
		}

		// Filer the forms fieldsets for those starting with the correct $searchName prefix
		foreach ($fieldsets as $fieldset)
		{
			if ($mode === 'nav-tabs')
			{
				$tabClass = $c === 0 ? ' active' : '';
				$str[] = '<div class="tab-pane' . $tabClass . '" id="tab-' . $fieldset->name . '-' . $repeatCounter . '">';
			}

			$class = 'form-horizontal ';
			$class .= $type . 'Settings page-' . $this->_name;
			$repeat = isset($fieldset->repeatcontrols) && $fieldset->repeatcontrols == 1;

			// Bind data for repeat groups
			$repeatDataMax = 1;

			if ($repeat)
			{
				$opts = new stdClass;
				$opts->repeatmin = (isset($fieldset->repeatmin)) ? $fieldset->repeatmin : 1;
				$repeatScript[] = "new FbRepeatGroup('$fieldset->name', " . json_encode($opts) . ');';
				$repeatData = array();

				foreach ($form->getFieldset($fieldset->name) as $field)
				{
					if ($repeatDataMax < count($field->value))
					{
						$repeatDataMax = count($field->value);
					}
				}

				$form->bind($repeatData);
			}

			$id = isset($fieldset->name) ? ' id="' . $fieldset->name . '"' : '';
			$style = isset($fieldset->modal) && $fieldset->modal ? 'style="display:none"' : '';
			$str[] = '<fieldset class="' . $class . '"' . $id . ' ' . $style . '>';

			if ($mode == '' && $fieldset->label != '')
			{
				$str[] = '<legend>' . Text::_($fieldset->label) . '</legend>';
			}

			$form->repeat = $repeat;

			if ($repeat)
			{
				$str[] = '<a class="btn" href="#" data-button="addButton"><i class="icon-plus"></i> ' . Text::_('COM_FABRIK_ADD') . '</a>';
				$str[] = '<a class="btn" href="#" data-button="deleteButton"><i class="icon-minus"></i> ' . Text::_('COM_FABRIK_REMOVE') . '</a>';
			}

			for ($r = 0; $r < $repeatDataMax; $r++)
			{
				if ($repeat)
				{
					$str[] = '<div class="repeatGroup">';
					$form->repeatCounter = $r;
				}

				foreach ($form->getFieldset($fieldset->name) as $field)
				{
					if ($repeat)
					{
						if (is_array($field->value))
						{
							if (array_key_exists($r, $field->value))
							{
								$field->setValue($field->value[$r]);
							}
						}
					}

					$str[] = '<div class="control-group">';
					$str[] = '<div class="control-label">' . $field->label . '</div>';
					$str[] = '<div class="controls">' . $field->input . '</div>';
					$str[] = '</div>';
				}

				if ($repeat)
				{
					$str[] = "</div>";
				}
			}

			$str[] = '</fieldset>';

			if ($mode === 'nav-tabs')
			{
				$str[] = '</div>';
			}

			$c ++;
		}

		if ($mode === 'nav-tabs')
		{
			$str[] = '</div>';
		}

		if (!empty($repeatScript))
		{
			$repeatScript = "window.addEvent('domready', function () {\n" . implode("\n", $repeatScript) . "\n})\n";
			HTML::script('administrator/components/com_fabrik/models/fields/repeatgroup.js', $repeatScript);
		}

		return implode("\n", $str);
	}

	/**
	 * Used in plugin manager runPlugins to set the correct repeat set of
	 * data for the plugin
	 *
	 * @param   object  &$params        Original params
	 * @param   int     $repeatCounter  Repeat group counter
	 *
	 * @return   object  params
	 */
	public function setParams(&$params, $repeatCounter)
	{
		echo "<h2>set params </h2>";
		$opts = $params->toArray();
		$data = array();

		foreach ($opts as $key => $val)
		{
			if (is_array($val))
			{
				$data[$key] = ArrayHelper::getValue($val, $repeatCounter);
			}
			else
			{
				$data[$key] = $val;
			}
		}

		$this->params = new Registry(json_encode($data));

		return $this->params;
	}

	/**
	 * Load params
	 *
	 * @return  Registry  params
	 */
	public function getParams()
	{
		if (!isset($this->params))
		{
			$row = $this->getItem();
			$this->params = new Registry($row->params);
		}

		return $this->params;
	}

	/**
	 * Get item
	 *
	 * @return  Registry
	 */
	public function getItem()
	{
		// Should always be set
		return $this->item;
	}

	/**
	 * Set item
	 *
	 * @param   Registry  $item
	 *
	 * @return  void
	 */
	public function setItem(Registry $item)
	{
		$this->item = $item;
	}

	/**
	 * Determine if we use the plugin or not
	 * both location and event criteria have to be match when form plug-in
	 *
	 * @param   string  $location  Location to trigger plugin on
	 * @param   string  $event     Event to trigger plugin on
	 *
	 * @return  bool  true if we should run the plugin otherwise false
	 */

	public function canUse($location = null, $event = null)
	{
		$ok = false;
		$app = $this->app;
		$model = $this->getModel();

		switch ($location)
		{
			case 'front':
				if (!$app->isAdmin())
				{
					$ok = true;
				}
				break;
			case 'back':
				if ($app->isAdmin())
				{
					$ok = true;
				}
				break;
			case 'both':
				$ok = true;
				break;
		}

		if ($ok)
		{
			// $$$ hugh @FIXME - added copyingRow() stuff to form model, need to do it
			// for list model as well.
			$k = array_key_exists('origRowId', $model) ? 'origRowId' : 'rowId';

			switch ($event)
			{
				case 'new':
					if ($model->$k != 0)
					{
						$ok = isset($model->copyingRow) ? $model->copyingRow() : false;
					}

					break;
				case 'edit':
					if ($model->$k == 0)
					{
						/** $$$ hugh - don't think this is right, as it'll return true when it shouldn't.
						 * Think if this row is being copied, then by definition it's not being edited, it's new.
						 * For now, just set $ok to false;
						 * $ok = $ok = isset($model->copyingRow) ? !$model->copyingRow() : false;
						 */
						$ok = false;
					}

					break;
			}
		}

		return $ok;
	}

	/**
	 * Custom process plugin result
	 *
	 * @param   string  $method  Method
	 *
	 * @return boolean
	 */
	public function customProcessResult($method)
	{
		return true;
	}

	/**
	 * Ajax function to return a string of table drop down options
	 * based on cid variable in query string
	 *
	 * @return  void
	 */
	public function onAjax_tables()
	{
		$app = $this->app;
		$input = $app->input;
		$cid = $input->getInt('cid', -1);
		$rows = array();
		$showFabrikLists = $input->get('showf', false);

		if ($showFabrikLists)
		{
			$model = new Lists;
			$items = $model->getItems();
			$rows = array();

			foreach ($items as $id => $item)
			{
				$item = new Registry($item);

				if ((int) $item->get('list.connection_id') === $cid)
				{
					$option = new stdClass;
					$option->id = $id;
					$option->label = $item->get('list.label');
					$rows[] = $option;
				}
			}

			$default = new stdClass;
			$default->id = '';
			$default->label = Text::_('COM_FABRIK_PLEASE_SELECT');
			array_unshift($rows, $default);
		}
		else
		{
			if ($cid !== -1)
			{
				$cnn = new Connection;

				$cnn->set('id', $cid);
				$db = $cnn->getDb();
				$db->setQuery("SHOW TABLES");
				$rows = (array) $db->loadColumn();
			}

			array_unshift($rows, '');
		}

		echo json_encode($rows);
	}

	/**
	 * J1.6 plugin wrapper for ajax_fields
	 *
	 * @return  void
	 */

	public function onAjax_fields()
	{
		$this->ajax_fields();
	}

	/**
	 * Get a list of fields
	 *
	 * @return  string  json encoded list of fields
	 */

	public function ajax_fields()
	{
		$app = $this->app;
		$input = $app->input;
		$tid = $input->getString('t');
		$keyType = $input->getInt('k', 1);

		// If true show all fields if false show fabrik elements
		$showAll = $input->getBool('showall', false);

		// Should we highlight the PK as a recommended option
		$highlightPk = $input->getBool('highlightpk');

		// Only used if showall = false, includes validations as separate entries
		$incCalculations = $input->get('calcs', false);
		$arr = array();

		try
		{
			if ($showAll)
			{
				// Show all db columns
				$cid = $input->get('cid', -1);
				$cnn = new Connection;
				$cnn->set('id', $cid);
				$db = $cnn->getDb();

				if ($tid != '')
				{
					if (is_numeric($tid))
					{
						// If loading on a numeric list id get the list db table name
						$jDb = Worker::getDbo(true);
						$query = $jDb->getQuery(true);
						$query->select('db_table_name')->from('#__fabrik_lists')->where('id = ' . (int) $tid);
						$jDb->setQuery($query);
						$tid = $jDb->loadResult();
					}

					$db->setQuery('DESCRIBE ' . $db->qn($tid));
					$rows = $db->loadObjectList();

					if (is_array($rows))
					{
						foreach ($rows as $r)
						{
							$c = new stdClass;
							$c->value = $r->Field;
							$c->label = $r->Field;

							if ($highlightPk && $r->Key === 'PRI')
							{
								$c->label .= ' [' . Text::_('COM_FABRIK_RECOMMENDED') . ']';
								array_unshift($arr, $c);
							}
							else
							{
								$arr[$r->Field] = $c;
							}
						}

						ksort($arr);
						$arr = array_values($arr);
					}
				}
			}
			else
			{
				/*
				 * show fabrik elements in the table
				* $keyType 1 = $element->id;
				* $keyType 2 = tablename___elementname
				*/
				$model = new Lizt;

				$model->set('id', $tid);

				$table = $model->getItem();

				$db = $model->getDb();
				$groups = $model->getGroupsHierarchy();
				$published = $input->get('published', false);
				$showInTable = $input->get('showintable', false);

				foreach ($groups as $g => $groupModel)
				{
					if ($groupModel->isJoin())
					{
						if ($input->get('excludejoined') == 1)
						{
							continue;
						}

						$joinModel = $groupModel->getJoinModel();
						$join = $joinModel->getJoin();
					}

					if ($published == true)
					{
						$elementModels = $groups[$g]->getPublishedElements();
					}
					else
					{
						$elementModels = $groups[$g]->getMyElements();
					}

					foreach ($elementModels as $e => $eVal)
					{
						$element = $eVal->getElement();

						if ($showInTable == true && $element->get('show_in_list_summary') == 0)
						{
							continue;
						}

						if ($keyType == 1)
						{
							$v = $element->id;
						}
						else
						{
							/*
							 * @TODO if in repeat group this is going to add [] to name - is this really
							* what we want? In timeline viz options I've simply stripped out the [] off the end
							* as a temp hack
							*/
							$useStep = $keyType === 2 ? true : false;
							$v = $eVal->getFullName($useStep);
						}

						$c = new stdClass;
						$c->value = $v;
						$label = String::getShortDdLabel($element->label);

						if ($groupModel->isJoin())
						{
							$label = $join->table_join . '.' . $label;
						}

						$c->label = $label;

						// Show hightlight primary key and shift to top of options
						$pk = $table->get('list.db_primary_key');

						if ($highlightPk && $pk === $db->qn($eVal->getFullName(false, false)))
						{
							$c->label .= ' [' . Text::_('COM_FABRIK_RECOMMENDED') . ']';
							array_unshift($arr, $c);
						}
						else
						{
							$arr[] = $c;
						}

						if ($incCalculations)
						{
							$params = $eVal->getParams();

							if ($params->get('sum_on', 0))
							{
								$c = new stdClass;
								$c->value = 'sum___' . $v;
								$c->label = Text::_('COM_FABRIK_SUM') . ': ' . $label;
								$arr[] = $c;
							}

							if ($params->get('avg_on', 0))
							{
								$c = new stdClass;
								$c->value = 'avg___' . $v;
								$c->label = Text::_('COM_FABRIK_AVERAGE') . ': ' . $label;
								$arr[] = $c;
							}

							if ($params->get('median_on', 0))
							{
								$c = new stdClass;
								$c->value = 'med___' . $v;
								$c->label = Text::_('COM_FABRIK_MEDIAN') . ': ' . $label;
								$arr[] = $c;
							}

							if ($params->get('count_on', 0))
							{
								$c = new stdClass;
								$c->value = 'cnt___' . $v;
								$c->label = Text::_('COM_FABRIK_COUNT') . ': ' . $label;
								$arr[] = $c;
							}

							if ($params->get('custom_calc_on', 0))
							{
								$c = new stdClass;
								$c->value = 'cnt___' . $v;
								$c->label = Text::_('COM_FABRIK_CUSTOM') . ': ' . $label;
								$arr[] = $c;
							}
						}
					}
				}
			}
		}
		catch (RuntimeException $err)
		{
			// Ignore errors as you could be swapping between connections, with old db table name selected.
		}

		array_unshift($arr, JHTML::_('select.option', '', Text::_('COM_FABRIK_PLEASE_SELECT'), 'value', 'label'));
		echo json_encode($arr);
	}

	/**
	 * Get js for managing the plugin in J admin
	 *
	 * @param   string  $name   plugin name
	 * @param   string  $label  plugin label
	 * @param   string  $html   html (not sure what this is?)
	 *
	 * @return  string  JS code to ini adminplugin class
	 */
	public function onGetAdminJs($name, $label, $html)
	{
		$opts = $this->getAdminJsOpts($html);
		$opts = json_encode($opts);
		$script = "new fabrikAdminPlugin('$name', '$label', $opts)";

		return $script;
	}

	/**
	 * Get the options to ini the J Admin js plugin controller class
	 *
	 * @param   string  $html  HTML?
	 *
	 * @return  object
	 */
	protected function getAdminJsOpts($html)
	{
		$opts = new stdClass;
		$opts->livesite = COM_FABRIK_LIVESITE;
		$opts->html = $html;

		return $opts;
	}

	/**
	 * If true then the plugin is stating that any subsequent plugin in the same group
	 * should not be run.
	 *
	 * @param   string  $method  Current plug-in call method e.g. onBeforeStore
	 *
	 * @return  bool
	 */
	public function runAway($method)
	{
		return false;
	}

	/**
	 * Process the plugin, called when form is submitted
	 *
	 * @param   string     $paramName  Param name which contains the PHP code to eval
	 * @param   array      $data       Data
	 * @param   Registry $params      Plugin parameters - hacky fix ini email plugin where in
	 *                                 php 5.3.29 email params were getting confused between multiple plugin instances
	 *
	 * @return  bool
	 */
	protected function shouldProcess($paramName, $data = null, $params = null)
	{
		if (is_null($data))
		{
			$data = $this->data;
		}

		if (is_null($params))
		{
			$params = $this->getParams();
		}

		$condition = $params->get($paramName);
		$formModel = $this->getModel();
		$w = new Worker;

		if (trim($condition) == '')
		{
			return true;
		}

		if (!is_null($formModel))
		{
			$origData = $formModel->getOrigData();
			$origData = ArrayHelper::fromObject($origData[0]);
		}
		else
		{
			$origData = array();
		}

		$condition = trim($w->parseMessageForPlaceHolder($condition, $data));
		$res = @eval($condition);

		if (is_null($res))
		{
			return true;
		}

		return $res;
	}

	/**
	 * Translates numeric entities to UTF-8
	 *
	 * @param   array  $ord  preg replace call back matched
	 *
	 * @return  string
	 */
	protected function replace_num_entity($ord)
	{
		$ord = $ord[1];

		if (preg_match('/^x([0-9a-f]+)$/i', $ord, $match))
		{
			$ord = hexdec($match[1]);
		}
		else
		{
			$ord = intval($ord);
		}

		$no_bytes = 0;
		$byte = array();

		if ($ord < 128)
		{
			return chr($ord);
		}
		elseif ($ord < 2048)
		{
			$no_bytes = 2;
		}
		elseif ($ord < 65536)
		{
			$no_bytes = 3;
		}
		elseif ($ord < 1114112)
		{
			$no_bytes = 4;
		}
		else
		{
			return;
		}

		switch ($no_bytes)
		{
			case 2:
				$prefix = array(31, 192);
				break;
			case 3:
				$prefix = array(15, 224);
				break;
			case 4:
				$prefix = array(7, 240);
				break;
		}

		for ($i = 0; $i < $no_bytes; $i++)
		{
			$byte[$no_bytes - $i - 1] = (($ord & (63 * pow(2, 6 * $i))) / pow(2, 6 * $i)) & 63 | 128;
		}

		$byte[0] = ($byte[0] & $prefix[0]) | $prefix[1];
		$ret = '';

		for ($i = 0; $i < $no_bytes; $i++)
		{
			$ret .= chr($byte[$i]);
		}

		return $ret;
	}

	/**
	 * Get the plugin manager
	 *
	 * @since 3.0
	 *
	 * @deprecated use Worker::getPluginManager()
	 *
	 * @return  \Fabrik\Admin\Models\PluginManager
	 */
	protected function getPluginManager()
	{
		return Worker::getPluginManager();
	}

	/**
	 * Get user ids from group ids
	 *
	 * @param   array   $sendTo  User group id
	 * @param   string  $field   Field to return from user group. Default = 'id'
	 *
	 * @since   3.0.7
	 *
	 * @return  array  users' property defined in $field
	 */
	protected function getUsersInGroups($sendTo, $field = 'id')
	{
		if (empty($sendTo))
		{
			return array();
		}

		$db = Worker::getDbo();
		$query = $db->getQuery(true);
		$query->select('DISTINCT(' . $field . ')')->from('#__users AS u')->join('LEFT', '#__user_usergroup_map AS m ON u.id = m.user_id')
		->where('m.group_id IN (' . implode(', ', $sendTo) . ')');
		$db->setQuery($query);

		return $db->loadColumn();
	}

	/**
	 * Make db tables if found, called from onRenderAdminSettings - seems plugins cant run their own sql files atm
	 *
	 * @since   3.1a
	 *
	 * @return  void
	 */
	protected function makeDbTable()
	{
		$db = Worker::getDbo();

		// Attempt to create the db table?
		$file = COM_FABRIK_BASE . '/plugins/' . $this->_type . '/' . $this->_name . '/sql/install.mysql.uft8.sql';

		if (JFile::exists($file))
		{
			$sql = file_get_contents($file);
			$sqls = explode(";", $sql);

			if (!empty($sqls))
			{
				foreach ($sqls as $sql)
				{
					if (trim($sql) !== '')
					{
						$db->setQuery($sql);
						$db->execute();
					}
				}
			}
		}
	}
}
