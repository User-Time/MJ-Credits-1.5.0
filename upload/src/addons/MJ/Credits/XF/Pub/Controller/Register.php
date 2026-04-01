<?php

namespace MJ\Credits\XF\Pub\Controller;

use XF\Entity\User;

class Register extends XFCP_Register
{
	protected function finalizeRegistration(User $user)
	{
		$reply = parent::finalizeRegistration($user);

		if(\XF::visitor()->user_id){
			$trigger = $this->app()->service('MJ\Credits:Event\Trigger');
            $trigger->triggerEvent('register', \XF::visitor()->user_id, [
				'maximum_apply' => ['max' => 1],
				'bypass_permission' => true
			]);
		}

		return $reply;
	}
}
