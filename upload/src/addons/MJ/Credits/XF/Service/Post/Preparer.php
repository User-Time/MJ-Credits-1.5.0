<?php

namespace MJ\Credits\XF\Service\Post;

class Preparer extends XFCP_Preparer
{
	protected function associateAttachments($hash)
	{
		$GLOBALS['mjc.Post'] = $this->post;
		parent::associateAttachments($hash);
	}
}
