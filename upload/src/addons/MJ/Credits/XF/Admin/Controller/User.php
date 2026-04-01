<?php
namespace MJ\Credits\XF\Admin\Controller;

use MJ\Credits\ControllerPlugin\Credit;
use MJ\Credits\Service\Event\Trigger;
class User extends XFCP_User
{
	protected function userAddEdit(\XF\Entity\User $user)
	{
		$reply = parent::userAddEdit($user);

		$reply->setParams([
			'currencies' => $this->repository('MJ\Credits:Currency')->findCurrenciesForList()->fetch()
		]);
		return $reply;
	}

    protected function userSaveProcess(\XF\Entity\User $user)
    {
        $form = parent::userSaveProcess($user);

        $currencies = $this->repository('MJ\Credits:Currency')->findCurrenciesForList()->fetch();
        $input = $this->filter('credits', 'array');

        $form->validate(function () use (&$input, $user, $currencies) {
            foreach ($currencies as $currency) {
                // Make sure there's an adjust event
                $currency->verifyAdjustEvent();

                if (!isset($input[$currency->currency_id])) {
                    // This was probably a deactivated currency
                    unset($input[$currency->currency_id]);

                    continue;
                }

                if ($user->{$currency->getColumn()} == $input[$currency->currency_id]) {
                    // No change in points
                    unset($input[$currency->currency_id]);
                }
            }

            foreach ($input as $currencyId => $value) {
                if (!isset($currencies[$currencyId])) {
                    // Ignore this currency
                    unset($input[$currencyId]);
                    continue;
                }
            }
        });

        $form->complete(function () use ($input, $user, $currencies) {

            foreach ($input as $currencyId => $value) {
                $currency = $currencies[$currencyId];

                /** @var Credit $creditPlugin */
                $creditPlugin = $this->plugin('MJ\Credits:Credit');
                $adjustCurrencies = $creditPlugin->useableCurrencies('adjust');
                $adjustCurrency = $adjustCurrencies[$currencyId];

                $trigger = \XF::app()->service('MJ\Credits:Event\Trigger', false);

                if ($user->{$currency->getColumn()} < $value) {
                    /** @var Trigger $trigger */
                   $trigger->triggerEvent('adjust', $user->user_id, [
                        'amount' => abs($value - $user->{$currency->getColumn()}),
                        'content_type' => 'adjust',
                        'currency' => $adjustCurrency,
                        'message' => \XF::language()->renderPhrase('mjc_credits_admin_adjust'),
                        'extra_data' => [
                            'type' => 'adjust',
                            'receive_user_id' => $user->user_id,
                            'trigger_user_id' => \XF::visitor()->user_id
                        ]
                    ]);

                    $trigger->fire();

                } elseif ($user->{$currency->getColumn()} > $value) {
                    /** @var Trigger $trigger */
                    $trigger->triggerEvent('adjust', $user->user_id, [
                        'amount' => (-1 * abs($user->{$currency->getColumn()} - $value)),
                        'content_type' => 'adjust',
                        'currency' => $adjustCurrency,
                        'message' => \XF::language()->renderPhrase('mjc_credits_admin_adjust'),
                        'extra_data' => [
                            'type' => 'adjust',
                            'receive_user_id' => $user->user_id,
                            'trigger_user_id' => \XF::visitor()->user_id
                        ]
                    ]);

                    $trigger->fire();
                }
            }
        });

        return $form;
    }
}
