<?php

namespace MJ\Credits\XF\Service\Attachment;

class Preparer extends XFCP_Preparer
{
	public function associateAttachmentsWithContent($tempHash, $contentType, $contentId)
	{
		$associated = parent::associateAttachmentsWithContent($tempHash, $contentType, $contentId);
		if($associated && $contentType == 'post' && !empty($GLOBALS['mjc.Post'])){
			/** @var \MJ\Credits\Service\Event\Trigger $trigger */
			$trigger = $this->app->service('MJ\Credits:Event\Trigger');
			$post = $GLOBALS['mjc.Post'];
			$trigger->triggerEvent('attachment_upload', $post->user_id, [
				'multiple'     => $associated,
				'content_id'   => $post->post_id,
				'content_type' => 'post',
				'extra_data'   => [
					'attachment_count' => $associated
				]
			]);
		}
		return $associated;
	}
}
