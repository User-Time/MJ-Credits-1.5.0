<?php

namespace MJ\Credits\XF\Entity;

class ProfilePost extends XFCP_ProfilePost
{
	protected function _postSave()
	{
		if($this->isInsert())
		{
			$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
			if($this->profile_user_id == $this->user_id){
				$trigger->triggerEvent('update_status', $this->user_id, [
					'content_type' => 'profile_post',
					'content_id'   => $this->profile_post_id,
				]);
			}else{
				$trigger->triggerEvent('profile_post_new', $this->user_id, [
					'target_user'  => $this->User,
					'content_type' => 'profile_post',
					'content_id'   => $this->profile_post_id,
				]);
				$trigger->triggerEvent('profile_post_receive', $this->profile_user_id, [
					'target_user' => $this->ProfileUser,
					'content_type' => 'profile_post',
					'content_id'   => $this->profile_post_id,
				]);
			}
			$trigger->fire();
		}
		return parent::_postSave();
	}

	protected function _postDelete()
	{
		$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
		$trigger->triggerEvent('profile_post_delete', $this->user_id, [
			'target_user' => $this->User,
			'content_type' => 'profile_post',
			'content_id'   => $this->profile_post_id,
		]);
		if($this->profile_user_id != $this->user_id){
			$trigger->triggerEvent('profile_post_lose', $this->profile_user_id, [
				'target_user' => $this->ProfileUser,
				'content_type' => 'profile_post',
				'content_id'   => $this->profile_post_id,
			]);
		}
		$trigger->fire();
		return parent::_postDelete();
	}
}
