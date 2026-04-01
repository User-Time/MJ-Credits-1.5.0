<?php
namespace MJ\Credits\XF\Pub\Controller;

use MJ\Credits\Service\Event\Trigger;
use XF\Mvc\ParameterBag;

class Thread extends XFCP_Thread
{
    /**
     * @param \XF\Entity\Thread $thread
     *
     * @return \XF\Service\Thread\EditorService
     */
    protected function setupThreadEdit(\XF\Entity\Thread $thread)
    {
        $editor = parent::setupThreadEdit($thread);
        $setOptions = $this->filter('_xfSet', 'array-bool');
        if (isset($setOptions['reward']))
        {
            $thread->reward = $this->filter('reward', 'bool');
        }

        if (isset($setOptions['offer_reward']))
        {
            $thread->offer_reward = $this->filter('offer_reward', 'bool');
        }

        return $editor;
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionRewardEdit(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);

        $visitor = \XF::visitor();
        if ($visitor->user_id != $thread->user_id) {
            return $this->noPermission();
        }

        /** @var \XF\Service\Thread\EditorService $threadEditor */
        $threadEditor = $this->service('XF:Thread\EditorService', $thread);

        $rewardInput = $this->filter([
            'reward_amount' => 'str',
            'reward_currency_id' => 'uint'
        ]);

        /** @var \MJ\Credits\ControllerPlugin\Credit $creditPlugin */
        $creditPlugin = $this->plugin('MJ\Credits:Credit');

        $currencies = $creditPlugin->useableCurrencies('offerReward');

        $currentAmount = $thread->reward_amount;
        $rewardAmounts = str_replace(',', '', $rewardInput['reward_amount']);

        $rewardAmount = $currentAmount - (float)$rewardAmounts;

        if ($this->isPost()) {
            if (!isset($currencies[$rewardInput['reward_currency_id']])) {
                return $this->error(\XF::phrase('mjc_requested_currency_not_found'));
            }
            $currency = $currencies[$rewardInput['reward_currency_id']];
            $currencyId = $currency['currency_id'];

            if($rewardAmount < 0 ) {
                if ($visitor->get($currency['column']) < abs($rewardAmount)) {
                    throw \XF::phrasedException('mjc_not_enough_x_to_offer_reward', [
                        'x' => \MJ\Credits\Util\Money::formatAmount(abs($rewardAmount), $currencyId)
                    ]);
                }
            }

            if ($rewardInput['reward_amount'] > 0) {
                $threadEditor->setOfferReward($rewardAmounts);
                $threadEditor->setCurrencyId($rewardInput['reward_currency_id']);
                $threadEditor->save();

                $currency = $currencies[$rewardInput['reward_currency_id']];
                /** @var Trigger $trigger */
                $trigger = \XF::app()->service('MJ\Credits:Event\Trigger', false);
                $trigger->triggerEvent('offerReward', \XF::visitor()->user_id, [
                    'amount' => $rewardAmount,
                    'thread_id' => $thread->thread_id,
                    'currency' => $currency,
                    'extra_data' => [
                        'type' => 'offerReward',
                        'send_user_id' => \XF::visitor()->user_id
                    ]
                ], $error);

                $trigger->fire();

                return $this->redirect(
                    $this->getDynamicRedirect($this->buildLink('threads', $thread), false)
                );
            } else {
                return $this->error(\XF::phrase('mjc_credits_please_enter_an_amount_greater_than_zero'));
            }
        }

        $viewParams = [
            'currencies' => $currencies,
            'thread' => $thread,
        ];

        return $this->view('XF:Thread\RewardEdit', 'mjc_reward_edit', $viewParams);
    }
}