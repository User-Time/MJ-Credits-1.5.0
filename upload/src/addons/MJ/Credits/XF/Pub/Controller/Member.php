<?php
namespace MJ\Credits\XF\Pub\Controller;

use MJ\Credits\Service\Event\Trigger;
use MJ\Credits\Util\Money;
use XF\Mvc\ParameterBag;

class Member extends XFCP_Member
{
	public function actionFine(ParameterBag $params)
	{
		$user = $this->assertViewableUser($params->user_id, [], true);

		$input = $this->filter([
			'username' => 'str',
			'fine_amount' => 'unum',
			'currency_id' => 'uint',
			'message' => 'str',
		]);

		$visitor = \XF::visitor();

		/** @var \MJ\Credits\ControllerPlugin\Credit $creditPlugin */
		$creditPlugin = $this->plugin('MJ\Credits:Credit');
		
		$currencies = $creditPlugin->useableCurrencies('fine');

		if (!$currencies) {
			return $this->noPermission();
		}

		if ($this->isPost())
		{
			if(!$visitor->hasPermission('mjcCredits', 'canFine'))
			{
				return $this->noPermission();
			}

			if ( !isset($currencies[$input['currency_id']]))
			{
				return $this->error(\XF::phrase('mjc_requested_currency_not_found'));
			}

			$currency = $currencies[$input['currency_id']];
			$currencyId = $currency['currency_id'];

			$fineAmount = $input['fine_amount'];
			$event = $currency['event'];

			if ($user->get($currency['column']) < $fineAmount)
			{
				return $this->error(\XF::phrase('mjc_not_enough_x_being_fined', [
					'x' => Money::formatAmount($fineAmount, $currencyId)
				]));
			}

			/** @var Trigger $trigger */
			$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);

			$trigger->triggerEvent('fine', $user->user_id, [
				'amount'     => -$fineAmount,
				'message'    => $input['message'],
				'currency'   => $currency,
				'event'      => $event,
				'extra_data' => [
					'type'            => 'fine',
					'send_user_id'    => $user->user_id,
					'receive_user_id' => $visitor->user_id,
				]
			]);

			$trigger->fire();

			return $this->redirect($this->buildLink('mjc-credits/transactions'));
		}
		else
		{
			$viewParams = [
				'user' => $user,
				'currencies' => $currencies,
				'pageSelected' => 'fine'
			];

			return $this->view('MJ\Credits:Members', 'mjc_member_fine', $viewParams);
		}
	}
}