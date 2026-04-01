<?php

namespace MJ\Credits\DevelopmentOutput;

use XF\Mvc\Entity\Entity;
use XF\Util\Json;
use XF\DevelopmentOutput\AbstractHandler;

class EventDefinition extends AbstractHandler
{
	protected function getTypeDir()
	{
		return 'mjc_event_definitions';
	}

	public function export(Entity $eventDefinition)
	{
		if (!$this->isRelevant($eventDefinition))
		{
			return true;
		}

		$fileName = $this->getFileName($eventDefinition);

		$keys = [
			'definition_class',
			'display_order'
		];
		$json = $this->pullEntityKeys($eventDefinition, $keys);

		return $this->developmentOutput->writeFile(
			$this->getTypeDir(),
			$eventDefinition->addon_id,
			$fileName,
			Json::jsonEncodePretty($json)
		);
	}

	public function import($name, $addOnId, $contents, array $metadata, array $options = [])
	{
		$json = json_decode($contents, true);

		$eventDefinition = $this->getEntityForImport($name, $addOnId, $json, $options);

		$eventDefinition->bulkSet($json);
		$eventDefinition->definition_id = $name;
		$eventDefinition->addon_id = $addOnId;
		$eventDefinition->display_order = $displayOrder;
		$eventDefinition->save();
		// this will update the metadata itself

		return $eventDefinition;
	}
}
