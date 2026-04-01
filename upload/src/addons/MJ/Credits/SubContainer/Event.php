<?php

namespace MJ\Credits\SubContainer;

use XF\Container;

class Event extends \XF\SubContainer\AbstractSubContainer
{
	public function initialize()
	{
		$container = $this->container;

		$container['mjc.eventDefaultClass'] = 'MJ\Credits\Event\EventHandler';

		$container->factory('handler', function($identifier, array $params, Container $c)
		{
			$eventDefinitions = $c['mjc.eventDefinition'];
			$definition = $eventDefinitions[$identifier];

			if(!empty($eventDefinitions[$identifier])){
				$definition = $eventDefinitions[$identifier];
			}else{
				$definition = null;
			}

			if(empty($definition['definition_class'])){
				$class = $c['mjc.eventDefaultClass'];
			}else{
				$class = \XF::stringToClass($definition['definition_class'], '%s\Event\%s');

				$class = $this->extendClass($class, $c['mjc.eventDefaultClass']);

				if (!$class || !class_exists($class))
				{
					$class = $c['mjc.eventDefaultClass'];
				}
			}

			$class = $this->extendClass($class);

			return $c->createObject($class, [$this->app, $definition]);
		}, false);

		$container['mjc.eventDefinition'] = $this->fromRegistry(
			'mjcEventDefinition',
			function(Container $c) {
				return $this->parent['em']->getRepository('MJ\Credits:Event')->rebuildEventDefinitionCache();
			}
		);

		$container['mjc.eventCache'] = $this->fromRegistry(
			'mjcEvents',
			function (Container $c) {
				return $this->parent['em']->getRepository('MJ\Credits:Event')->rebuildEventCache();
			}
		);
	}

    /**
     * @param $identifier
     * @param array $options
     * @return array
     */
	public function getEvents($identifier, array $options = [])
	{
		$eventCache = $this->container['mjc.eventCache'];

		$validEvents = [];
		if(empty($eventCache[$identifier])){
			return $validEvents;
		}
		$events = $eventCache[$identifier];

		if(!empty($options['event_id'])){
			$eventId = $options['event_id'];
			if(isset($events[$eventId])){
				$validEvents = [$eventId => $events[$eventId]];
			}
			return $validEvents;
		}


		foreach($events as $eventId => $event){
			$valid = true;
			if(empty($event['currency_id']) ||
				(
					!empty($options['currency_id']) && (
						(
							is_array($options['currency_id']) && !in_array($event['currency_id'], $options['currency_id'])
						) || $event['currency_id'] != $options['currency_id']
					)
				)
			){
				continue;
			}
			if(!empty($options['filter_valid'])){
				$user = !empty($options['user']) ? $options['user'] : null;
				$valid = $this->app['em']->getRepository('MJ\Credits:Event')->validateEvent($event, $user);
			}

			if($valid){
				$validEvents[$eventId] = $event;
			}
		}

		return $validEvents;
	}

	/**
	 * @param $positionId
	 *
	 * @return array|\XF\Widget\AbstractWidget[]
	 */
	public function handler($definitionId, array $contextParams = [])
	{
		return $this->container->create('handler', $definitionId, $contextParams);
	}

	/**
	 * @param $identifier
	 * @param array $options
	 *
	 * @return null|\XF\Event\AbstractEvent
	 */
	public function getEvent($identifier, $eventId, array $options = [])
	{
		$eventCache = $this->container['eventCache'];

		$validEvents = [];
		$events = $eventCache[$identifier];
		if(isset($events[$eventId])){
			return $events[$eventId];
		}
		return false;
	}
}
