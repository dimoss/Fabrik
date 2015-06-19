<?php
/**
 * Fabrik Google Map Viz HTML View
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.visualization.googlemap
 * @copyright   Copyright (C) 2005-2015 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Helpers\HTML;
use Fabrik\Helpers\Text;

jimport('joomla.application.component.view');

/**
 * Fabrik Google Map Viz HTML View
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.visualization.googlemap
 * @since       3.0
 */

class FabrikViewGooglemap extends JViewLegacy
{
	/**
	 * Execute and display a template script.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  mixed  A string if successful, otherwise a JError object.
	 */

	public function display($tpl = 'bootstrap')
	{
		$app = JFactory::getApplication();
		$input = $app->input;
		$srcs = HTML::framework();
		HTML::slimbox();
		$usersConfig = JComponentHelper::getParams('com_fabrik');
		$model = $this->getModel();
		$model->setId($input->getInt('id', $usersConfig->get('visualizationid', $input->getInt('visualizationid', 0))));
		$this->row = $model->getVisualization();

		if (!$model->canView())
		{
			echo Text::_('JERROR_ALERTNOAUTHOR');

			return false;
		}

		$js = $model->getJs();
		$this->txt = $model->getText();
		$params = $model->getParams();
		$this->params = $params;
		$tpl = $params->get('fb_gm_layout', $tpl);
		$tmplpath = JPATH_ROOT . '/plugins/fabrik_visualization/googlemap/views/googlemap/tmpl/' . $tpl;
		$srcs[] = 'media/com_fabrik/js/list-plugin.js';
		$srcs[] = 'media/com_fabrik/js/listfilter.js';

		if ($params->get('fb_gm_center') == 'userslocation')
		{
			$srcs[] = 'components/com_fabrik/libs/geo-location/geo.js';
		}

		$model->getPluginJsClasses($srcs);

		global $ispda;

		if ($ispda == 1)
		{
			// Pdabot
			$template = 'static';
			$this->staticmap = $model->getStaticMap();
		}
		else
		{
			if (HTML::isDebug())
			{
				$srcs[] = 'plugins/fabrik_visualization/googlemap/googlemap.js';
			}
			else
			{
				$srcs[] = 'plugins/fabrik_visualization/googlemap/googlemap-min.js';
			}

			if ((int) $this->params->get('fb_gm_clustering', '0') == 1)
			{
				if (HTML::isDebug())
				{
					$srcs[] = 'components/com_fabrik/libs/googlemaps/markerclustererplus/src/markerclusterer.js';
				}
				else
				{
					$srcs[] = 'components/com_fabrik/libs/googlemaps/markerclustererplus/src/markerclusterer_packed.js';
				}
			}
			else
			{
				// Doesn't work in v3
				// HTML::script('components/com_fabrik/libs/googlemaps/markermanager.js');
			}

			$template = null;
		}

		// Assign plugin js to viz so we can then run clearFilters() 
		$aObjs = $model->getPluginJsObjects();

		if (!empty($aObjs))
		{
			$js .= $model->getJSRenderContext(). ".addPlugins([\n";
			$js .= "\t" . implode(",\n  ", $aObjs);
			$js .= "]);";
		}

		$js .= $model->getFilterJs();

		HTML::iniRequireJs($model->getShim());
		HTML::script($srcs, $js);
		HTML::stylesheetFromPath('plugins/fabrik_visualization/googlemap/views/googlemap/tmpl/' . $tpl . '/template.css');

		// Check and add a general fabrik custom css file overrides template css and generic table css
		HTML::stylesheetFromPath('media/com_fabrik/css/custom.css');

		// Check and add a specific viz template css file overrides template css generic table css and generic custom css
		HTML::stylesheetFromPath('plugins/fabrik_visualization/googlemap/views/googlemap/tmpl/' . $tpl . '/custom.css');
		$this->filters = $model->getFilters();
		$this->showFilters = $model->showFilters();
		$this->filterFormURL = $model->getFilterFormURL();
		$this->sidebarPosition = $params->get('fb_gm_use_overlays_sidebar');
		$this->showOverLays = (bool) $params->get('fb_gm_use_overlays');

		if ($model->getShowSideBar())
		{
			$this->showSidebar = 1;
			$this->overlayUrls = (array) $params->get('fb_gm_overlay_urls');
			$this->overlayLabels = (array) $params->get('fb_gm_overlay_labels');
		}
		else
		{
			$this->showSidebar = 0;
		}

		$this->_setPath('template', $tmplpath);
		$this->containerId = $model->getContainerId();
		$this->groupTemplates = $model->getGroupTemplates();
		echo parent::display($template);
	}
}
