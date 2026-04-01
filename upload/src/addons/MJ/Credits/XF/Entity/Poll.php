<?php

namespace MJ\Credits\XF\Entity;

class Poll extends XFCP_Poll
{
	protected function _postSave()
	{
		if($this->isInsert() && $this->content_type == 'thread')
		{
			$trigger = $this->app()->service('MJ\Credits:Event\Trigger');
			$content = $this->getContent();
			$trigger->triggerEvent('poll_create', $content->user_id, [
				'content_type' => $this->content_type,
				'content_id'   => $this->content_id,
			]);
		}
		return parent::_postSave();
	}

	protected function _postDelete()
	{
		return parent::_postDelete();
	}
}
