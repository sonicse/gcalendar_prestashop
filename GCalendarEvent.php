<?php

class GCalendarEvent {
	
	private $_user;
	private $_pass;
	
	function __construct($user, $pass)
    {
		$this->_user = $user;
		$this->_pass = $pass;
	}
 	
	function addEvent($title = 'Новый заказ',
		$desc='Описание заказа', $where = 'интернет-магазин',
		$startDate,
		$endDate,
		$sms_reminder)
	{
		set_include_path('.' . PATH_SEPARATOR . $_SERVER['DOCUMENT_ROOT'].'/modules/gcalendar/' . PATH_SEPARATOR . get_include_path());

		include_once 'Zend/Loader.php';
		Zend_Loader::loadClass('Zend_Gdata');
		Zend_Loader::loadClass('Zend_Gdata_AuthSub');
		Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
		Zend_Loader::loadClass('Zend_Gdata_HttpClient');
		Zend_Loader::loadClass('Zend_Gdata_Calendar');
  
		$client = Zend_Gdata_ClientLogin::getHttpClient($this->_user, $this->_pass, "cl");
		
		$gdataCal = new Zend_Gdata_Calendar($client);
		$newEvent = $gdataCal->newEventEntry();
		$newEvent->title = $gdataCal->newTitle($title);
		$newEvent->where = array($gdataCal->newWhere($where));
		$newEvent->content = $gdataCal->newContent($desc);  
		$when = $gdataCal->newWhen();
		
		$when->startTime = $startDate;
		$when->endTime = $endDate;
		
		if( intval($sms_reminder) )
		{
			$reminder = $gdataCal->newReminder();
			$reminder->method = "sms";
			$reminder->minutes = "0"; 
			$when->reminders = array($reminder);
		}
		$newEvent->when = array($when);
		$createdEvent = $gdataCal->insertEvent($newEvent);
		
		return $createdEvent->id->text;
	}
}

?>