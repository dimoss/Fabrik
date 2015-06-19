<?php
/**
 * Admin Element Edit - List view Tmpl
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
<div class="tab-pane" id="tab-listview">
	<fieldset class="form-horizontal">
		<legend><?php echo Text::_('COM_FABRIK_LIST_VIEW_SETTINGS');?></legend>
		<ul class="nav nav-tabs">
			<li class="active">
					<a data-toggle="tab" href="#listview-details">
						<?php echo Text::_('COM_FABRIK_ELEMENT_LABEL_LIST_SETTINGS_DETAILS'); ?>
					</a>
				</li>
				<li>
					<a data-toggle="tab" href="#listview-icons">
						<?php echo Text::_('COM_FABRIK_ELEMENT_LABEL_ICONS_SETTINGS_DETAILS')?>
					</a>
				</li>
				<li>
					<a data-toggle="tab" href="#listview-filters">
						<?php echo Text::_('COM_FABRIK_ELEMENT_LABEL_FILTERS_DETAILS')?>
					</a>
				</li>
				<li>
					<a data-toggle="tab" href="#listview-css">
						<?php echo Text::_('COM_FABRIK_ELEMENT_LABEL_CSS_DETAILS')?>
					</a>
				</li>
				<li>
					<a data-toggle="tab" href="#listview-calculations">
						<?php echo Text::_('COM_FABRIK_ELEMENT_LABEL_CALCULATIONS_DETAILS')?>
					</a>
				</li>
		</ul>
	</fieldset>

	<div class="tab-content">
		<div class="tab-pane active" id="listview-details">
		    <fieldset class="form-horizontal">
				<?php foreach ($this->form->getFieldset('listsettings') as $this->field) :
					require '_control_group.php';
				endforeach;
				?>
				<?php foreach ($this->form->getFieldset('listsettings2') as $this->field) :
					require '_control_group.php';
				endforeach;
				?>
			</fieldset>
		</div>

		<div class="tab-pane" id="listview-icons">
			<fieldset class="form-horizontal">
				<?php foreach ($this->form->getFieldset('icons') as $this->field) :
					require '_control_group.php';
				endforeach;
				?>
			</fieldset>
		</div>

		<div class="tab-pane" id="listview-filters">
			<fieldset class="form-horizontal">
				<?php foreach ($this->form->getFieldset('filters') as $this->field) :
					require '_control_group.php';
				endforeach;
				?>
				<?php foreach ($this->form->getFieldset('filters2') as $this->field) :
					require '_control_group.php';
				endforeach;
				?>
			</fieldset>
		</div>

		<div class="tab-pane" id="listview-css">
			<fieldset class="form-horizontal">
				<?php foreach ($this->form->getFieldset('viewcss') as $this->field) :
					require '_control_group.php';
				endforeach;
				?>
			</fieldset>
		</div>

		<div class="tab-pane" id="listview-calculations">
			<fieldset class="form-horizontal">
				<div class="span6">
				<?php
				$fieldsets = $this->form->getFieldsets();
				$cals = array('calculations-sum', 'calculations-avg', 'calculations-median');
				foreach ($cals as $cal) :?>
					<legend><?php echo Text::_($fieldsets[$cal]->label); ?></legend>
					<?php foreach ($this->form->getFieldset($cal) as $this->field) :
						require '_control_group.php';
					endforeach;
				endforeach;
				?>
				</div>
				<div class="span6">
				<?php
				$cals = array('calculations-count', 'calculations-custom');
				foreach ($cals as $cal) :?>
					<legend><?php echo Text::_($fieldsets[$cal]->label); ?></legend>
					<?php foreach ($this->form->getFieldset($cal) as $this->field) :
						require '_control_group.php';
					endforeach;
				endforeach;
				?>
				</div>
			</fieldset>
		</div>
	</div>
</div>
