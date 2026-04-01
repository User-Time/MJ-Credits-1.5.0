<?php

namespace MJ\Credits\XF\Repository;

class Attachment extends XFCP_Attachment
{
	public function logAttachmentView(\XF\Entity\Attachment $attachment)
	{
		$visitor = \XF::visitor();
		if($attachment->content_type == 'post' && $visitor->user_id != $attachment->Data->user_id){
			$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);

			$trigger->triggerEvent('attachment_download', $visitor->user_id, [
				/*'maximum_apply'    => [
					'max'          => 1,
					'with_content' => true,
				],*/
				'content_type'     => 'attachment',
				'content_id'       => $attachment->attachment_id,
				'extension'        => $attachment->extension,
			]);
			$trigger->triggerEvent('attachment_download_receive', $attachment->Data->user_id, [
				/*'maximum_apply'    => [
					'max'          => 1,
					'with_content' => true,
				],*/
				'content_type'     => 'attachment',
				'content_id'       => $attachment->attachment_id,
				'extension'        => $attachment->extension,
			]);
			$trigger->fire();
		}
		return parent::logAttachmentView($attachment);
	}
}
