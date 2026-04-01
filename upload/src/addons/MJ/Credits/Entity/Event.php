<?php

namespace MJ\Credits\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Event extends Entity
{
	public function canView(&$error = null)
	{
		$visitor = \XF::visitor();

		if (!$this->EventDefinition)
		{
			return false;
		}

		if (!$visitor->canViewCredits($error))
		{
			return false;
		}

		if (!$visitor->user_id)
		{
			$error = \XF::phraseDeferred('mjc_requested_event_not_found');
			return false;
		}

		return true;
	}

	public function isActive()
	{
		$eventDefinition = $this->WidgetDefinition;
		return $eventDefinition ? $eventDefinition->isActive() : false;
	}

	public function getTitle()
	{
		$definition = $this->EventDefinition;
		$eventPhrase = \XF::phrase('mjc_event.' . $this->event_id);
		return $eventPhrase->render('html', ['nameOnInvalid' => false]) ?: ($definition ? $definition->title : $this->definition_id);
	}

	public function getPhraseName($title, $fallback = false)
	{
		$phraseName = 'mjc_event' . ($title ? '' : '_desc') . '.' . $this->event_id;

		$eventPhrase = \XF::phrase($phraseName);
		if(!$fallback || $eventPhrase->render('html', ['nameOnInvalid' => false])){
			return $phraseName;
		}else{
			$definition = $this->EventDefinition;
			return $definition ? $definition->getPhraseName($title) : $this->definition_id;
		}
	}

	public function getMasterPhrase($title)
	{
		$phrase = $title ? $this->MasterTitle : $this->MasterDescription;
		if (!$phrase)
		{
			$phrase = $this->_em->create('XF:Phrase');
			$phrase->title = $this->_getDeferredValue(function () use ($title) {
				return $this->getPhraseName($title);
			});
			$phrase->language_id = 0;
			$phrase->addon_id = '';
		}

		return $phrase;
	}

	public function getDescription()
	{
		$definition = $this->EventDefinition;
		$eventPhrase = \XF::phrase('mjc_event_desc.' . $this->event_id);
		return $eventPhrase->render('html', ['nameOnInvalid' => false]) ?: ($definition ? $definition->description : $this->definition_id);
	}

	protected $eventHandler = null;

    /**
     * @return mixed|null
     * @throws \Exception
     */
	public function getHandler()
	{
		if(!empty($this->eventHandler)){
			return $this->eventHandler;
		}
		$eventDefinition = $this->EventDefinition;
		if (!$eventDefinition)
		{
			return null;
		}
		$class = \XF::stringToClass($eventDefinition->definition_class, '%s\Event\%s');
		if (!class_exists($class))
		{
			$class = $this->app()->container('mjc.eventDefaultClass');
		}
		$class = \XF::extendClass($class);
		$this->eventHandler = new $class($this->app(), $eventDefinition->toArray());
		return $this->eventHandler;
	}

	public function renderEdit($position)
	{
		return $this->handler ? $this->handler->renderEdit($this, $position) : '';
	}

	protected function _preSave()
	{
	}

	protected function _postSave()
	{
		if ($this->getOption('rebuild_cache'))
		{
			$this->rebuildEventCache();
		}
	}

	protected function _postDelete()
	{
		if ($this->MasterTitle)
		{
			$this->MasterTitle->delete();
		}
		if ($this->MasterDescription)
		{
			$this->MasterDescription->delete();
		}
		if ($this->getOption('rebuild_cache'))
		{
			$this->rebuildEventCache();
		}
	}

	protected function rebuildEventCache()
	{
		\XF::runOnce('mjcEventCacheRebuild', function () {
			$this->getEventRepo()->rebuildEventCache();
		});
	}

	protected function _setupDefaults()
	{
		$this->active = true;
		$this->send_alert = true;
		$this->moderate_transactions = false;
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mjc_event';
		$structure->shortName = 'MJ\Credits:Event';
		$structure->contentType = 'mjc_event';
		$structure->primaryKey = 'event_id';
		$structure->columns = [
			'event_id'               => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'definition_id'          => ['type' => self::STR, 'maxLength' => 50, 'match' => 'alphanumeric', 'required' => true],
			'currency_id'            => ['type' => self::UINT, 'required' => true],

			'amount'                 => ['type' => self::FLOAT, 'default' => 0],
			'moderate_transactions'  => ['type' => self::BOOL, 'default' => false],
			'send_alert'             => ['type' => self::BOOL, 'default' => true],
			'options'                => ['type' => self::JSON, 'default' => ''],
			'active'                 => ['type' => self::BOOL, 'default' => true],
			'allowed_user_group_ids' => ['type' => self::LIST_COMMA, 'default' => ''],
		];
		$structure->behaviors = [];
		$structure->getters = [
			'title' => true,
			'description' => true,
			'handler' => true
		];
		$structure->relations = [
			'MasterTitle' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'mjc_event.', '$event_id']
				]
			],
			'MasterDescription' => [
				'entity'     => 'XF:Phrase',
				'type'       => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'mjc_event_desc.', '$event_id']
				]
			],
			'EventDefinition' => [
				'entity'     => 'MJ\Credits:EventDefinition',
				'type'       => self::TO_ONE,
				'conditions' => 'definition_id',
				'primary'    => true
			],
			'Currency' => [
				'entity'     => 'MJ\Credits:Currency',
				'type'       => self::TO_ONE,
				'conditions' => 'currency_id',
				'primary'    => true
			]
		];

		$structure->options = [
			'rebuild_cache' => true
		];

		$structure->defaultWith[] = 'EventDefinition';

		return $structure;
	}

	/**
	 * @return \MJ\Credits\Repository\Event
	 */
	protected function getEventRepo()
	{
		return $this->repository('MJ\Credits:Event');
	}
}
