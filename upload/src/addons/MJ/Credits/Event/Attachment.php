<?php

namespace MJ\Credits\Event;

use XF\Mvc\Entity\Entity;

class Attachment extends EventHandler
{
	public function getDefaultEventOptions()
	{
		return parent::getDefaultEventOptions() + [
			'extensions' => 'rar zip txt pdf',
		];
	}

	public function getEditTemplateData(Entity $event, $position, &$templateName, &$params)
	{
		$templateName = 'mjc_event_edit_attachment';
	}

	public function _validateEvent(array $event, $params, &$error = null)
	{
		if(!empty($params['extension']) && !empty($event['options']['extensions'])){
			$extensions = preg_split('/\s+/', trim($event['options']['extensions']), -1, PREG_SPLIT_NO_EMPTY);
			if(!in_array($params['extension'], $extensions)){
				$error = \XF::phrase('mjc_this_event_cannot_trigger_in_this_forum');
				return false;
			}
		}
		return true;
	}
}
