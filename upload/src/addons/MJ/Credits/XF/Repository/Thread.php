<?php

namespace MJ\Credits\XF\Repository;

class Thread extends XFCP_Thread
{
	public function logThreadView(\XF\Entity\Thread $thread)
	{
		$userId = \XF::visitor()->user_id;
		if($thread->user_id && $userId && $thread->user_id != $userId){
			$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
			$trigger->triggerEvent('thread_view', $userId, [
				'maximum_apply' => [
					'max'          => 1,
					'with_content' => true,
				],
				'content_type' => 'thread',
				'content_id'   => $thread->thread_id,
				'node_id'      => $thread->node_id,
			]);
			$trigger->triggerEvent('thread_view_receive', $thread->user_id, [
				'maximum_apply' => [
					'max'          => 1,
					'with_content' => true,
				],
				'content_type' => 'thread',
				'content_id'   => $thread->thread_id,
				'node_id'      => $thread->node_id,
			]);
			$trigger->fire();
		}
		return parent::logThreadView($thread);
	}
}
