<?php
/**
 * Fabrik Admin Lists Model
 *
 * @package     Joomla.Administrator
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2018  Media A-Team, Inc. - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 * @since       4.0
 */

namespace Fabrik\Component\Fabrik\Administrator\Model;

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Helpers\Worker;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Fabrik\Component\Fabrik\Administrator\Table\FabrikTable;
use Joomla\Database\DatabaseQuery;
use Joomla\Utilities\ArrayHelper;

/**
 * Fabrik Admin Lists Model
 *
 * @package     Joomla.Administrator
 * @subpackage  Fabrik
 * @since       4.0
 */
class ListsModel extends FabrikListModel
{
	/**
	 * Constructor.
	 *
	 * @param   array $config An optional associative array of configuration settings.
	 *
	 * @since      4.0
	 */
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array('l.id', 'label', 'db_table_name', 'published');
		}

		parent::__construct($config);
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return  DatabaseQuery
	 *
	 * @since    4.0
	 */
	protected function getListQuery()
	{
		// Initialise variables.
		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select($this->getState('list.select', 'l.*'));
		$query->from('#__{package}_lists AS l');

		// Filter by published state
		$published = $this->getState('filter.published');

		if (is_numeric($published))
		{
			$query->where('l.published = ' . (int) $published);
		}
		elseif ($published === '')
		{
			$query->where('(l.published IN (0, 1))');
		}

		// Checked out user name
		$query->select('u.name AS editor')->join('LEFT', '#__users AS u ON u.id = l.checked_out');

		// Filter by search in title
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			$search = $db->quote('%' . $db->escape($search, true) . '%');
			$query->where('(l.db_table_name LIKE ' . $search . ' OR l.label LIKE ' . $search . ')');
		}

		// Add the list ordering clause.
		$orderCol  = $this->state->get('list.ordering');
		$orderDirn = $this->state->get('list.direction');

		if ($orderCol == 'ordering' || $orderCol == 'category_title')
		{
			$orderCol = 'category_title ' . $orderDirn . ', ordering';
		}

		if (trim($orderCol) !== '')
		{
			$query->order($db->escape($orderCol . ' ' . $orderDirn));
		}

		return $query;
	}

	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param   string $id A prefix for the store id.
	 *
	 * @return  string  A store id.
	 *
	 * @since    4.0
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.access');
		$id .= ':' . $this->getState('filter.state');
		$id .= ':' . $this->getState('filter.category_id');
		$id .= ':' . $this->getState('filter.language');

		return parent::getStoreId($id);
	}

	/**
	 * Get list groups
	 *
	 * @return  array  groups
	 */
	public function getTableGroups()
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true);
		$query->select('DISTINCT(l.id) AS id, fg.group_id AS group_id');
		$query->from('#__{package}_lists AS l');
		$query->join('LEFT', '#__{package}_formgroup AS fg ON l.form_id = fg.form_id');
		$db->setQuery($query);
		$rows = $db->loadObjectList('id');

		return $rows;
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string $ordering  An optional ordering field.
	 * @param   string $direction An optional direction (asc|desc).
	 *
	 * @since    4.0
	 *
	 * @return  void
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Initialise variables.
		$app = Factory::getApplication('administrator');

		// Load the filter state.
		$search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
		$this->setState('filter.search', $search);

		// Load the published state
		$published = $app->getUserStateFromRequest($this->context . '.filter.published', 'filter_published', '');
		$this->setState('filter.published', $published);

		// Load the parameters.
		$params = ComponentHelper::getParams('com_fabrik');
		$this->setState('params', $params);

		// List state information.
		parent::populateState('label', 'asc');
	}

	/**
	 * Get an array of database table names used in fabrik lists
	 *
	 * @return  array  database table names
	 *
	 * @since 4.0
	 */
	public function getDbTableNames()
	{
		$app   = Factory::getApplication();
		$input = $app->input;
		$cid   = $input->get('cid', array(), 'array');
		$cid   = ArrayHelper::toInteger($cid);
		$db    = Worker::getDbo(true);
		$query = $db->getQuery(true);
		$query->select('db_table_name')->from('#__{package}_lists')->where('id IN(' . implode(',', $cid) . ')');
		$db->setQuery($query);

		return $db->loadColumn();
	}
}