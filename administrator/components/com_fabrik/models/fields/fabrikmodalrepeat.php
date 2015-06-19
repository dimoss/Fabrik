<?php
/**
 * Display a json loaded window with a repeatable set of sub fields
 *
 * @package     Joomla
 * @subpackage  Form
 * @copyright   Copyright (C) 2005-2015 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Helpers\Worker;
use Fabrik\Helpers\HTML;
use Fabrik\Helpers\Text;

jimport('joomla.form.formfield');

/**
 * Display a json loaded window with a repeatable set of sub fields
 *
 * @package     Joomla
 * @subpackage  Form
 * @since       1.6
 */

class JFormFieldFabrikModalrepeat extends JFormField
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'FabrikModalrepeat';

	/**
	 * Method to get the field input markup.
	 *
	 * @since	1.6
	 *
	 * @return	string	The field input markup.
	 */

	protected function getInput()
	{
		// Initialize variables.
		$document = JFactory::getDocument();
		JHTML::stylesheet('administrator/components/com_fabrik/views/fabrikadmin.css');
		$subForm = new JForm($this->name, array('control' => 'jform'));
		$xml = $this->element->children()->asXML();
		$subForm->load($xml);

		// Needed for repeating modals in gmaps viz
		$subForm->repeatCounter = (int) @$this->form->repeatCounter;

		/**
		 * f3 hack
		 */
		$this->app = JFactory::getApplication();
		$input = $this->app->input;
		$view = $input->get('view', 'list');

		switch ($view)
		{
			case 'item':
				$view = 'list';
				$id = $this->form->getValue('request.listid');
				break;
			case 'module':
				$view = 'list';
				$id = $this->form->getValue('params.list_id');
				break;
			default:
				$id = $input->getString('id');
				break;
		}

		if ($view === 'element')
		{
			//$pluginManager = Worker::getPluginManager();
			//$feModel = $pluginManager->getPluginFromId($id);
		}
		else
		{
			if ($view === 'list')
			{
				$view = 'lizt';
			}
			$view = \Joomla\String\String::ucfirst($view);
			$klass = "Fabrik\\Admin\\Models\\$view";
			$model = new $klass;
			$model->set('id', $id);
		}

		$subForm->model = $model;

		// Hack for order by elements which we now want to store as ids
		$v = json_decode($this->value);

		if (isset($v->order_by))
		{
			foreach ($v->order_by as &$orderBy)
			{
				$elementModel = $model->getElement($orderBy, true);
				$orderBy = $elementModel ? $elementModel->getId() : $orderBy;
			}
		}

		$this->value = json_encode($v);

		/*
		 * end
		 */
		$children = $this->element->children();

		// $$$ rob 19/07/2012 not sure y but this fires a strict standard warning deep in JForm, suppress error for now
		@$subForm->setFields($children);

		$str = array();
		$modalId = 'attrib-' . $this->id . '_modal';

		// As JForm will render child fieldsets we have to hide it via CSS
		$fieldSetId = str_replace('jform_params_', '', $modalId);
		$css = '#' . $fieldSetId . ' { display: none; }';
		$document->addStyleDeclaration($css);

		$str[] = '<div id="' . $modalId . '" style="display:none">';
		$str[] = '<table class="adminlist ' . $this->element['class'] . ' table table-striped">';
		$str[] = '<thead><tr class="row0">';
		$names = array();
		$attributes = $this->element->attributes();

		foreach ($subForm->getFieldset($attributes->name . '_modal') as $field)
		{
			$names[] = (string) $field->element->attributes()->name;
			$str[] = '<th>' . strip_tags($field->getLabel($field->name));
			$str[] = '<br /><small style="font-weight:normal">' . Text::_($field->description) . '</small>';
			$str[] = '</th>';
		}

		$str[] = '<th><a href="#" class="add btn button btn-success"><i class="icon-plus"></i> </a></th>';
		$str[] = '</tr></thead>';
		$str[] = '<tbody><tr>';

		foreach ($subForm->getFieldset($attributes->name . '_modal') as $field)
		{
			$str[] = '<td>' . $field->getInput() . '</td>';
		}

		$str[] = '<td>';

		$str[] = '<div class="btn-group"><a class="add btn button btn-success"><i class="icon-plus"></i> </a>';
		$str[] = '<a class="remove btn button btn-danger"><i class="icon-minus"></i> </a></div>';

		$str[] = '</td>';
		$str[] = '</tr></tbody>';
		$str[] = '</table>';
		$str[] = '</div>';
		static $modalRepeat;

		if (!isset($modalRepeat))
		{
			$modalRepeat = array();
		}

		if (!array_key_exists($modalId, $modalRepeat))
		{
			$modalRepeat[$modalId] = array();
		}

		if (!isset($this->form->repeatCounter))
		{
			$this->form->repeatCounter = 0;
		}

		if (!array_key_exists($this->form->repeatCounter, $modalRepeat[$modalId]))
		{
			// If loaded as js template then we don't want to repeat this again. (fabrik)
			$names = json_encode($names);

			$modalRepeat[$modalId][$this->form->repeatCounter] = true;
			$opts = new stdClass;
			$opts = json_encode($opts);
			$script = str_replace('-', '', $modalId) . " = new FabrikModalRepeat('$modalId', $names, '$this->id', $opts);";
			$option = $input->get('option');

			if ($option === 'com_fabrik')
			{
				HTML::script('administrator/components/com_fabrik/models/fields/fabrikmodalrepeat.js', $script);
			}
			else
			{
				$script = "window.addEvent('domready', function() {
				" . $script . "
				});";

				// Wont work when rendering in admin module page
				// @TODO test this now that the list and form pages are loading plugins via ajax (18/08/2012)
				HTML::script('administrator/components/com_fabrik/models/fields/fabrikmodalrepeat.js', $script);
			}
		}

		if (is_array($this->value))
		{
			$this->value = array_shift($this->value);
		}

		$value = htmlspecialchars($this->value, ENT_COMPAT, 'UTF-8');

		$icon = $this->element['icon'] ? '<i class="icon-' . $this->element['icon'] . '"></i> ' : '';
		$icon .= Text::_('JLIB_FORM_BUTTON_SELECT');
		$str[] = '<button class="btn" id="' . $modalId . '_button" data-modal="' . $modalId . '">' . $icon . '</button>';
		$str[] = '<input type="hidden" name="' . $this->name . '" id="' . $this->id . '" value="' . $value . '" />';

		HTML::framework();
		HTML::iniRequireJS();

		return implode("\n", $str);
	}
}
