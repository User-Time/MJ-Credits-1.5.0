<?php

namespace MJ\Credits\XF\Entity;

class Report extends XFCP_Report
{
	protected function _postSave()
	{
		if($this->isInsert())
		{
			/** @var \MJ\Credits\Service\Event\Trigger $trigger */
			$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
			if($this->content_type == 'post'){
				$visitor = \XF::visitor();
				$extraData = [
					'content_user_id' => $this->content_user_id,
					'content_type'    => $this->content_type,
					'content_id'      => $this->content_id,
					'node_id'         => $this->Content->Thread->node_id,
				];
				$trigger->triggerEvent('post_report', $visitor->user_id, [
					'content_type' => $this->content_type,
					'content_id'   => $this->content_id,
					'node_id'      => $this->Content->Thread->node_id,
					'extra_data'   => $extraData
				]);
				$trigger->triggerEvent('post_report_receive', $this->content_user_id, [
					'content_type' => $this->content_type,
					'content_id'   => $this->content_id,
					'node_id'      => $this->Content->Thread->node_id,
					'extra_data'   => $extraData
				]);
			}
			$trigger->fire();
		}
		return parent::_postSave();
	}

	protected function _postDelete()
	{
		if($this->content_type == 'post'){
			/** @var \MJ\Credits\Service\Event\Trigger $trigger */
			/*$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
			$extraData = [
				'like_user_id' => $this->like_user_id,
				'content_user_id' => $this->content_user_id,
				'content_type' => $this->content_type,
				'content_id' => $this->content_id
			];
			$triggered = $trigger->triggerEvent('post_unreport', $this->like_user_id, [
				'content_type' => $this->content_type,
				'content_id' => $this->content_id,
				'extra_data' => $extraData
			]);

			$trigger->triggerEvent('post_like_lose', $this->content_user_id, [
				'content_type' => $this->content_type,
				'content_id' => $this->content_id,
				'extra_data' => $extraData
			]);
			$trigger->fire();*/
		}

		return parent::_postDelete();
	}
}
