<?php

namespace MJ\Credits\XF\Entity;

class ConversationMaster extends XFCP_ConversationMaster
{
	protected function _postSave()
	{
		if($this->isInsert())
		{
			$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
			$trigger->triggerEvent('conversation_new', $this->user_id, [
				'content_type' => 'conversation',
				'content_id'   => $this->conversation_id,
			]);
			$trigger->fire();
		}
		return parent::_postSave();
	}

	public function messageAdded(\XF\Entity\ConversationMessage $message)
	{
		$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
		if ($this->first_message_id)
		{
			$trigger->triggerEvent('conversation_reply_new', $message->user_id, [
				'content_type' => 'conversation_message',
				'content_id'   => $message->message_id,
			]);
			if($message->user_id !== $this->user_id){
				$trigger->triggerEvent('conversation_reply_receive', $this->user_id, [
					'content_type' => 'conversation_message',
					'content_id'   => $message->message_id,
				]);
			}
		}
		$trigger->fire();

		return parent::messageAdded($message);
	}
}
