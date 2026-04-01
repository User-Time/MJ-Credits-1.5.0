<?php

namespace MJ\Credits\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Event extends Repository
{
	public function findEventsForList()
	{
		return $this->finder('MJ\Credits:Event');
	}

	public function findEventsOfCurrency($currencyId)
	{
		return $this->finder('MJ\Credits:Event')
				->with('EventDefinition')
				->where('currency_id', $currencyId)
				->order('EventDefinition.display_order')
				->order('EventDefinition.definition_id')
				->order('event_id');
	}

	/**
	 * @return Finder
	 */
	public function findEventDefinitionsForList($activeOnly = false)
	{
		$finder = $this->finder('MJ\Credits:EventDefinition')
					->order('display_order')
					->order('definition_id');
		if ($activeOnly)
		{
			$finder->with('AddOn')
				->whereOr(
					['AddOn.active', 1],
					['AddOn.addon_id', null]
				);
		}
		return $finder;
	}

	public function getEventDefinitionTitlePairs($activeOnly = false)
	{
		return $this->findEventDefinitionsForList($activeOnly)->fetch()->pluckNamed('title', 'definition_id');
	}

	public function prepareEvents($events)
	{
		foreach ($events as $event)
		{
			if(empty($event['title']) && !empty($event['title_phrase'])){
				$event['title'] = \XF::phrase($event['title_phrase']);
			}
			if(empty($event['description']) && !empty($event['description_phrase'])){
				$event['description'] = \XF::phrase($event['description_phrase']);
			}
		}

		return $events;
	}

	public function calculateFee($amount, $event)
	{
		$fee = 0;
		if(!empty($event['options']['fee']) && $event['options']['fee'] > 0){
			$fee = $event['options']['fee'];
		}

		if(!empty($event['options']['tax']) && $event['options']['tax'] > 0){
			$fee = $fee + (($amount - $fee)*$event['options']['tax'])/100;
		}
		return $fee;
	}

	public function calculateAmountWithFee($amount, $feeType, $event)
	{
		$fee = $this->calculateFee($amount, $event);

		$sendAmount = $amount;
		$receiveAmount = $amount;

		switch ($feeType) {
			case 'sender':
				$sendAmount += $fee;
				break;
			case 'receiver':
				$receiveAmount -= $fee;
				break;
			default:
				$sendAmount += $fee/2;
				$receiveAmount -= $fee/2;
				break;
		}

		return [$sendAmount, $receiveAmount];
	}

	public function getEventDefinitionCache()
	{
		$output = [];
		$eventDefinitions = $this->finder('MJ\Credits:EventDefinition')
			->with('AddOn')
			->whereOr(
				['AddOn.active', 1],
				['AddOn.addon_id', null]
			)
			->order('display_order')
			->order('definition_id');

		foreach ($eventDefinitions->fetch() as $eventDefinition)
		{
			$output[$eventDefinition->definition_id] = [
				'definition_id'    => $eventDefinition->definition_id,
				'definition_class' => $eventDefinition->definition_class,
				'display_order'    => $eventDefinition->display_order,
				'addon_id'         => $eventDefinition->addon_id
			];
		}

		return $output;
	}

	public function validateEvent(array $event, \XF\Entity\User $user = null, $ignoreEventPrivilege = false, &$error = null)
	{
		if(!$user){
			$user = \XF::visitor();
		}
		if(empty($event['active'])){
			$error = \XF::phrase('mjc_this_event_disabled');
			return false;
		}

		if(!$ignoreEventPrivilege && !$this->canUseEvent($event, $user)){
			if(\XF::visitor()->user_id != $user->user_id){
				$error = \XF::phrase('mjc_target_user_cannot_use_this_event');
			}else{
				$error = \XF::phrase('mjc_you_cannot_use_this_event');
			}

			return false;
		}
		return true;
	}

	public function canUseEvent($event, \XF\Entity\User $user)
	{
		if(!empty($event['allowed_user_group_ids'])){
			foreach ($event['allowed_user_group_ids'] as $userGroupId)
			{
				if ($userGroupId == -1 || $user->isMemberOf($userGroupId))
				{
					return true;
				}
			}
			return false;
		}

		return true;
	}

    /**
     * @param $type
     * @param bool $throw
     * @return |null
     * @throws \Exception
     */
    public function getHandler($type, $throw = true)
    {
        $handlerClass = \XF::app()->getContentTypeFieldValue($type, 'mjc_credits_event_handler_class');
        if (!$handlerClass)
        {
            if ($throw)
            {
                throw new \InvalidArgumentException("No event trigger handler for '$type'");
            }
            return null;
        }

        if (!class_exists($handlerClass))
        {
            if ($throw)
            {
                throw new \InvalidArgumentException("Event trigger handler for '$type' does not exist: $handlerClass");
            }
            return null;
        }

        $handlerClass = \XF::extendClass($handlerClass);
        return new $handlerClass($type);
    }

	public function canUseEventTrigger($definitionId, $options = [])
	{
		$currencies = $this->repository('MJ\Credits:Currency')->useableCurrencies($definitionId);
		if(!$currencies){
			return false;
		}
		if(!empty($options['numberCurrency']) && count($currencies) < $options['numberCurrency']){
			return false;
		}
		if(!empty($options['currencyId']) && !in_array($options['currencyId'], array_keys($currencies))){
			return false;
		}

		return true;
	}

    public function canDisplayEvent($definitionId)
    {
        $currencies = $this->repository('MJ\Credits:Currency')->useableCurrencies($definitionId);

        if(!$currencies){
            return false;
        }
         return true;
    }

	public function rebuildEventDefinitionCache()
	{
		$cache = $this->getEventDefinitionCache();
		\XF::registry()->set('mjcEventDefinition', $cache);
		return $cache;
	}

	public function getEventCache()
	{
		$output = [];
		$events = $this->finder('MJ\Credits:Event')
			->order('definition_id')
			->order('event_id');

		foreach ($events->fetch() as $event)
		{
			if(!isset($output[$event->definition_id])){
				$output[$event->definition_id] = [];
			}
			$eventCache = $event->toArray();
			$definition = $event->EventDefinition;
			if($definition){
				$eventCache['title_phrase'] = $event->getPhraseName(true, true);
				$eventCache['description_phrase'] = $event->getPhraseName(false);
				$output[$event->definition_id][$event->event_id] = $eventCache;
			}
		}

		return $output;
	}

	public function rebuildEventCache()
	{
		$cache = $this->getEventCache();
		\XF::registry()->set('mjcEvents', $cache);
		return $cache;
	}
}
