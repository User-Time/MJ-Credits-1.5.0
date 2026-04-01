<?php

namespace MJ\Credits\Pub\Controller;

use MJ\Credits\Service\Event\Trigger;
use MJ\Credits\Util\Money;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
use XF\Mvc\Reply\View;

class Credit extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		/** @var \XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewCredits($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}

	public function actionIndex(ParameterBag $params)
	{
		$transactionRepo = $this->getTransactionRepo();

		$visitor = \XF::visitor();
		$transactionList = $transactionRepo->findTransactionsForUser($visitor);

		$transactionList->limit(5);

		/** @var \MJ\Credits\Entity\Transaction[] $transactions */
		$transactions = $transactionList->fetch();
		$totalTransactions = $transactionList->total();

		$viewParams = [
			'transactions' => $transactions,
			'pageSelected' => 'overview',
		];

		return $this->view('MJ\Credits:Overview', 'mjc_overview', $viewParams);
	}

    public function actionClock()
    {
        $this->assertPostOnly();
        $currencyIds = $this->filter('currency_ids', 'array-uint');

        $visitor = \XF::visitor();

        if (!$visitor->user_id) {
            return $this->error(\XF::phrase('requested_user_not_found'));
        }

        $redis = new \Redis();
        $redis->connect('127.0.0.1');

        $ip = $this->request()->getIp();
        $key = 'clock_limit:' . md5($ip . $visitor->user_id);

        $count = $redis->incr($key);
        if ($count == 1) {
            $redis->expire($key, 2);
        } elseif ($count > 1) {
            return $this->redirect($this->buildLink('index'));
        }

        /** @var \MJ\Credits\ControllerPlugin\Credit $creditPlugin */
        $creditPlugin = $this->plugin('MJ\Credits:Credit');
        $currencies = $creditPlugin->useableCurrencies('daily_clock');

        if (!$currencies) {
            return $this->noPermission();
        }

        date_default_timezone_set('Asia/Hong_Kong');
        $dt = new \DateTime('today', new \DateTimeZone(\XF::options()->guestTimeZone));

        $today = $dt->getTimestamp();

        $total = $this->finder('MJ\Credits:Transaction')
            ->where('transaction_status', '=', 'completed')
            ->where('transaction_date', 'BETWEEN', [$today, \XF::$time])
            ->where('user_id', '=', $visitor->user_id)
            ->where('currency_id', $currencyIds)
            ->where('definition_id', '=', 'daily_clock')
            ->fetchOne();

        if ($total) {
            return $this->error(\XF::phrase('mjc_already_signed_in'));
        }

        /** @var Trigger $trigger */
        $trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);

        // $currency = $currencies[$currencyId];
        // $event = $currency['event'];

        $trigger->triggerEvent('daily_clock', $visitor->user_id, [
            //'amount' => $event['amount'],
            // 'event' => $event,
            'extra_data' => [
                'type' => 'daily_clock',
                'trigger_user_id' => $visitor->user_id
            ],
            //'maximum_apply' => ['daily' => 1]
        ]);

        $trigger->fire();
        //  }

        return $this->redirect($this->buildLink('index'));
    }


    public function actionWallet(ParameterBag $params)
	{
		$currencies = $this->app->container('mjc.currencies');
		$currencies = $this->repository('MJ\Credits:Currency')->prepareCurrencies($currencies, true);

		/** @var \MJ\Credits\ControllerPlugin\Credit $creditPlugin */
		$creditPlugin = $this->plugin('MJ\Credits:Credit');

		$canTransfer = $creditPlugin->useableCurrencies('transfer', $currencies);
		$canWithdraw = $creditPlugin->useableCurrencies('withdraw', $currencies);
		$exchangeCurrencies = $creditPlugin->useableCurrencies('exchange', $currencies);

		$viewParams = [
			'currencies'  => $currencies,
			'canTransfer' => $canTransfer,
			'canWithdraw' => $canWithdraw,
			'canExchange' => ($exchangeCurrencies && count($exchangeCurrencies) >= 2),
		];
		return $this->view('MJ\Credits:WalletPopup', 'mjc_wallet_popup', $viewParams);
	}

	public function actionSendCredit()
    {
        /** @var \MJ\Credits\ControllerPlugin\Credit $creditPlugin */
        $creditPlugin = $this->plugin('MJ\Credits:Credit');

        $currencies = $creditPlugin->useableCurrencies('transfer');
        if (!$currencies) {
            return $this->noPermission();
        }

        $canAnonymousTransfer = false;

        $input = $this->filter([
            'username' => 'str',
            'amount' => 'unum',
            'currency_id' => 'uint',
            'message' => 'str',
            'fee_type' => 'str',
        ]);

        if (!$input['currency_id']) {
            if (count($currencies) > 1) {
                /*$viewParams = [
                    'currencies'           => $currencies,
                    'username'             => $input['username'],
                    'amount'               => $input['amount'],
                    'message'              => $input['message'],
                    'canAnonymousTransfer' => $canAnonymousTransfer,
                ];
                return $this->view('MJ\Credits:WalletPopup', 'mjc_send_credit_choosers', $viewParams);*/
            } else {
                $currency = reset($currencies);
                $input['currency_id'] = $currency['currency_id'];
            }
        }

        if ($this->isPost()) {
            if (!isset($currencies[$input['currency_id']])) {
                return $this->error(\XF::phrase('mjc_requested_currency_not_found'));
            }

            $currency = $currencies[$input['currency_id']];

            $currencyId = $currency['currency_id'];
            $username = $input['username'];
            $userNotFound = true;

            $visitor = \XF::visitor();

            if ($username) {
	            $user = $this->em()->findOne('XF:User', ['username' => $username]);
                if ($user && $user->user_id == $visitor->user_id) {
                    return $this->error(\XF::phrase('mjc_you_may_not_send_credit_yourself'));
                }
                if ($user) {
                    $userNotFound = false;
                }
            }

            if ($userNotFound) {
                return $this->error(\XF::phrase('requested_user_not_found'));
            }

            if (!$input['amount'] || $input['amount'] <= 0) {
                return $this->error(\XF::phrase('mjc_please_enter_valid_amount'));
            }

            /** @var Trigger $trigger */
            $trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);

            $amount = $input['amount'];
            $event = $currency['event'];

            list($sendAmount, $receiveAmount) = $this->repository('MJ\Credits:Event')->calculateAmountWithFee(
                $amount,
                $input['fee_type'],
                $event
            );

            if ($visitor->get($currency['column']) < $sendAmount) {
                return $this->error(\XF::phrase('mjc_not_enough_x_to_transfer', [
                    'amount' => Money::formatAmount($sendAmount, $currencyId)
                ]));
            }
	        $user = $this->em()->findOne('XF:User', ['username' => $username]);
            $trigger->triggerEvent('transfer', $visitor->user_id, [
                'amount' => -$sendAmount,
                'message' => $input['message'],
                'currency' => $currency,
                'event' => $event,
                'extra_data' => [
                    'type' => 'send',
                    'send_user_id' => $visitor->user_id,
                    'receive_user_id' => $user->user_id,
                ]
            ]);

            $currencies = $creditPlugin->useableCurrencies('transfer');
            if ($receiveAmount > 0) {
                $trigger->triggerEvent('transfer', $user->user_id, [
                    'amount' => $receiveAmount,
                    'message' => $input['message'],
                    'currency' => $currency,
                    'extra_data' => [
                        'type' => 'receive',
                        'send_user_id' => $visitor->user_id,
                        'receive_user_id' => $user->user_id,
                    ]
                ]);

                $trigger->fire();
            } else {
                return $this->error(\XF::phrase('cnxfans_the_transfer_amount_is_not_enough'));
            }
            return $this->redirect($this->buildLink('mjc-credits/send-credit'));
        } else {
            $viewParams = [
                'currencies' => $currencies,
                'username' => $input['username'],
                'amount' => $input['amount'],
                'currencyId' => $input['currency_id'],
                'message' => $input['message'],
                'canAnonymousTransfer' => $canAnonymousTransfer,
                'pageSelected' => 'transfer',
            ];

            return $this->view('MJ\Credits:SendCredit', 'mjc_send_credit', $viewParams);
        }
    }

	public function getSendCreditProcess(ParameterBag $params)
	{
	}

	public function actionFine()
	{
		$visitor = \XF::visitor();

		$input = $this->filter([
			'username' => 'str',
			'fine_amount' => 'unum',
			'currency_id' => 'uint',
			'message' => 'str',
			]);

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

			$userNotFound = true;

			if ($input['username'])
			{
				$user = $this->em()->findOne('XF:User', ['username' => $input['username']]);
				if ($user && $user->user_id == $visitor->user_id)
				{
					return $this->error(\XF::phrase('mjc_you_may_not_fine_yourself'));
				}
				if ($user)
				{
					$userNotFound = false;
				}

				if ($userNotFound)
				{
					return $this->error(\XF::phrase('requested_user_not_found'));
				}

				if ( !isset($currencies[$input['currency_id']]))
				{
					return $this->error(\XF::phrase('mjc_requested_currency_not_found'));
				}

				$currency   = $currencies[$input['currency_id']];
				$currencyId = $currency['currency_id'];

				$fineAmount = $input['fine_amount'];
				$event      = $currency['event'];

				if ($user->get($currency['column']) < $fineAmount) {
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
				], $error);

				$trigger->fire();
			}
			return $this->redirect($this->buildLink('mjc-credits/transactions'));
		}
		else
		{
			$viewParams = [
				'currencies' => $currencies,
				'pageSelected' => 'fine'
			];

			return $this->view('MJ\Credits:Members', 'mjc_fine', $viewParams);
		}
	}

	public function actionSendCreditAmount()
	{
		/** @var \MJ\Credits\ControllerPlugin\Credit $creditPlugin */
		$creditPlugin = $this->plugin('MJ\Credits:Credit');

		$currencies = $creditPlugin->useableCurrencies('transfer');
		if(!$currencies){
			return $this->noPermission();
		}

		$input = $this->filter([
			'amount'      => 'unum',
			'currency_id' => 'uint',
			'fee_type'    => 'str',
		]);

		if(!isset($currencies[$input['currency_id']])){
			return $this->error(\XF::phrase('mjc_requested_currency_not_found'));
		}

		$currency = $currencies[$input['currency_id']];

		if(!$input['amount'] || $input['amount'] <= 0){
			return $this->error(\XF::phrase('mjc_please_enter_valid_amount'));
		}

		/** @var Trigger $trigger */
		$this->app()->service('MJ\Credits:Event\Trigger', false);

		$amount = $input['amount'];
		$event = $currency['event'];

		list($sendAmount, $receiveAmount) = $this->repository('MJ\Credits:Event')->calculateAmountWithFee(
			$amount,
			$input['fee_type'],
			$event
		);

		$viewParams = [
			'sendAmount'    => $sendAmount,
			'receiveAmount' => $receiveAmount,
			'currency'      => $currency,
		];
		return $this->view('MJ\Credits:SendCreditAmount', 'mjc_send_credit_amount', $viewParams);
	}

	public function actionExchange(ParameterBag $params)
	{
		/** @var \MJ\Credits\ControllerPlugin\Credit $creditPlugin */
		$creditPlugin = $this->plugin('MJ\Credits:Credit');

		$currencies = $creditPlugin->useableCurrencies('exchange');

		if(!$currencies || count($currencies) < 2){
			return $this->noPermission();
		}

		if ($this->isPost())
		{
			$input = $this->filter([
				'amount' => 'unum',
				'from'   => 'uint',
				'to'     => 'uint',
			]);

			if(!isset($currencies[$input['from']]) || !isset($currencies[$input['to']])){
				return $this->error(\XF::phrase('mjc_requested_currency_not_found'));
			}

			$fromCurrency = $currencies[$input['from']];
			$toCurrency = $currencies[$input['to']];

			if(!$input['amount'] || $input['amount'] <= 0){
				return $this->error(\XF::phrase('mjc_please_enter_valid_amount'));
			}
			if($input['from'] == $input['to']){
				return $this->error(\XF::phrase('mjc_you_cannot_exchange_same_currency'));
			}

			$sendAmount = $input['amount'];
			$receiveAmount = ($input['amount'] / $fromCurrency['exchange_rate']) * $toCurrency['exchange_rate'];

			$fee = $this->repository('MJ\Credits:Event')->calculateFee($receiveAmount, $toCurrency['event']);
			$receiveAmount -= $fee;

			/** @var Trigger $trigger */
			$trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);

			$visitor = \XF::visitor();

			if($visitor->get($fromCurrency['column']) < $sendAmount){
				return $this->error(\XF::phrase('mjc_not_enough_x_to_exchange', [
					'amount' => Money::formatAmount($sendAmount, $fromCurrency['currency_id'])
				]));
			}

			$trigger->triggerEvent('exchange', $visitor->user_id, [
				'amount'   => -$sendAmount,
				'currency' => $fromCurrency,
				'extra_data' => [
					'from' => $fromCurrency['currency_id'],
					'to' => $toCurrency['currency_id'],
				]
			], $error);


			$trigger->triggerEvent('exchange', $visitor->user_id, [
				'amount'   => $receiveAmount,
				'currency' => $toCurrency,
				'extra_data' => [
					'from' => $fromCurrency['currency_id'],
					'to' => $toCurrency['currency_id'],
				]
			], $error);


			$trigger->fire();

			return $this->redirect($this->buildLink('mjc-credits/exchange'));
		}else{
			$viewParams = [
				'currencies' => $currencies,
				'pageSelected' => 'exchange',
			];
			return $this->view('MJ\Credits:Exchange', 'mjc_exchange', $viewParams);
		}
	}

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|View
     * @throws \XF\PrintableException
     */
    public function actionCurrencyBuyContent(ParameterBag $params)
    {
        /** @var \MJ\Credits\ControllerPlugin\Credit $creditPlugin */
        $creditPlugin = $this->plugin('MJ\Credits:Credit');

        $currencies = $creditPlugin->useableCurrencies('content');
        if(!$currencies){
            return $this->noPermission();
        }

        $input = $this->filter([
            'content_type' => 'str',
            'content_id' => 'uint',
            'content_hash' => 'str',
        ]);


        $visitor = \XF::visitor();

        /** @var Trigger $trigger */
        $trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);

        /** @var \MJ\Credits\Entity\Charge $charge */
        $charge = $this->finder('MJ\Credits:Charge')
            ->where('content_type', $input['content_type'])
            ->where('content_id', $input['content_id'])
            ->where('content_hash', $input['content_hash'])
            ->fetchOne();

        if (!$charge)
        {
            return $this->error(\XF::phrase('mjc_credits_invalid_hash'));
        }

        if ($charge->Purchases->offsetExists($visitor->user_id))
        {
            return $this->error(\XF::phrase('mjc_credits_already_owned'));
        }

        $currency = $currencies[$charge->Currency->currency_id];
        $currencyId = $currency['currency_id'];

        if ($this->isPost())
        {
            $buyAmount = $charge->cost;

            if($visitor->get($currency['column']) < $buyAmount){
                return $this->error(\XF::phrase('mjc_not_enough_x_to_buy_content', [
                    'amount' => Money::formatAmount($buyAmount, $currencyId)
                ]));
            }

            $trigger->triggerEvent('content', $visitor->user_id, [
                'amount'       => -$buyAmount,
                'currency_id'  => $charge->Currency->currency_id,
                'message'      => \XF::phrase('mjrcp_buy_content_x') . ($charge->content_type == 'post' ?  '[URL="' . $this->buildLink('canonical:threads/post', $charge->Post->Thread, ['post_id' => $charge->Post->post_id]) . '"]' . $charge->Post->Thread->title . '[/URL]' : '[URL="' . $this->buildLink('canonical:resources', $charge->ResourceUpdate) . '"]' . $charge->ResourceUpdate->title . '[/URL]'),
                'content_id'   => $charge->content_id,
                'content_type' => $charge->content_type,
            ], $error);


            $trigger->triggerEvent('content', $charge->content_type == 'post' ? $charge->Post->User->user_id : $charge->ResourceUpdate->TeamUser->user_id, [
                'amount'           => +$buyAmount,
                'currency_id'  => $charge->Currency->currency_id,
				'message'          => \XF::phrase('mjrcp_sold_content_x') . ($charge->content_type == 'post' ?  '[URL="' . $this->buildLink('canonical:threads/post', $charge->Post->Thread, ['post_id' => $charge->Post->post_id]) . '"]' . $charge->Post->Thread->title . '[/URL]' : '[URL="' . $this->buildLink('canonical:resources', $charge->ResourceUpdate) . '"]' . $charge->ResourceUpdate->title . '[/URL]'),
                'content_id'       => $charge->content_id,
                'content_type'     => $charge->content_type
            ]);

            /** @var \MJ\Credits\Entity\ChargePurchase $chargePurchase */
            $chargePurchase = $this->em()->create('MJ\Credits:ChargePurchase');
            $chargePurchase->content_type = $charge->content_type;
            $chargePurchase->content_id = $charge->content_id;
            $chargePurchase->content_hash = $charge->content_hash;
            $chargePurchase->user_id = $visitor->user_id;
            $chargePurchase->save();

            $trigger->fire();

            return $this->redirect($this->buildLink('posts', $charge->Post), \XF::phrase('mjc_credits_unlock_successful'));
        }

        $viewParams = [
            'currency' => $charge->Currency,
            'charge' => $charge,
        ];

        return $this->view('MJ\Credits:Currency\BuyContent', 'mjc_credits_currency_unlock', $viewParams);
    }

    /**
     * @return \XF\Mvc\Entity\Repository
     */
	protected function getTransactionRepo()
	{
		return $this->repository('MJ\Credits:Transaction');
	}
}
