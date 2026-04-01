<?php

namespace MJ\Credits\Alert;

use XF\Entity\UserAlert;
use XF\Mvc\Entity\Entity;

class Transaction extends \XF\Alert\AbstractHandler
{
	public function getTemplateName($action)
	{
		return 'public:mjc_alert_transaction';
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Event', 'Currency'];
	}

	public function getOptOutDisplayOrder()
	{
		return 400000;
	}
}
