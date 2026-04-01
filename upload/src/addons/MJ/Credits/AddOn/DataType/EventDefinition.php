<?php

namespace MJ\Credits\AddOn\DataType;

use MJ\Credits\Repository\Event;
use XF\AddOn\DataType\AbstractDataType;

class EventDefinition extends AbstractDataType
{
	public function getShortName()
	{
		return 'MJ\Credits:EventDefinition';
	}

	public function getContainerTag()
	{
		return 'mjc_event_definitions';
	}

	public function getChildTag()
	{
		return 'event_definition';
	}

	public function exportAddOnData($addOnId, \DOMElement $container)
	{
		$entries = $this->finder()
			->where('addon_id', $addOnId)
			->order('definition_id')->fetch();

		$doc = $container->ownerDocument;

		foreach ($entries as $entry)
		{
			$node = $doc->createElement($this->getChildTag());

			$this->exportMappedAttributes($node, $entry);

			$container->appendChild($node);
		}

		return $entries->count() ? true : false;
	}

	public function importAddOnData($addOnId, \SimpleXMLElement $container, $start = 0, $maxRunTime = 0)
	{
		$startTime = microtime(true);

		$entries = $this->getEntries($container, $start);
		if (!$entries)
		{
			return false;
		}

		$ids = $this->pluckXmlAttribute($entries, 'definition_id');
		$existing = $this->findByIds($ids);

		$i = 0;
		$last = 0;
		foreach ($entries as $entry)
		{
			$id = $ids[$i++];

			if ($i <= $start)
			{
				continue;
			}

			/** @var \MJ\Credits\Entity\EventDefinition $entity */
			$entity = isset($existing[$id]) ? $existing[$id] : $this->create();

			$entity->getBehavior('XF:DevOutputWritable')->setOption('write_dev_output', false);
			$this->importMappedAttributes($entry, $entity);

			$entity->addon_id = $addOnId;

			$entity->save(true, false);

			if ($this->resume($maxRunTime, $startTime))
			{
				$last = $i;
				break;
			}
		}
		return ($last ?: false);
	}

	public function deleteOrphanedAddOnData($addOnId, \SimpleXMLElement $container)
	{
		$this->deleteOrphanedSimple($addOnId, $container, 'definition_id');
	}

	public function deleteAddOnData($addOnId, $maxRunTime = 0)
	{
		if($addOnId == 'MJ/Credits'){
			return true;
		}
		return parent::deleteAddOnData($addOnId, $maxRunTime);
	}

	public function rebuildActiveChange(\XF\Entity\AddOn $addOn, array &$jobList)
	{
		\XF::runOnce('rebuild_active_' . $this->getContainerTag(), function () {
			/** @var Event $repo */
			$repo = $this->em->getRepository('MJ\Credits:Event');
			$repo->rebuildEventDefinitionCache();
		});
	}

	protected function getMappedAttributes()
	{
		return [
			'definition_id',
			'definition_class',
			'display_order'
		];
	}
}
