<?php
/**
 * Fabrik Model
 *
 * @package     Joomla.Administrator
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2018  Media A-Team, Inc. - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 * @since       4.0
 */

namespace Joomla\Component\Fabrik\Administrator\Model;


use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class FabModel extends BaseDatabaseModel
{
	/**
	 * BaseDatabaseModel::getInstance is ugly AF due to requiring a string based $prefix for namespacing.
	 *
	 * @param string $modelClass
	 * @param string $prefix
	 * @param array  $config
	 *
	 * @return BaseDatabaseModel
	 *
	 * @since 4.0
	 */
	public static function getInstance($modelClass, $prefix = '', $config = array())
	{
		if (!class_exists($modelClass)) {
			// Try Native Joomla
			return parent::getInstance($modelClass, $prefix, $config);
		}

		// Check for a possible service from the container otherwise manually instantiate the class
		if (Factory::getContainer()->has($modelClass))
		{
			return Factory::getContainer()->get($modelClass);
		}

		return new $modelClass($config);
	}
}