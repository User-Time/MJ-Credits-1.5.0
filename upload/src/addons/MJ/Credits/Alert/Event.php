<?php

namespace MJ\Credits\Alert;

use XF\Entity\UserAlert;
use XF\Mvc\Entity\Entity;

class Event extends \XF\Alert\AbstractHandler
{
	public function getTemplateName($action)
	{
		$cEvent = \XF::app()->container('mjcEvent');
		$templateName = '';
		if($cEvent){
			$handler = $cEvent->handler($action);
			if($handler){
				$handler->getAlertTemplate();
			}
		}
		if($handler){
			$templateName = $handler->getAlertTemplate();
		}
		return $templateName ? : 'public:mjc_alert_event';
	}

	public function getTemplateData($action, UserAlert $alert, Entity $content = null)
	{
		$data = parent::getTemplateData($action, $alert, $content);
		$extraData = $alert->extra_data;

		$data['amountFormatted'] = \MJ\Credits\Util\Money::formatAmount($extraData['amount'], $extraData['currency_id'], false);
		$data['amount'] = $extraData['amount'];
		$cEvent = \XF::app()->container('mjcEvent');
		if($cEvent){
			$handler = $cEvent->handler($content->definition_id);
			if($handler){
				$handler->getAlertData($content, $data);
			}
		}

		return $data;
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Currency', 'EventDefinition'];
	}

	public function getOptOutActions()
	{
		$eventDefinitions = \XF::app()->container('mjc.eventDefinition');
		if(!$eventDefinitions){
			return [];
		}
		return array_keys($eventDefinitions);
	}

	public function getOptOutDisplayOrder()
	{
		return 400001;
	}

	/**
	 *
	 *
	 * @return array
	 */
	public function getOptOutsMap()
	{
		$optOuts = $this->getOptOutActions();
		if (!$optOuts)
		{
			return [];
		}

		return array_combine($optOuts, array_map(function($action)
		{
			return \XF::phrase('mjc_trigger_credit_event_x', [
				'event' => \XF::phrase('mjc_event_def_title.' . $action)
			]);
		}, $optOuts));
	}
}
