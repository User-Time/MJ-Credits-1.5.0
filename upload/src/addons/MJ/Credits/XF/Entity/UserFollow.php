<?php

namespace MJ\Credits\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class UserFollow extends XFCP_UserFollow
{
	protected function _postSave()
	{
		/** @var \MJ\Credits\Service\Event\Trigger $trigger */
		$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
		$trigger->triggerEvent('follow', $this->user_id, [
			'extra_data' => [
				'follow_user_id' => $this->follow_user_id
			]
		]);
		$trigger->triggerEvent('follower_receive', $this->follow_user_id);
		$trigger->fire();
		parent::_postSave();
	}

	protected function _postDelete()
	{
		/** @var \MJ\Credits\Service\Event\Trigger $trigger */
		$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
		$trigger->triggerEvent('unfollow', $this->user_id, [
			'extra_data' => [
				'follow_user_id' => $this->follow_user_id
			]
		]);

		$trigger->triggerEvent('follower_lose', $this->follow_user_id);

		$trigger->fire();
		parent::_postDelete();
	}
}
