<?php

namespace MJ\Credits\Finder;

use XF\Mvc\Entity\Finder;

class Transaction extends Finder
{
	public function applyGlobalVisibilityChecks()
	{
		$visitor = \XF::visitor();
		if (!$visitor->hasPermission('mjcCredits', 'viewHidden'))
		{
			$this->where('is_hidden', 0);
		}
		return $this;
	}

	public function forUser($user)
	{
		$this->where('user_id', $user->user_id);
		return $this;
	}

	public function byUser($user)
	{
		$this->where('trigger_user_id', $user->user_id);
		return $this;
	}

	public function forEventId($eventId)
	{
		$this->where('event_id', $eventId);
		return $this;
	}

	public function forDefinitionId($definitionId)
	{
		$this->where('definition_id', $definitionId);
		return $this;
	}

	public function byTransactionDate($operator, $cutOff)
	{
		$this->where('transaction_date', $operator, $cutOff);
		return $this;
	}

	public function pendingOnly()
	{
		$this->where('transaction_status', 'pending');
		return $this;
	}
}
