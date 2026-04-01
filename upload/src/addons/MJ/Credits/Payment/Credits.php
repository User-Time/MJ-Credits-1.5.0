<?php

namespace MJ\Credits\Payment;

use XF\Payment\AbstractProvider;
use XF\Payment\CallbackState;
use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Mvc\Controller;
use XF\Purchasable\Purchase;

class Credits extends AbstractProvider
{
	/**
	 * @return string
	 */
	public function getTitle()
	{
		return '[BR] Credits';
	}

	/**
	 * @param \XF\Entity\PaymentProfile $profile
	 *
	 * @return string
	 */
	public function renderConfig(PaymentProfile $profile)
	{
		$data = [
			'profile' => $profile,
			'currencies' => \XF::finder('MJ\Credits:Currency')
				->fetch()
				->pluckNamed('title', 'currency_id')
		];
		return \XF::app()->templater()->renderTemplate('admin:payment_profile_' . $this->providerId, $data);
	}

	/**
	 * @param array $options
	 * @param array $errors
	 *
	 * @return bool
	 */
	public function verifyConfig(array &$options, &$errors = [])
	{
		$currency = \XF::em()->find('MJ\Credits:Currency', $options['currency_id']);
		if (!$currency)
		{
			$errors[] = \XF::phrase('mjc_credits_invalid_currency');
			return false;
		}

		if (empty($options['exchange_rate']))
		{
			$errors[] = \XF::phrase('mjc_credits_must_specify_exchange_rate');
			return false;
		}

		return true;
	}

	/**
	 * @param \XF\Mvc\Controller $controller
	 * @param \XF\Entity\PurchaseRequest $purchaseRequest
	 * @param \XF\Purchasable\Purchase $purchase
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
	 */
	public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
    {
        $paymentProfile = $purchase->paymentProfile;
        $cost = $purchase->cost * $paymentProfile->options['exchange_rate'];
        $currency = $this->getCurrencyFromPaymentProfile($paymentProfile);

        $visitor = \XF::visitor();
        /** @var \MJ\Credits\Service\Event\Trigger $trigger */
        $trigger = \XF::app()->service('MJ\Credits:Event\Trigger', false);

        /** @var \MJ\Credits\ControllerPlugin\Credit $creditPlugin */
        $creditPlugin = $controller->plugin('MJ\Credits:Credit');

        $currencies = $creditPlugin->useableCurrencies('payment');

        if (!$currencies) {
            return $controller->noPermission();
        }

        if ($visitor->get($currency['column']) < $cost) {
            return $controller->error(\XF::phrase('mjc_not_enough_x_to_payment', [
                'amount' => \MJ\Credits\Util\Money::formatAmount($cost, $currency)
            ]));
        }

        $trigger->triggerEvent('payment', $purchase->purchaser->user_id,
            [
                'amount' => $cost,
                'currency_id' => $currency->currency_id,
                'purchaseRequest' => $purchaseRequest,
                'paymentProfile' => $paymentProfile,
                'purchaser' => $purchase->purchaser,
                'purchase' => $purchase
            ]);


        $viewParams = [
            'purchaseRequest' => $purchaseRequest,
            'paymentProfile' => $paymentProfile,
            'purchaser' => $purchase->purchaser,
            'purchase' => $purchase,
            'currency' => $currency,
            'cost' => $cost
        ];

        return $controller->view('MJ\Credits:Payment', 'mjc_credits_payment_initiate', $viewParams);
    }

	/**
	 * @param \XF\Mvc\Controller $controller
	 * @param \XF\Entity\PurchaseRequest $purchaseRequest
	 * @param \XF\Entity\PaymentProfile $paymentProfile
	 * @param \XF\Purchasable\Purchase $purchase
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|null
	 */
	public function processPayment(Controller $controller, PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile, Purchase $purchase)
    {
        $refId = $purchaseRequest->purchase_request_id . '_' . md5(\XF::$time);
        $currency = $this->getCurrencyFromPaymentProfile($paymentProfile);

        /** @var \MJ\Credits\Service\Event\Trigger $trigger */
        $trigger = \XF::app()->service('MJ\Credits:Event\Trigger', false);

        $trigger->triggerEvent('payment', $purchase->purchaser->user_id,
            [
                'amount' => -$purchase->cost * $paymentProfile->options['exchange_rate'],
                'currency_id' => $currency->currency_id,
                'purchaseRequest' => $purchaseRequest,
                'paymentProfile' => $paymentProfile,
                'purchaser' => $purchase->purchaser,
                'purchase' => $purchase,
                'content_id' => $purchase->purchasableId,
                'content_type' => $purchase->purchasableTypeId,
            ]);


        $trigger->fire();

        $state = new CallbackState();
        $state->transactionId = $refId;
        $state->paymentResult = CallbackState::PAYMENT_RECEIVED;

        $state->purchaseRequest = $purchaseRequest;
        $state->paymentProfile = $paymentProfile;

        $this->completeTransaction($state);

        $this->log($state);

        return $controller->redirect($purchase->returnUrl, '');
    }

	/**
	 * @param \XF\Http\Request $request
	 *
	 * @return \XF\Payment\CallbackState
	 */
	public function setupCallback(\XF\Http\Request $request)
	{
		return new CallbackState();
	}

	/**
	 * @param \XF\Payment\CallbackState $state
	 */
	public function getPaymentResult(CallbackState $state)
	{
		$state->paymentResult = CallbackState::PAYMENT_RECEIVED;
	}

	/**
	 * @param \XF\Payment\CallbackState $state
	 */
	public function prepareLogData(CallbackState $state)
	{
		$state->logDetails = [
            '_GET' => $_GET
        ];
	}

	/**
	 * @param \XF\Entity\PaymentProfile $paymentProfile
	 * @param null $error
	 *
	 * @return \MJ\Credits\Entity\Currency|\XF\Mvc\Entity\Entity|null
	 */
	protected function getCurrencyFromPaymentProfile(PaymentProfile $paymentProfile, &$error = null)
	{
		if (empty($paymentProfile->options['currency_id']))
		{
			$error = \XF::phrase('mjc_credits_invalid_currency');
			return null;
		}

		$currency = \XF::em()->find('MJ\Credits:Currency', $paymentProfile->options['currency_id']);
		if (!$currency)
		{
			$error = \XF::phrase('this_item_cannot_be_purchased_at_moment');
			return null;
		}

		return $currency;
	}
}