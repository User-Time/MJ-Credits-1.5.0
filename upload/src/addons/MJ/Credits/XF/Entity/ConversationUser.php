<?php

namespace MJ\Credits\XF\Entity;

class ConversationUser extends XFCP_ConversationUser
{
	protected function _postSave()
	{
		if($this->isInsert())
		{
			$trigger = $this->app()->service('MJ\Credits:Event\Trigger');
			if($this->Master->user_id != $this->owner_user_id){
				$trigger->triggerEvent('conversation_receive', $this->owner_user_id, [
					'content_type' => 'conversation',
					'content_id'   => $this->conversation_id,
				]);
			}
		}
		return parent::_postSave();
	}

	protected function _postDelete()
	{
		$trigger = $this->app()->service('MJ\Credits:Event\Trigger');
		$trigger->triggerEvent('conversation_leave', $this->owner_user_id, [
			'content_type' => 'conversation',
			'content_id'   => $this->conversation_id,
		]);
		return parent::_postDelete();
	}
}
