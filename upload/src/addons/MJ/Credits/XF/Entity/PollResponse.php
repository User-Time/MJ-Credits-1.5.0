<?php

namespace MJ\Credits\XF\Entity;

class PollResponse extends XFCP_PollResponse
{
	public function updateForNewVote(\XF\Entity\User $user)
	{
		$voters = $this->voters;
		if(!empty($GLOBALS['mjc.PollThread']) && !isset($voters[$user->user_id]) ){

			$thread = $GLOBALS['mjc.PollThread'];
			if($user->user_id != $thread->user_id){
				$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
				$trigger->triggerEvent('poll_vote', $user->user_id, [
					'content_type' => 'thread',
					'content_id'   => $thread->thread_id,
				]);
				$trigger->triggerEvent('poll_vote_receive', $thread->user_id, [
					'trigger_user_id' => $user->user_id,
					'content_type' => 'thread',
					'content_id'   => $thread->thread_id,
				]);
				$trigger->fire();
			}

		}
		return parent::updateForNewVote($user);
	}
}
