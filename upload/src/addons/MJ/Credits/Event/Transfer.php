<?php

namespace MJ\Credits\Event;

use XF\Mvc\Entity\Entity;

class Transfer extends EventHandler
{
	public function transactionDescription($transaction)
	{
		if($transaction->amount < 0){
			$extraData = $transaction->extra_data;
			if(isset($extraData['type'], $extraData['send_user_id'], $extraData['receive_user_id']))
			{
				if($extraData['type'] == 'send'){
					$receiver = $this->em()->find('XF:User', $extraData['receive_user_id']);
					return \XF::phrase('mjc_you_sent_x_to_y', [
						'amount' => \MJ\Credits\Util\Money::formatAmount($transaction->amount, $transaction->currency_id, false),
						'name' => $receiver->username
					]);
				}else{
					$sender = $this->em()->find('XF:User', $extraData['send_user_id']);
					return \XF::phrase('mjc_x_sent_you_y', [
						'amount' => \MJ\Credits\Util\Money::formatAmount($transaction->amount, $transaction->currency_id, false),
						'name' => $sender->username
					]);
				}
			}
		}
	}

	public function getAlertTemplate()
	{
		return 'public:mjc_alert_event_transfer';
	}

	public function getAlertData(Entity $event, &$data)
	{
		if(isset($data['extra']['type'], $data['extra']['send_user_id'], $data['extra']['receive_user_id'])){
			if($data['extra']['type'] == 'send'){
				$data['receiver'] = $this->em()->find('XF:User', $data['extra']['receive_user_id']);
			}else{
				$data['sender'] = $this->em()->find('XF:User', $data['extra']['send_user_id']);
			}
		}
	}
}
