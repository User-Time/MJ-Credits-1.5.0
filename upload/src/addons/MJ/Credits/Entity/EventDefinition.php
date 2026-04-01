<?php

namespace MJ\Credits\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class EventDefinition extends Entity
{
	public function isActive()
	{
		$addOn = $this->AddOn;
		return $addOn ? $addOn->active : false;
	}

	public function getPhraseName($title)
	{
		return 'mjc_event_def_' . ($title ? 'title' : 'desc') . '.' . $this->definition_id;
	}

	public function getTitle()
	{
		return \XF::phrase($this->getPhraseName(true));
	}

	public function getDescription()
	{
		return \XF::phrase($this->getPhraseName(false));
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
			$phrase->addon_id = $this->_getDeferredValue(function () {
				return $this->addon_id;
			});
		}

		return $phrase;
	}

	protected function _preSave()
	{
		if (strpos($this->definition_class, ':') !== false)
		{
			$this->definition_class = \XF::stringToClass($this->definition_class, '%s\Event\%s');
		}
		if (!class_exists($this->definition_class))
		{
			$this->error(\XF::phrase('invalid_class_x', ['class' => $this->definition_class]), 'definition_class');
		}
	}

	protected function _postSave()
	{
		if ($this->isUpdate())
		{
			if ($this->isChanged('addon_id') || $this->isChanged('definition_id'))
			{
				$writeDevOutput = $this->getBehavior('XF:DevOutputWritable')->getOption('write_dev_output');

				/** @var Phrase $titlePhrase */
				$titlePhrase = $this->getExistingRelation('MasterTitle');
				if ($titlePhrase)
				{
					$titlePhrase->getBehavior('XF:DevOutputWritable')->setOption('write_dev_output', $writeDevOutput);

					$titlePhrase->addon_id = $this->addon_id;
					$titlePhrase->title = $this->getPhraseName(true);
					$titlePhrase->save();
				}

				/** @var Phrase $descriptionPhrase */
				$descriptionPhrase = $this->getExistingRelation('MasterDescription');
				if ($descriptionPhrase)
				{
					$descriptionPhrase->getBehavior('XF:DevOutputWritable')->setOption('write_dev_output', $writeDevOutput);

					$descriptionPhrase->addon_id = $this->addon_id;
					$descriptionPhrase->title = $this->getPhraseName(false);
					$descriptionPhrase->save();
				}
			}

			if ($this->isChanged('definition_id'))
			{
				$finder = $this->finder('MJ\Credits:Event')->where('definition_id', $this->getExistingValue('definition_id'));

				foreach ($finder->fetch() as $event)
				{
					$event->definition_id = $this->definition_id;
					$event->save();
				}
			}
		}

		$this->rebuildEventDefinitionCache();
	}

	protected function _postDelete()
	{
		$writeDevOutput = $this->getBehavior('XF:DevOutputWritable')->getOption('write_dev_output');

		$titlePhrase = $this->MasterTitle;
		if ($titlePhrase)
		{
			$titlePhrase->getBehavior('XF:DevOutputWritable')->setOption('write_dev_output', $writeDevOutput);

			$titlePhrase->delete();
		}
		$descriptionPhrase = $this->MasterDescription;
		if ($descriptionPhrase)
		{
			$descriptionPhrase->getBehavior('XF:DevOutputWritable')->setOption('write_dev_output', $writeDevOutput);

			$descriptionPhrase->delete();
		}

		if ($this->getOption('delete_events'))
		{
			$finder = $this->finder('MJ\Credits:Event')->where('definition_id', $this->definition_id);

			foreach ($finder->fetch() AS $event)
			{
				$event->delete();
			}
		}

		$this->rebuildEventDefinitionCache();
	}

	protected function rebuildEventDefinitionCache()
	{
		\XF::runOnce('mjcEventDefinitionCacheRebuild', function () {
			$this->getEventRepo()->rebuildEventDefinitionCache();
		});
	}

	protected function _setupDefaults()
	{
		/** @var \XF\Repository\AddOn $addOnRepo */
		$addOnRepo = $this->_em->getRepository('XF:AddOn');
		$this->addon_id = $addOnRepo->getDefaultAddOnId();
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mjc_event_definition';
		$structure->shortName = 'MJ\Credits:EventDefinition';
		$structure->primaryKey = 'definition_id';
		$structure->columns = [
			'definition_id'      => ['type' => self::STR, 'maxLength' => 50, 'match' => 'alphanumeric', 'required' => true],
			'definition_class'   => ['type' => self::STR, 'maxLength' => 150, 'default' => ''],
			'addon_id'           => ['type' => self::BINARY, 'maxLength' => 50, 'default' => ''],
			'display_order'      => ['type' => self::UINT, 'default' => 0],
		];
		$structure->behaviors = [
			'XF:DevOutputWritable' => []
		];
		$structure->getters = [
			'title'       => true,
			'description' => true,
		];
		$structure->relations = [
			'AddOn' => [
				'entity'     => 'XF:AddOn',
				'type'       => self::TO_ONE,
				'conditions' => 'addon_id',
				'primary'    => true
			],
			'MasterTitle' => [
				'entity'     => 'XF:Phrase',
				'type'       => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'mjc_event_def_title.', '$definition_id']
				]
			],
			'MasterDescription' => [
				'entity'     => 'XF:Phrase',
				'type'       => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'mjc_event_def_desc.', '$definition_id']
				]
			]
		];
		$structure->options = [
			'delete_events' => true
		];

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
