<?php
/**
 * Admin Connection Tmpl
 *
 * @package     Joomla.Administrator
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2013 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 * @since       3.0
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Helpers\HTML;
use Fabrik\Helpers\Text;

JHtml::addIncludePath(JPATH_COMPONENT . '/helpers/html');
JHtml::_('behavior.tooltip');
HTML::formvalidation();
JHtml::_('behavior.keepalive');

?>
<form action="<?php JRoute::_('index.php?option=com_fabrik'); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">

	<div class="row-fluid">
		<?php if ($this->item->host != "") : ?>
			<li>
				<label><?php echo Text::_('COM_FABRIK_ENTER_PASSWORD_OR_LEAVE_AS_IS'); ?></label>
			</li>
		<?php endif; ?>
		<fieldset class="form-horizontal">
			<legend>
				<?php echo Text::_('COM_FABRIK_DETAILS'); ?>
			</legend>
			<?php foreach ($this->form->getFieldset('details') as $this->field) :
				require '_control_group.php';
			endforeach;
			?>
		</fieldset>
	</div>

	<input type="hidden" name="task" value="" />
	<?php echo JHtml::_('form.token'); ?>
</form>