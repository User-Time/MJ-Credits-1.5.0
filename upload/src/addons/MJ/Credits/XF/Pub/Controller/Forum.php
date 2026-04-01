<?php
namespace MJ\Credits\XF\Pub\Controller;

use MJ\Credits\Service\Event\Trigger;
use XF\Mvc\Reply\Error;
use XF\Service\Thread\CreatorService;

class Forum extends XFCP_Forum
{
    /**
     * @param \XF\Entity\Forum $forum
     * @return CreatorService
     * @throws \XF\PrintableException
     */
    protected function setupThreadCreate(\XF\Entity\Forum $forum)
    {
        $creator = parent::setupThreadCreate($forum);

        $input = $this->getInput();
        $visitor = \XF::visitor();

        $setOptions = $this->filter('_xfSet', 'array-bool');
        $thread = $creator->getThread();

        if (isset($setOptions['reward'])) {
            $thread->reward = $this->filter('reward', 'bool');
        }

        $creditPlugin = $this->plugin('MJ\Credits:Credit');

        //抢红包
        if($input['currency_id']) {
            $bonusSendAmount = $input['total_point'];

            $currencies = $creditPlugin->useableCurrencies('bonus');
            $bonusCurrency = $currencies[$input['currency_id']];
            $currencyId = $bonusCurrency['currency_id'];

            if ($visitor->get($bonusCurrency['column']) < $bonusSendAmount) {
                throw \XF::phrasedException('mjc_not_enough_x_to_bonus', [
                    'x' => \MJ\Credits\Util\Money::formatAmount($bonusSendAmount, $currencyId)
                ]);
            }

            if ($bonusSendAmount > 0 && $input['total_people'] > 0) {

                $bonusCreator = $this->plugin('MJ\Credits:Bonus')->setupBonusCreate($creator->getThread());
                $creator->setBonusCreator($bonusCreator);
            }
        }

        $creator->setOfferReward($input['reward_amount']);
        $creator->setCurrencyId($input['reward_currency_id']);

        return $creator;
    }

    /**
     * @param CreatorService $creator
     * @return void|Error
     * @throws \XF\PrintableException
     */
    protected function finalizeThreadCreate(CreatorService $creator)
    {
        parent::finalizeThreadCreate($creator);
        $visitor = \XF::visitor();
        $input = $this->getInput();
        $thread = $creator->getThread();

        /** @var Trigger $trigger */
        $trigger = \XF::app()->service('MJ\Credits:Event\Trigger', false);
        $creditPlugin = $this->plugin('MJ\Credits:Credit');

        if ($this->isPost()) {
            if (!$creator->validate($errors)) {
                return $this->error($errors);
            }

            //抢红包
            $bonusSendAmount = $input['total_point'];

            if ($bonusSendAmount > 0 && $input['total_people'] > 0) {
                $currencies = $creditPlugin->useableCurrencies('bonus');
                $bonusCurrency = $currencies[$input['currency_id']];
               $trigger->triggerEvent('bonus', $visitor->user_id, [
                    'amount' => -$bonusSendAmount,
                    'thread_id' => $thread->thread_id,
                    'currency' => $bonusCurrency,
                    'extra_data' => [
                        'type' => 'bonus',
                        'send_user_id' => $visitor->user_id
                    ]
                ], $error);
            }

            //悬赏
            $offerRewardSendAmount = $input['reward_amount'];
            if ($offerRewardSendAmount > 0) {
                $currencies = $creditPlugin->useableCurrencies('offerReward');
                $currency = $currencies[$input['reward_currency_id']];

               $trigger->triggerEvent('offerReward', $visitor->user_id, [
                    'amount' => -$offerRewardSendAmount,
                    'thread_id' => $thread->thread_id,
                    'currency' => $currency,
                    'extra_data' => [
                        'type' => 'offerReward',
                        'send_user_id' => $visitor->user_id
                    ]
                ], $error);

            }
            $trigger->fire();
        }
    }

    public function getInput()
    {
        return $this->filter([
            'currency_id' => 'int',
            'total_point' => 'unum',
            'total_people' =>'int',
            'bonus_message' => 'str',
            'reward_amount' => 'unum',
            'reward_currency_id' => 'uint'
        ]);
    }
}