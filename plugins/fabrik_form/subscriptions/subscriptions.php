<?php
/**
 *  Redirects the browser to subscriptions to perform payment
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.form.subscriptions
 * @copyright   Copyright (C) 2005-2015 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\String\String;
use Fabrik\Helpers\ArrayHelper;
use Fabrik\Helpers\Worker;
use Fabrik\Helpers\Text;

// Require the abstract plugin class
require_once COM_FABRIK_FRONTEND . '/models/plugin-form.php';
JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_fabrik/tables');
JTable::addIncludePath(JPATH_SITE . '/plugins/fabrik_form/subscriptions/tables');

/**
 * Redirects the browser to subscriptions to perform payment
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.form.subscriptions
 * @since       3.0
 */

class PlgFabrik_FormSubscriptions extends PlgFabrik_Form
{
	/**
	 * Gateway
	 *
	 * @var object
	 */
	protected $gateway = null;

	/**
	 * Billing Cycle
	 *
	 * @var object
	 */
	protected $billingCycle = null;

	/**
	 * Construct
	 *
	 * @param   object  &$subject  Subject to observer
	 * @param   array   $config    Config
	 */
	public function __construct(&$subject, $config = array())
	{
		// Include the JLog class.
		jimport('joomla.log.log');

		// Add the logger.
		JLog::addLogger(array('text_file' => 'fabrik.subs.log.php'));
		parent::__construct($subject, $config);
	}

	/**
	 * Get the business email either based on the accountemail field or the value
	 * found in the selected accountemail_element
	 *
	 * @return  string  email
	 */

	protected function getBusinessEmail()
	{
		$w = $this->getWorker();
		$data = $this->getProcessData();
		$params = $this->getParams();
		$field = $params->get('subscriptions_testmode') == 1 ? 'subscriptions_sandbox_email' : 'subscriptions_accountemail';

		return $w->parseMessageForPlaceHolder($params->get($field), $data);
	}

	/**
	 * Get transaction amount based on the cost field or the value
	 * found in the selected cost_element
	 *
	 * @return  string  cost
	 */

	protected function getAmount()
	{
		$billingCycle = $this->getBillingCycle();

		return $billingCycle->cost;
	}

	/**
	 * Get the select billing cycles row
	 *
	 * @return  object  row
	 */

	protected function getBillingCycle()
	{
		if (!isset($this->billingCycle))
		{
			try
			{
				$db = $this->db;
				$query = $db->getQuery(true);
				$data = $this->getProcessData();
				$cycleField = $db->replacePrefix('#__fabrik_subs_users___billing_cycle');
				$cycleId = (int) $data[$cycleField . '_raw'][0];

				if ($cycleId === 0)
				{
					throw new Exception('No billing cycle found in request', 404);
				}

				$query->select('*')->from('#__fabrik_subs_plan_billing_cycle')->where('id = ' . $cycleId);
				$db->setQuery($query);
				$this->billingCycle = $db->loadObject();

				if ($error = $db->getErrorMsg())
				{
					throw new Exception($error);
				}

				if (empty($this->billingCycle))
				{
					throw new Exception('No billing cycle found', 404);
				}
			}
			catch (Exception $e)
			{
				$this->setError($e);

				return false;
			}
		}

		return $this->billingCycle;
	}

	/**
	 * Get the selected gateway (paypal single payment / subscription)
	 *
	 * @return  false|object  row or false
	 */

	protected function getGateway()
	{
		if (!isset($this->gateway))
		{
			try
			{
				$db = $this->db;
				$query = $db->getQuery(true);
				$data = $this->getProcessData();
				$gatewayField = $db->replacePrefix('#__fabrik_subs_users___gateway');

				$id = (int) $data[$gatewayField . '_raw'][0];
				$query->select('*')->from('#__fabrik_subs_payment_gateways')->where('id = ' . $id);
				$db->setQuery($query);
				$this->gateway = $db->loadObject();

				if ($error = $db->getErrorMsg())
				{
					throw new Exception($error);
				}

				if (empty($this->gateway))
				{
					throw new Exception('No gateway cycle found', 404);
				}
			}
			catch (Exception $e)
			{
				$this->setError($e);

				return false;
			}
		}

		return $this->gateway;
	}

	/**
	 * Get transaction item name based on the item field or the value
	 * found in the selected item_element
	 *
	 * @return  array  item name
	 */

	protected function getItemName()
	{
		$data = $this->getProcessData();

		// @TODO replace with look up of plan name and billing cycle
		return array($data['jos_fabrik_subs_users___plan_id_raw'], $data['jos_fabrik_subs_users___plan_id'][0]);
	}

	/**
	 * Append additional paypal values to the data to send to paypal
	 *
	 * @param   array  &$opts  paypal options
	 *
	 * @return  void
	 */

	protected function setSubscriptionValues(&$opts)
	{
		$w = $this->getWorker();
		$data = $this->getProcessData();
		$gateWay = $this->getGateway();

		$item = $data['jos_fabrik_subs_users___billing_cycle'][0] . ' ' . $data['jos_fabrik_subs_users___gateway'][0];
		$item_raw = $data['jos_fabrik_subs_users___billing_cycle_raw'][0];

		$db = $this->db;
		$query = $db->getQuery(true);
		$query->select('cost, label, plan_name, duration AS p3, period_unit AS t3, ' . $db->q($item_raw) . ' AS item_number ')
		->from('#__fabrik_subs_plan_billing_cycle')->where('id = ' . $db->q($item_raw));

		$db->setQuery($query);
		$sub = $db->loadObject();

		// @TODO test replace various placeholders
		$filter = JFilterInput::getInstance();
		$post = $filter->clean($_REQUEST, 'array');
		$name = $this->config->get('sitename') . ' {plan_name}  User: {jos_fabrik_subs_users___name} ({jos_fabrik_subs_users___username})';
		$tmp = array_merge($post, ArrayHelper::fromObject($sub));

		// 'http://fabrikar.com/ '.$sub->item_name. ' - User: subtest26012010 (subtest26012010)';
		$opts['item_name'] = $w->parseMessageForPlaceHolder($name, $tmp);
		$opts['invoice'] = uniqid('', true);

		if ($gateWay->subscription == 1)
		{
			if (is_object($sub))
			{
				$opts['p3'] = $sub->p3;
				$opts['t3'] = $sub->t3;
				$opts['a3'] = $sub->cost;
				$opts['no_note'] = 1;
				$opts['custom'] = '';
				$opts['src'] = 1;
				unset($opts['amount']);
			}
			else
			{
				throw new RuntimeException('Could not determine subscription period, please check your settings', 500);
			}
		}
	}

	/**
	 * Get Worker
	 *
	 * @return Worker
	 */

	protected function getWorker()
	{
		if (!isset($this->w))
		{
			$this->w = new Worker;
		}

		return $this->w;
	}

	/**
	 * Set up the html to be injected into the bottom of the form
	 *
	 * @return  void
	 */

	public function getBottomContent()
	{
		$pendingSub = $this->pendingSub();

		if ($pendingSub !== false)
		{
			$this->html = '<input type="hidden" name="subscription_id" value="' . (int) $pendingSub->id . '" />';
		}
	}

	/**
	 * Inject custom html into the bottom of the form
	 *
	 * @param   int  $c  Plugin counter
	 *
	 * @return  string  html
	 */

	public function getBottomContent_result($c)
	{
		return $this->html;
	}

	/**
	 * Run when the form is loaded - before its data has been created
	 * data found in $formModel->data
	 *
	 * @return	bool
	 */

	public function onBeforeLoad()
	{
		$pendingSub = $this->pendingSub();

		if ($pendingSub !== false)
		{
			/* $input->set('usekey', false);
			$formModel->unsetData();
			$formModel->setRowId($pendingSub->id); */
			$this->app->enqueueMessage('We found an existing pending subscription from ' . $pendingSub->signup_date);
		}

		return true;
	}
/*
	public function onLoad()
	{
		$params = $this->getParams();
		$formModel = $this->getModel();
		$pendingSub = $this->pendingSub($formModel, false);
		if ($pendingSub !== false)
		{
			$app->enqueueMessage('We found an existing pending subscription from ' . $pendingSub->signup_date);
			$formModel->setRowId($pendingSub->id);
			$formModel->data['jos_fabrik_subs_users___gateway'] = $pendingSub->type;
			$formModel->data['jos_fabrik_subs_users___gateway_raw'] = $pendingSub->type;
			$formModel->data['jos_fabrik_subs_users___billing_cycle'] = $pendingSub->billing_cycle_id;
			$formModel->data['jos_fabrik_subs_users___billing_cycle_raw'] = $pendingSub->billing_cycle_id;
		}
	} */

	/**
	 * Test if the subscription is pending
	 *
	 * @param   bool  $newRow  Is it a  new subscription
	 *
	 * @return  bool
	 */

	protected function pendingSub($newRow = true)
	{
		// Check if the user has pending subscriptions
		$formModel = $this->getModel();
		$rowId = $formModel->getRowId();

		if (($rowId === '' || !$newRow) && $this->user->get('id') !== 0)
		{
			$db = $this->db;
			$query = $db->getQuery(true);
			$query->select('*')->from('#__fabrik_subs_subscriptions')->where('userid = ' . (int) $this->user->get('id'))
			->where('status = ' . $db->q('Pending'))
			->order('signup_date DESC');
			$db->setQuery($query);
			$pendingSubs = $db->loadObjectList();

			if (!empty($pendingSubs))
			{
				// Found so set the row id to the found pending subscription
				$pendingSub = $pendingSubs[0];

				return $pendingSub;
			}
		}

		return false;
	}

	/**
	 * Run right at the end of the form processing
	 * form needs to be set to record in database for this to hook to be called
	 *
	 * @return	bool
	 */

	public function onAfterProcess()
	{
		$formModel = $this->getModel();
		$params = $this->getParams();
		$input = $this->app->input;
		$this->data = $formModel->fullFormData;

		if (!$this->shouldProcess('subscriptions_conditon'))
		{
			return true;
		}

		$w = $this->getWorker();
		$ipn = $this->getIPNHandler();
		$testMode = $params->get('subscriptions_testmode', false);
		$url = $testMode == 1 ? 'https://www.sandbox.paypal.com/us/cgi-bin/webscr?' : 'https://www.paypal.com/cgi-bin/webscr?';
		$opts = array();
		$gateway = $this->getGateway();
		$opts['cmd'] = $gateway->subscription ? '_xclick-subscriptions' : '_xclick';
		$opts['business'] = $this->getBusinessEmail();
		$opts['amount'] = $this->getAmount();
		list($item_raw, $item) = $this->getItemName();
		$opts['item_name'] = $item;
		$this->setSubscriptionValues($opts);
		$opts['currency_code'] = $this->getCurrencyCode();
		$opts['notify_url'] = $this->getNotifyUrl();
		$opts['return'] = $this->getReturnUrl();
		$sub = $this->createSubscription();

		// If paying for an existing subscription get the id
		$subscriptionId = $input->getInt('subscription_id', 0);

		if ($subscriptionId !== 0)
		{
			// Updating a subscription - load the invoice
			$invoice = JTable::getInstance('Invoice', 'FabrikTable');
			$invoice->load(array('subscr_id' => $sub->id));
			$opts['invoice'] = $invoice->invoice_number;

			// In case the user has altered the pending subscriptions plan.
			$this->setInvoicePaymentOptions($invoice);
		}
		else
		{
			$invoice = $this->createInvoice($sub);
		}

		$opts['custom'] = $this->data['formid'] . ':' . $invoice->id;
		$qs = array();

		foreach ($opts as $k => $v)
		{
			$qs[] = $k . '=' . $v;
		}

		$url .= implode('&', $qs);

		// $$$ rob 04/02/2011 no longer doing redirect from ANY plugin EXCEPT the redirect plugin
		// - instead a session var is set as the preferred redirect url

		$session = JFactory::getSession();
		$context = $formModel->getRedirectContext();
		$surl = (array) $session->get($context . 'url', array());
		$surl[$this->renderOrder] = $url;
		$session->set($context . 'url', $surl);

		// @TODO use JLog instead of fabrik log
		// JLog::add($subject . ', ' . $body, JLog::NOTICE, 'com_fabrik');
		return true;
	}

	/**
	 * Get the currency code for the transaction e.g. USD
	 *
	 * @return  string  currency code
	 */

	protected function getCurrencyCode()
	{
		$cycle = $this->getBillingCycle();
		$data = $this->getProcessData();

		return $this->getWorker()->parseMessageForPlaceHolder($cycle->currency, $data);
	}

	/**
	 * Get the url that payment notifications (IPN) are sent to
	 *
	 * @return  string  url
	 */

	protected function getNotifyUrl()
	{
		$formModel = $this->getModel();
		$params = $this->getParams();
		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');
		$testSite = $params->get('subscriptions_test_site', '');
		$testSiteQs = $params->get('subscriptions_test_site_qs', '');
		$testMode = $params->get('subscriptions_testmode', false);
		$ppurl = ($testMode == 1 && !empty($testSite)) ? $testSite : COM_FABRIK_LIVESITE;
		$ppurl .= '/index.php?option=com_' . $package . '&view=pluginAjax&formid=' . $formModel->get('id')
		. '&g=form&plugin=subscriptions&method=ipn';

		if ($testMode == 1 && !empty($testSiteQs))
		{
			$ppurl .= $testSiteQs;
		}

		$ppurl .= '&renderOrder=' . $this->renderOrder;

		return urlencode($ppurl);
	}

	/**
	 * Make the return url, this is the page you return to after paypal has component the transaction.
	 *
	 * @return  string  url.
	 */

	protected function getReturnUrl()
	{
		$formModel = $this->getModel();
		$package = $this->app->getUserState('com_fabrik.package', 'fabrik');
		$url = '';
		$params = $this->getParams();
		$testSite = $params->get('subscriptions_test_site', '');
		$testSiteQs = $params->get('subscriptions_test_site_qs', '');
		$testMode = (bool) $params->get('subscriptions_testmode', false);

		$qs = 'index.php?option=com_' . $package . '&view=pluginAjax&formid=' . $formModel->get('id')
		. '&g=form&plugin=subscriptions&method=thanks&rowid=' . $this->data['rowid'] . '&renderOrder=' . $this->renderOrder;

		if ($testMode)
		{
			$url = !empty($testSite) ? $testSite . $qs : COM_FABRIK_LIVESITE . $qs;

			if (!empty($testSiteQs))
			{
				$url .= $testSiteQs;
			}
		}
		else
		{
			$url = COM_FABRIK_LIVESITE . $qs;
		}

		return urlencode($url);
	}

	/**
	 * Thanks message
	 *
	 * @return  void
	 */

	public function onThanks()
	{
		$input = $this->app->input;
		$formid = $input->getString('formid');
		$rowId = $input->getString('rowid', '', 'string');
		$formModel = new \Fabrik\Admin\Models\Form;
		$formModel->setId($formid);
		$params = $formModel->getParams();
		$ret_msg = (array) $params->get('subscriptions_return_msg');
		$ret_msg = array_values($ret_msg);
		$ret_msg = ArrayHelper::getValue($ret_msg, 0);

		if ($ret_msg)
		{
			$w = $this->getWorker();
			$listModel = $formModel->getlistModel();
			$row = $listModel->getRow($rowId);
			$ret_msg = $w->parseMessageForPlaceHolder($ret_msg, $row);

			if (String::stristr($ret_msg, '[show_all]'))
			{
				$all_data = array();

				foreach ($_REQUEST as $key => $val)
				{
					$all_data[] = "$key: $val";
				}

				$input->set('show_all', implode('<br />', $all_data));
			}

			$ret_msg = str_replace('[', '{', $ret_msg);
			$ret_msg = str_replace(']', '}', $ret_msg);
			$ret_msg = $w->parseMessageForPlaceHolder($ret_msg, $_REQUEST);
			echo $ret_msg;
		}
		else
		{
			echo Text::_("thanks");
		}
	}

	/**
	 * Called from subscriptions at the end of the transaction
	 *
	 * TO test the IPN you can login to your paypal acc, and go to history -> IPN History
	 * then use the 'Notification URL' along with the 'IPN Message' as the querystring
	 * PLUS "&fakeit=1"
	 *
	 * @return  void
	 */

	public function onIpn()
	{
		$input = $this->app->input;
		JLog::add($_SERVER['REQUEST_URI'] . ' ' . http_build_query($_REQUEST), JLog::INFO, 'fabrik.ipn.start');

		// Lets try to load in the custom returned value so we can load up the form and its parameters
		$custom = $input->get('custom', '', 'string');
		list($formid, $invoiceId) = explode(':', $custom);

		$input->set('invoiceid', $invoiceId);
		$mail = JFactory::getMailer();

		// Pretty sure they are added but double add
		$formModel = new \Fabrik\Admin\Models\Form;
		$formModel->setId($formid);
		$listModel = $formModel->getlistModel();
		$params = $formModel->getParams();
		$table = $listModel->getTable();
		$db = $listModel->getDb();

		$renderOrder = $input->getInt('renderOrder');
		$ipn_txn_field = 'pp_txn_id';
		$ipn_payment_field = 'amount';

		$ipn_status_field = 'pp_payment_status';

		$w = $this->getWorker();

		$email_from = $admin_email = $this->config->get('mailfrom');

		// Read the post from Subscriptions system and add 'cmd'
		$req = 'cmd=_notify-validate';

		// For
		$fake = $input->getInt('fakeit');

		if ($fake == 1)
		{
			$request = $_GET;
		}
		else
		{
			$request = $_POST;
		}

		foreach ($request as $key => $value)
		{
			if ($key !== 'fakeit')
			{
				$value = urlencode(stripslashes($value));
				$req .= '&' . $key . '=' . $value;
			}
		}

		$sandBox = $input->get('test_ipn') == 1;

		// Post back to Paypal to validate
		$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
		$header .= $sandBox ? "Host: www.sandbox.paypal.com:443\r\n" : "Host: www.paypal.com:443\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . String::strlen($req) . "\r\n\r\n";

		$subscriptionsurl = $sandBox ? 'ssl://www.sandbox.paypal.com' : 'ssl://www.paypal.com';

		// Assign posted variables to local variables
		$item_name = $input->get('item_name', '', 'string');
		$item_number = $input->get('item_number', '', 'string');
		$payment_status = $input->get('payment_status', '', 'string');
		$payment_amount = $input->get('mc_gross', '', 'string');
		$payment_currency = $input->get('mc_currency', '', 'string');
		$txn_id = $input->get('txn_id', '', 'string');
		$txn_type = $input->get('txn_type', '', 'string');
		$receiver_email = $input->get('receiver_email', '', 'string');
		$payer_email = $input->get('payer_email', '', 'string');

		$status = true;
		$res = 'IPN never fired';
		$err_msg = '';
		$err_title = '';

		if (empty($formid) || empty($invoiceId))
		{
			$status = false;
			$err_title = 'form.subscriptions.ipnfailure.custom_error';
			$err_msg = "formid or rowid empty in custom: $custom";
		}
		else
		{
			// @TODO implement a curl alternative as fsockopen is not always available
			$fp = fsockopen($subscriptionsurl, 443, $errno, $errstr, 30);

			if (!$fp)
			{
				$status = false;
				$err_title = 'form.subscriptions.ipnfailure.fsock_error';
				$err_msg = "fsock error: $errno;$errstr";
			}
			else
			{
				fputs($fp, $header . $req);

				while (!feof($fp))
				{
					$res = fgets($fp, 1024);
					/*subscriptions steps (from their docs):
					 * check the payment_status is Completed
					* check that txn_id has not been previously processed
					* check that receiver_email is your Primary Subscriptions email
					* check that payment_amount/payment_currency are correct
					* process payment
					*/
					if (String::strcmp(strtoupper($res), "VERIFIED") == 0)
					{
						$query = $db->getQuery(true);
						$query->select($ipn_status_field)->from('#__fabrik_subs_invoices')
						->where($db->qn($ipn_txn_field) . ' = ' . $db->q($txn_id));
						$db->setQuery($query);
						$txn_result = $db->loadResult();

						if ($txn_type == 'subscr_signup')
						{
							// Just a notification - no payment yet
						}
						else
						{
							if (!empty($txn_result) && $txn_type != 'subscr_signup')
							{
								if ($txn_result == 'Completed')
								{
									if ($payment_status != 'Reversed' && $payment_status != 'Refunded')
									{
										$status = false;
										$err_title = 'form.subscriptions.ipnfailure.txn_seen';
										$err_msg = "transaction id already seen as Completed, new payment status makes no sense: $txn_id, $payment_status"
										. (string) $query;
									}
								}
								elseif ($txn_result == 'Reversed')
								{
									if ($payment_status != 'Canceled_Reversal')
									{
										$status = false;
										$err_title = 'form.subscriptions.ipnfailure.txn_seen';
										$err_msg = "transaction id already seen as Reversed, new payment status makes no sense: $txn_id, $payment_status";
									}
								}
							}

							if ($status)
							{
								$set_list = array();

								$set_list[$ipn_txn_field] = $txn_id;
								$set_list[$ipn_payment_field] = $payment_amount;
								$set_list[$ipn_status_field] = $payment_status;

								$ipn = $this->getIPNHandler($params, $renderOrder);

								if ($ipn !== false)
								{
									$request = $_REQUEST;
									$ipn_function = 'payment_status_' . $payment_status;

									if (method_exists($ipn, $ipn_function))
									{
										$status = $ipn->$ipn_function($listModel, $request, $set_list, $err_msg);

										if ($status == false)
										{
											break;
										}
									}
									else
									{
										$txn_type_function = 'txn_type_' . $txn_type;

										if (method_exists($ipn, $txn_type_function))
										{
											$status = $ipn->$txn_type_function($listModel, $request, $set_list, $err_msg);

											if ($status == false)
											{
												break;
											}
										}
									}
								}

								if (!empty($set_list))
								{
									$set_array = array();

									foreach ($set_list as $set_field => $set_value)
									{
										$set_value = $db->q($set_value);
										$set_field = $db->qn($set_field);
										$set_array[] = "$set_field = $set_value";
									}

									$query = $db->getQuery(true);
									$query->update('#__fabrik_subs_invoices')->set(implode(',', $set_array))->where('id = ' . $db->q($invoiceId));
									$db->setQuery($query);

									if (!$db->execute())
									{
										$status = false;
										$err_title = 'form.subscriptions.ipnfailure.query_error';
										$err_msg = 'sql query error: ' . $db->getErrorMsg();
									}
								}
							}
						}
					}
					elseif (String::strcmp($res, "INVALID") == 0)
					{
						$status = false;
						$err_title = 'form.subscriptions.ipnfailure.invalid';
						$err_msg = 'subscriptions postback failed with INVALID';
					}
				}

				fclose($fp);
			}
		}

		$receive_debug_emails = (array) $params->get('subscriptions_receive_debug_emails');
		$send_default_email = (array) $params->get('subscriptions_send_default_email');
		$emailtext = '';

		foreach ($_POST as $key => $value)
		{
			$emailtext .= $key . " = " . $value . "\n\n";
		}

		$logLevel = JLog::INFO;
		$logMessage = "transaction type: $txn_type \n///////////////// \n emailtext: " . $emailtext . "\n//////////////\nres= " . $res
		. "\n//////////////\n" . $req . "\n//////////////\n";

		if ($status == false)
		{
			$logLevel = JLog::CRITICAL;
			$subject = $this->config->get('sitename') . ": Error with Fabrik Subscriptions IPN";
			$logMessageTitle = $err_title;
			$logMessage .= $err_msg;
			$payer_emailtext = "There was an error processing your Subscriptions payment.  The administrator of this site has been informed.";
		}
		else
		{
			$subject = $this->config->get('sitename') . ': IPN ' . $payment_status;
			$logMessageTitle = 'form.subscriptions.ipn.' . $payment_status;
			$payer_subject = "Subscriptions success";
			$payer_emailtext = "Your Subscriptions payment was successfully processed.  The Subscriptions transaction id was $txn_id";
		}

		if ($receive_debug_emails == '1')
		{
			$mail->sendMail($email_from, $email_from, $admin_email, $subject, $emailtext, false);
		}

		if ($send_default_email == '1')
		{
			$mail->sendMail($email_from, $email_from, $payer_email, $payer_subject, $payer_emailtext, false);
		}

		if (isset($ipn_function))
		{
			$logMessage .= "\n IPN custom function = $ipn_function";
		}
		else
		{
			$logMessage .= "\n No IPN custom function";
		}

		if (isset($txn_type_function))
		{
			$logMessage .= "\n IPN custom transaction function = $txn_type_function";
		}
		else
		{
			$logMessage .= "\n No IPN custom transaction function ";
		}

		JLog::add($logMessage, $logLevel, $logMessageTitle);
		jexit();
	}

	/**
	 * Get the custom IPN class
	 *
	 * @return	object	ipn handler class
	 */

	protected function getIPNHandler()
	{
		$ipn = 'plugins/fabrik_form/subscriptions/scripts/ipn.php';

		if (JFile::exists($ipn))
		{
			require_once $ipn;

			return new fabrikSubscriptionsIPN;
		}
		else
		{
			throw new RuntimeException('Missing subs IPN file', 404);
		}
	}

	/**
	 * Get plan
	 *
	 * @return  object  plan
	 */
	protected function getPlan()
	{
		if (!isset($this->plan))
		{
			try
			{
				$input = $this->app->input;
				$db = $this->db;
				$planField = $db->replacePrefix('#__fabrik_subs_users___plan_id');
				$planid = $input->getInt($planField, $input->getInt($planField . '_raw'));
				$query = $db->getQuery(true);

				$query->select('*')->from('#__fabrik_subs_plans')->where('id = ' . $planid);
				$db->setQuery($query);
				$this->plan = $db->loadObject();

				if ($error = $db->getErrorMsg())
				{
					throw new Exception($error);
				}

				if (empty($this->plan))
				{
					throw new Exception('No plan found', 404);
				}
			}
			catch (Exception $e)
			{
				$this->setError($e);

				return false;
			}
		}

		return $this->plan;
	}

	/**
	 * Create the subscription
	 *
	 * @return  JTable subscription
	 */

	protected function createSubscription()
	{
		$gateway = $this->getGateway();
		$plan = $this->getPlan();
		$date = JFactory::getDate();
		$input = $this->app->input;
		$sub = JTable::getInstance('Subscription', 'FabrikTable');

		// Replace fields with db prefix
		$billingCycle = $this->getBillingCycle();

		// If paying for an existing subscription get the id
		$subscriptionId = $input->getInt('subscription_id', 0);

		if ($subscriptionId !== 0)
		{
			$sub->id = $subscriptionId;
		}

		// If upgrading fall back to logged in user id
		$sub->userid = $input->getInt('newuserid', $this->user->get('id'));
		$sub->primary = 1;
		$sub->type = $gateway->id;
		$sub->status = $plan->free == 1 ? 'Active' : 'Pending';
		$sub->signup_date = $date->toSql();
		$sub->plan = $billingCycle->plan_id;
		$sub->lifetime = $input->getInt('lifetime', 0);
		$sub->recurring = $gateway->subscription;
		$sub->billing_cycle_id = $billingCycle->id;
		$input->setVar('recurring', $sub->recurring);
		$sub->store();

		return $sub;
	}

	/**
	 * Create an invoice for a subscription
	 *
	 * @param   JTable  $sub  Subscription row
	 *
	 * @return  JTable Invoice
	 */

	protected function createInvoice($sub)
	{
		$input = $this->app->input;
		$date = JFactory::getDate();

		$invoice = JTable::getInstance('Invoice', 'FabrikTable');
		$invoice->invoice_number = uniqid('', true);
		$input->setVar('invoice_number', $invoice->invoice_number);

		$this->setInvoicePaymentOptions($invoice);
		$invoice->created_date = $date->toSql();
		$invoice->subscr_id = $sub->id;
		$invoice->store();

		return $invoice;
	}

	/**
	 * Set the Invoice payment options
	 *
	 * @param   JTable  &$invoice  Invoice
	 *
	 * @return  void
	 */

	protected function setInvoicePaymentOptions(&$invoice)
	{
		$gateway = $this->getGateway();
		$invoice->gateway_id = $gateway->id;
		$billingCycle = $this->getBillingCycle();
		$invoice->currency = $billingCycle->currency;
		$invoice->amount = $billingCycle->cost;
	}
}
