<?php

namespace MJ\Credits\XF\Entity;

class ConversationMessage extends XFCP_ConversationMessage
{
    public function getQuoteWrapper($inner)
    {
        return parent::getQuoteWrapper(preg_replace(
            '#\[' . preg_quote(\XF::options()->mjc_credits_event_trigger_content_bbcode, '#') . '=(\d+|\d+[.,](\d+))\](.*)\[\/' . preg_quote(\XF::options()->mjc_credits_event_trigger_content_bbcode, '#') . '\]#i',
            \XF::phrase('mjc_credits_stripped_content'),
            $inner
        ));
    }
	protected function _postDelete()
	{
		/*$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
		$trigger->triggerEvent('conversation_reply_lose', $this->Conversation->user_id, [
			'content_type' => 'conversation_message',
			'content_id'   => $this->message_id,
		]);
		$trigger->triggerEvent('conversation_reply_delete', $this->user_id, [
			'content_type' => 'conversation_message',
			'content_id'   => $this->message_id,
		]);

		$trigger->fire();*/
		return parent::_postDelete();
	}
}