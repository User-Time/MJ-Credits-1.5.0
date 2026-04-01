<?php

namespace MJ\Credits\Service\Event;

use MJ\Credits\Entity\Currency;

class Import extends \XF\Service\AbstractService
{
	/**
	 * @var Currency|null
	 */
	protected $currency;

	protected $definitionIds;

	public function setCurrency(Currency $currency)
	{
		$this->currency = $currency;
	}

	public function setDefinitionIds($definitionIds)
	{
		$this->definitionIds = array_filter($definitionIds);
	}

	public function getCurrency()
	{
		return $this->currency;
	}

	public function isValidXml($rootElement, &$error = null)
	{
		if (!($rootElement instanceof \SimpleXMLElement))
		{
			$error = \XF::phrase('mjc_provided_file_is_not_valid_events_xml');
			return false;
		}

		if ($rootElement->getName() != 'events')
		{
			$error = \XF::phrase('mjc_provided_file_is_not_valid_events_xml');
			return false;
		}

		if ((string)$rootElement['export_version'] != (string)Export::EXPORT_VERSION_ID)
		{
			$error = \XF::phrase('mjc_this_events_xml_file_was_not_built_for_this_version_of_credits');
			return false;
		}

		return true;
	}

	public function importFromXml(\SimpleXMLElement $document)
	{
		$db = $this->db();
		$db->beginTransaction();

		$addOnId = (string)$document['addon_id'];

		$currency = $this->getCurrency();
		foreach ($document as $xmlEvent)
		{
			$event = $this->em()->create('MJ\Credits:Event');

			$definitionId = (string)$xmlEvent['definition_id'];

			if($this->definitionIds && !in_array($definitionId, $this->definitionIds)){
				continue;
			}

			$event->definition_id = (string)$xmlEvent['definition_id'];
			$event->currency_id = $currency->currency_id;
			$event->amount = (float)$xmlEvent['amount'];
			$event->moderate_transactions = (int)$xmlEvent['moderate_transactions'];
			$event->send_alert = (int)$xmlEvent['send_alert'];
			$event->active = (int)$xmlEvent['active'];

			$userGroupIds = (string)$xmlEvent['allowed_user_group_ids'];
			$event->allowed_user_group_ids = $userGroupIds ? explode(',', $userGroupIds) : [-1];

			$options = (string)$xmlEvent->options;
			$options = json_decode($options, true);

			$handler = $event->getHandler();
			if($handler){
				if($options && is_array($options)){
					$event->options = array_replace($options, $handler->getDefaultEventOptions());
				}else{
					$event->options = $handler->getDefaultEventOptions();
				}

				$event->save(false, false);

				$title = $event->getMasterPhrase(true);
				$title->phrase_text = (string)$xmlEvent->title;
				$title->save(false, false);

				$desc = $event->getMasterPhrase(false);
				$desc->phrase_text = (string)$xmlEvent->description;
				$desc->save(false, false);
			}
		}

		$this->repository('MJ\Credits:Event')->rebuildEventCache();

		$db->commit();

		return $currency;
	}
}
