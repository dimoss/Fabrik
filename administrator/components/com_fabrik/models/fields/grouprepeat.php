<?php
/**
 * Renders a radio group but only if the fabrik group is assigned to a form
 *
 * @package     Joomla
 * @subpackage  Form
 * @copyright   Copyright (C) 2005-2015 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Helpers\Text;

JFormHelper::loadFieldClass('radio');

/**
 * Renders a radio group but only if the fabrik group is assigned to a form
 * see: https://github.com/Fabrik/fabrik/issues/95
 *
 * @package     Joomla
 * @subpackage  Form
 * @since       1.6
 */
class JFormFieldGrouprepeat extends JFormFieldRadio
{
	/**
	 * Element name
	 *
	 * @var		string
	 */
	protected $name = 'Grouprepeat';

	/**
	 * Method to get the field input markup.
	 *
	 * @return	string	The field input markup.
	 */
	protected function getInput()
	{
		if ($this->form->getValue('form') === '')
		{
			return '<input class="readonly" size="60" value="' . Text::_('COM_FABRIK_FIELD_ASSIGN_GROUP_TO_FORM_FIRST') . '" type="readonly" />';
		}
		else
		{
			return parent::getInput();
		}
	}
}
