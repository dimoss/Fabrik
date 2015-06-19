<?php
/**
 * Fabrik Raw Form View
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2015 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Helpers\Text;

jimport('joomla.application.component.view');

/**
 * Fabrik Raw Form View
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @since       3.0
 */
class FabrikViewForm extends JViewLegacy
{
	/**
	 * Access value
	 *
	 * @var  int
	 */
	public $access = null;

	/**
	 * Execute and display a template script.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @throws Exception
	 *
	 * @return  mixed  A string if successful, otherwise a JError object.
	 */
	public function display($tpl = null)
	{
		$app = JFactory::getApplication();
		$input = $app->input;
		$model = $this->getModel('form');

		// Get the active menu item
		$model->getForm();
		$model->render();

		$listModel = $model->getListModel();

		if (!$model->canPublish())
		{
			if (!$app->isAdmin())
			{
				echo Text::_('COM_FABRIK_FORM_NOT_PUBLISHED');

				return false;
			}
		}

		$this->access = $model->checkAccessFromListSettings();

		if ($this->access == 0)
		{
			throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'));
		}

		if (is_object($listModel))
		{
			$joins = $listModel->getJoins();
			$model->getJoinGroupIds($joins);
		}

		$params = $model->getParams();
		$params->def('icons', $app->getCfg('icons'));
		$pop = ($input->get('tmpl') == 'component') ? 1 : 0;
		$params->set('popup', $pop);
		$view = $input->get('view', 'form');

		if ($view == 'details')
		{
			$model->setEditable(false);
		}

		$groups = $model->getGroupsHierarchy();
		$gkeys = array_keys($groups);
		$JSONarray = array();
		$JSONHtml = array();

		for ($i = 0; $i < count($gkeys); $i++)
		{
			$groupModel = $groups[$gkeys[$i]];
			$groupModel->getGroup();

			// Check if group is actually a table join
			$repeatGroup = 1;

			if ($groupModel->canRepeat())
			{
				if ($groupModel->isJoin())
				{
					$joinModel = $groupModel->getJoinModel();
					$joinTable = $joinModel->getJoin();

					if (is_object($joinTable))
					{

						// $$$ rob test!!!
						if (!$groupModel->canView('form'))
						{
							continue;
						}

						$elementModels = $groupModel->getPublishedElements();
						reset($elementModels);
						$tmpElement = current($elementModels);
						$smallerElHTMLName = $tmpElement->getFullName(true, false);
						$repeatGroup = count($model->data[$smallerElHTMLName]);
					}
				}
			}

			$groupModel->repeatTotal = $repeatGroup;

			for ($c = 0; $c < $repeatGroup; $c++)
			{
				$elementModels = $groupModel->getPublishedElements();

				foreach ($elementModels as $elementModel)
				{
					if (!$model->isEditable())
					{
						/* $$$ rob 22/03/2011 changes element keys by appending "_id" to the end, means that
						 * db join add append data doesn't work if for example the popup form is set to allow adding,
						 * but not editing records
						 * $elementModel->inDetailedView = true;
						 */
						$elementModel->setEditable(false);
					}

					// Force reload?
					$elementModel->HTMLids = null;
					$elementHTMLId = $elementModel->getHTMLId($c);

					if (!$model->isEditable())
					{
						$JSONarray[$elementHTMLId] = $elementModel->getROValue($model->data, $c);
					}
					else
					{
						$JSONarray[$elementHTMLId] = $elementModel->getValue($model->data, $c);
					}
					// Test for paginate plugin
					if (!$model->isEditable())
					{
						$elementModel->HTMLids = null;
						$elementModel->inDetailedView = true;
					}

					$JSONHtml[$elementHTMLId] = htmlentities($elementModel->render($model->data, $c), ENT_QUOTES, 'UTF-8');
				}
			}
		}

		$data = array("id" => $model->getId(), 'model' => 'table', "errors" => $model->errors, "data" => $JSONarray, 'html' => $JSONHtml,
			'post' => $_REQUEST);
		echo json_encode($data);
	}
}
