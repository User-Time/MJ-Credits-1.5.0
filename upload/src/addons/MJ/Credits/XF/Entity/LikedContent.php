<?php

namespace MJ\Credits\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class LikedContent extends XFCP_LikedContent
{
	protected function _postSave()
	{
		if($this->isInsert())
		{
			/** @var \MJ\Credits\Service\Event\Trigger $trigger */
			$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
			if($this->content_type == 'post'){
				$extraData = [
					'like_user_id'    => $this->like_user_id,
					'content_user_id' => $this->content_user_id,
					'content_type'    => $this->content_type,
					'content_id'      => $this->content_id,
					'node_id'         => $this->Content->Thread->node_id,
				];
				$trigger->triggerEvent('post_like', $this->like_user_id, [
					'content_type' => $this->content_type,
					'content_id'   => $this->content_id,
					'node_id'      => $this->Content->Thread->node_id,
					'extra_data'   => $extraData
				]);
				$trigger->triggerEvent('post_like_receive', $this->content_user_id, [
					'content_type' => $this->content_type,
					'content_id'   => $this->content_id,
					'node_id'      => $this->Content->Thread->node_id,
					'extra_data'   => $extraData
				]);
			} else if ($this->content_type == 'profile_post'){
				$extraData = [
					'like_user_id'    => $this->like_user_id,
					'content_user_id' => $this->content_user_id,
					'content_type'    => $this->content_type,
					'content_id'      => $this->content_id,
				];
				$trigger->triggerEvent('profile_post_like', $this->like_user_id, [
					'content_type' => $this->content_type,
					'content_id'   => $this->content_id,
					'extra_data'   => $extraData
				]);
				$trigger->triggerEvent('profile_post_like_receive', $this->content_user_id, [
					'content_type' => $this->content_type,
					'content_id'   => $this->content_id,
					'extra_data'   => $extraData
				]);
			}
			$trigger->fire();
		}
		return parent::_postSave();
	}

	protected function _postDelete()
	{
		/** @var \MJ\Credits\Service\Event\Trigger $trigger */
		$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
		if($this->content_type == 'post'){
			$extraData = [
				'like_user_id'    => $this->like_user_id,
				'content_user_id' => $this->content_user_id,
				'content_type'    => $this->content_type,
				'content_id'      => $this->content_id,
				'node_id'         => $this->Content->Thread->node_id,
			];
			$trigger->triggerEvent('post_unlike', $this->like_user_id, [
				'content_type' => $this->content_type,
				'content_id'   => $this->content_id,
				'node_id'      => $this->Content->Thread->node_id,
				'extra_data'   => $extraData
			]);

			$trigger->triggerEvent('post_like_lose', $this->content_user_id, [
				'content_type' => $this->content_type,
				'content_id'   => $this->content_id,
				'node_id'      => $this->Content->Thread->node_id,
				'extra_data'   => $extraData
			]);
		}
		else if($this->content_type == 'profile_post')
		{
			$extraData = [
				'like_user_id'    => $this->like_user_id,
				'content_user_id' => $this->content_user_id,
				'content_type'    => $this->content_type,
				'content_id'      => $this->content_id
			];
			$trigger->triggerEvent('profile_post_unlike', $this->like_user_id, [
				'content_type' => $this->content_type,
				'content_id' => $this->content_id,
				'extra_data' => $extraData
			]);

			$trigger->triggerEvent('profile_post_like_lose', $this->content_user_id, [
				'content_type' => $this->content_type,
				'content_id' => $this->content_id,
				'extra_data' => $extraData
			]);
		}
		$trigger->fire();
		return parent::_postDelete();
	}
}
