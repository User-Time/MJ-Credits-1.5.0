<?php

namespace MJ\Credits\Cron;

/**
 * Cron entry for timed counter updates.
 */
class Birthday
{
	public static function trigger()
	{
		/** @var \MJ\Credits\Repository\Stats $statsRepo */
		$statsRepo = \XF::app()->repository('MJ\Credits:Stats');

		$birthdays = \XF::app()->finder('XF:User')
			->isBirthday()
			->isValidUser(true)
			->fetch();
		if(!$birthdays){
			return true;
		}
		$trigger = \XF::app()->service('MJ\Credits:Event\Trigger', false);
		foreach ($birthdays as $user)
		{
			$age = $user->Profile->getAge(true);
			if (!$age)
			{
				continue;
			}
			$trigger->triggerEvent('birthday', $user->user_id, [
				'target_user'     => $user,
				'trigger_user_id' => 0,
				'maximum_apply'   => [
					'yearly'      => 1
				]
			]);
		}
		$trigger->fire();
	}
}