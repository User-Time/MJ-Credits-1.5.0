<?php

namespace MJ\Credits\XF\Entity;

class Attachment extends XFCP_Attachment
{
	protected function _postDelete()
	{
		if($this->content_type == 'post' && !$this->unassociated){
			$trigger = $this->app()->service('MJ\Credits:Event\Trigger');
			$trigger->triggerEvent('attachment_delete', $this->Data->user_id, [
				'content_type' => 'post',
				'content_id'   => $this->content_id,
			]);
		}
		return parent::_postDelete();
	}
}
