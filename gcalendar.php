<?php 

class GCalendar extends Module
{
	private $_data;
	private $_password;
	private $_user;
	private $_sms_new_order;
	
	static private $_tpl_sms_files = array(
		'name' => array(
			'new_orders' => 'sms_new_order',
			'out_of_stock' => 'sms_out_of_stock'
			),
		'ext' => array(
			'new_orders' => '.txt',
			'out_of_stock' => '.txt'
			)
	);

    public function __construct()
    {
        $this->name = 'gcalendar';
        $this->tab = 'Tools';
        $this->version = "0.1";
        
        parent::__construct();
        
        /* The parent construct is required for translations */
        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('GCalendar');
        $this->description = $this->l('Google Calendar Sync Module');
		$this->confirmUninstall = $this->l('Are you sure you want to delete your info?');
		
		/* Common vars */
		$this->_data = array('shopname' => Configuration::get('PS_SHOP_NAME'));
		
		/* Get config vars */		 
		$this->_password = Configuration::get('GCALENDAR_PASSWORD');
		$this->_user = Configuration::get('GCALENDAR_USER');
		$this->_sms_new_order = Configuration::get('GCALENDAR_SMS_NEW_ORDER');
	}

    function install()
    {
       if (!Configuration::updateValue('GCALENDAR_SMS_NEW_ORDER', 1)
			OR !parent::install()
			OR !$this->registerHook('newOrder'))
		{
			return false;
		}

		return true;
    }
	
	public function uninstall()
	{
		Configuration::deleteByName('GCALENDAR_PASSWORD');
		Configuration::deleteByName('GCALENDAR_USER');
		Configuration::deleteByName('GCALENDAR_SMS_NEW_ORDER');
		Configuration::deleteByName('GCALENDAR_OFFSET');
		
		return parent::uninstall();
	}

	private function _getTplBody($tpl_file, $vars = array())
	{
		$iso = Language::getIsoById(intval(Configuration::get(PS_LANG_DEFAULT)));
		$file = dirname(__FILE__).'/tpl/'.$iso.'/'.$tpl_file;
		if (!file_exists($file))
			die($file);
		$tpl = file($file);
		$template = str_replace(array_keys($vars), array_values($vars), $tpl);
		return (implode("\n", $template));
	}
	
	public function hookNewOrder($params)
	{
		include_once 'GCalendarEvent.php';
		
		if ( empty($this->_user) OR empty($this->_password))
			return ;
			
		$order = $params['order'];
		$customer = $params['customer'];
		
		$templateVars = array(
		 '{firstname}' => $customer->firstname,
		 '{lastname}' => $customer->lastname,
		 '{order_name}' => sprintf("%06d", $order->id),
		 '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
		 '{payment}' => $order->payment,
		 '{total_paid}' => $order->total_paid,
		 '{currency}' => $currency->sign);

		$desc = $this->_getTplBody(self::$_tpl_sms_files['name']['new_orders'].self::$_tpl_sms_files['ext']['new_orders'], $templateVars);
		
		$offset = intval(Configuration::get('GCALENDAR_OFFSET'));
		if (!Validate::isInt($offset) OR $offset < 0)
			$offset = 0;
		
		$event = new GCalendarEvent($this->_user, $this->_password);
		$event->addEvent('Новый заказ'
						,$desc
						,$this->_data['shopname']
						,date('c', time() + $offset)
						,date('c', time() + $offset)
						,$this->_sms_new_order );
	}
	
	public function getContent()
	{
		include_once 'GCalendarEvent.php';
		$this->_html = '<h2>'.$this->displayName.'</h2>';

		if (!empty($_POST))
		{
			if (isset($_POST['btnTest']))
			{
				if (!empty($this->_user) AND !empty($this->_password) )
				{
					$offset = intval(Configuration::get('GCALENDAR_OFFSET'));
					if (!Validate::isInt($offset) OR $offset < 0)
						$offset = 0;
			
					$event = new GCalendarEvent($this->_user, $this->_password);
					$event->addEvent('Test order', 'Description', $this->_data['shopname']
										,date('c', time() + $offset)
										,date('c', time() + $offset)
										,$this->_sms_new_order );
					
					$this->_html .= $this->displayConfirmation($this->l('Check your google calendar'));
					//$this->_html .= $this->displayError($this->l('Error while sending message'));
				}
				else
					$this->_html .= $this->displayError($this->l('Fill login and password field'));
			}
			else
			{
				$this->_postValidation();
				if (!sizeof($this->_postErrors))
					$this->_postProcess();
				else
					foreach ($this->_postErrors AS $err)
						$this->_html .= $this->displayError($err);
			}
		}
		
		$this->_displaySettings();
		$this->_displayTest();

		return $this->_html;
	}
	
	private function _displayTest()
	{
		include_once 'GCalendarEvent.php';
		
		$btn_txt = 'Create order';
		
		$this->_html .= '
		<fieldset><legend><img src="'.$this->_path.'informations.gif" alt="" title="" /> '.$this->l('Information').'</legend>
			<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
				<label>'.$this->l('Create "Test Order":').'</label>
				<div class="margin-form">
				<input class="button" name="btnTest" value="'.$btn_txt.'" type="submit" style="margin-bottom:10px;" />
				<br />'.$this->l('Don\'t forget setup your mobile settings in google calendar if your want to receive sms').'</div>';
		$this->_html .= '
			</form>
		</fieldset><br />';
	}
	
	private function _displaySettings()
	{
		if (!isset($_POST['btnSubmit']))
		{
			if ($this->_user)
			{
				$_POST['user'] = $this->_user;
				$_POST['password'] = $this->_password;
				$_POST['sms_new_order'] = $this->_sms_new_order;
				$_POST['offset'] = Configuration::get('GCALENDAR_OFFSET');
			}
		}
		
		$this->_html .= '<fieldset><legend><img src="'.$this->_path.'prefs.gif" alt="" title="" /> '.$this->l('Settings').'</legend>
		<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
			<label>'.$this->l('Google account:').'</label>
			<div class="margin-form"><input type="text" name="user" value="'.(isset($_POST['user']) ? $_POST['user'] : '').'" /></div>
			<label>'.$this->l('Password:').'</label>
			<div class="margin-form"><input type="text" name="password" value="'.(isset($_POST['password']) ? $_POST['password'] : '').'" /></div>
			
			<label>'.$this->l('Event delay:').'</label>
			<div class="margin-form"><input type="text" name="offset" value="'.(isset($_POST['offset']) ? $_POST['offset'] : '').'" />
			<br />'.$this->l('in seconds (default = 0)').'</div>
			
			<label>'.$this->l('Send sms on new order:').'</label>
			<div class="margin-form"><div style="color:#000000; font-size:12px; margin-bottom:6px"><input type="checkbox" value="1" name="sms_new_order" '.( (isset($_POST['sms_new_order']) AND $_POST['sms_new_order'] == '1') ? 'checked' : '').' />&nbsp;'.$this->l('Yes').'</div>'.$this->l('Send SMS if a new order is made').'</div>
			
			<div class="margin-form"><input class="button" name="btnSubmit" value="'.$this->l('Update settings').'" type="submit" /></div>
		</form></fieldset>';
	}
	
	private function _postProcess()
	{
		Configuration::updateValue('GCALENDAR_USER', $_POST['user']);
		Configuration::updateValue('GCALENDAR_PASSWORD', $_POST['password']);
		Configuration::updateValue('GCALENDAR_SMS_NEW_ORDER', isset($_POST['sms_new_order']) ? 1 : 0);
		
		$offset = $_POST['offset'];
		if (isset($offset) AND Validate::isInt($offset) AND $offset >= 0)
			Configuration::updateValue('GCALENDAR_OFFSET', $offset);

		$this->_html .= $this->displayConfirmation($this->l('Settings updated'));
	}
	
	private function _postValidation()
	{
		if (empty($_POST['user']))
			$this->_postErrors[] = $this->l('Username is mandatory');
		elseif (empty($_POST['password']))
			$this->_postErrors[] = $this->l('Password is mandatory');
	}

} 

?>