<?php

namespace MJ\Credits\XF\Repository;

class ThreadWatch extends XFCP_ThreadWatch
{
	public function setWatchState(\XF\Entity\Thread $thread, \XF\Entity\User $user, $state)
	{
		if (!$thread->thread_id || !$user->user_id)
		{
			throw new \InvalidArgumentException("Invalid thread or user");
		}

		$watch = $this->em->find('XF:ThreadWatch', [
			'thread_id' => $thread->thread_id,
			'user_id' => $user->user_id
		]);

		$response = parent::setWatchState($thread, $user, $state);

		if($thread->user_id != $user->user_id)
		{
			$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);

			switch ($state)
			{
				case 'watch_email':
				case 'watch_no_email':
				case 'no_email':
					$trigger->triggerEvent('thread_watch', $user->user_id, [
						'content_type' => 'thread',
						'content_id'   => $thread->thread_id,
						'node_id'      => $thread->node_id,
					]);
					$trigger->triggerEvent('thread_watch_receive', $thread->user_id, [
						'content_type' => 'thread',
						'content_id'   => $thread->thread_id,
						'node_id'      => $thread->node_id,
					]);
					break;

				case 'delete':
				case 'stop':
				case '':
					if ($watch)
					{
						$trigger->triggerEvent('thread_unwatch', $user->user_id, [
							'content_type' => 'thread',
							'content_id'   => $thread->thread_id,
							'node_id'      => $thread->node_id,
						]);
						$trigger->triggerEvent('thread_watch_lose', $thread->user_id, [
							'content_type' => 'thread',
							'content_id'   => $thread->thread_id,
							'node_id'      => $thread->node_id,
						]);
					}
					break;
			}

			$trigger->fire();
		}
		return $response;
	}
}
