<?php
/**
 * Admin List Tmpl
 *
 * @package     Joomla.Administrator
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2015 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 * @since       3.0
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Helpers\Text;

?>
<div class="tab-pane" id="publishing">

	<ul class="nav nav-tabs">
		<li class="active">
			<a data-toggle="tab" href="#publishing-details">
				<?php echo Text::_('COM_FABRIK_GROUP_LABEL_PUBLISHING_DETAILS'); ?>
			</a>
		</li>
		<li>
			<a data-toggle="tab" href="#publishing-rss">
				<?php echo Text::_('COM_FABRIK_GROUP_LABEL_RSS') ?>
			</a>
		</li>
		<li>
			<a data-toggle="tab" href="#publishing-csv">
				<?php echo Text::_('COM_FABRIK_GROUP_LABEL_CSV') ?>
			</a>
		</li>
		<li>
			<a data-toggle="tab" href="#publishing-search">
				<?php echo Text::_('COM_FABRIK_GROUP_LABEL_SEARCH') ?>
			</a>
		</li>
	</ul>

	<div class="tab-content">
		<div class="tab-pane active" id="publishing-details">
			<fieldset class="form-horizontal">
				<?php foreach ($this->form->getFieldset('publishing-details') as $this->field) :
					require '_control_group.php';
				endforeach;
				?>
			</fieldset>
		</div>

		<div class="tab-pane" id="publishing-rss">
			<fieldset class="form-horizontal">
				<?php foreach ($this->form->getFieldset('rss') as $this->field) :
					require '_control_group.php';
				endforeach;
				?>
			</fieldset>
		</div>

		<div class="tab-pane" id="publishing-csv">
			<fieldset class="form-horizontal">
				<?php
				foreach ($this->form->getFieldset('csv') as $this->field) :
					require '_control_group.php';
				endforeach;
				foreach ($this->form->getFieldset('csvauto') as $this->field) :
					require '_control_group.php';
				endforeach;
				?>
			</fieldset>
		</div>

		<div class="tab-pane" id="publishing-search">
			<fieldset class="form-horizontal">
				<div class="alert"><?php echo Text::_('COM_FABRIK_SPECIFY_ELEMENTS_IN_DETAILS_FILTERS'); ?></div>
				<?php foreach ($this->form->getFieldset('search') as $this->field) :
					require '_control_group.php';
				endforeach;
				?>
			</fieldset>
		</div>
	</div>
</div>
