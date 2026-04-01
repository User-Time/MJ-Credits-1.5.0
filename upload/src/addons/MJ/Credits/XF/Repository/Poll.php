<?php

namespace MJ\Credits\XF\Repository;

class Poll extends XFCP_Poll
{
	public function voteOnPoll(\XF\Entity\Poll $poll, $votes, \XF\Entity\User $voter = NULL)
	{
		if($poll->content_type == 'thread' && $poll->getContent()){
			$GLOBALS['mjc.PollThread'] = $poll->getContent();
		}
		return parent::voteOnPoll($poll, $votes, $voter);
	}
}
