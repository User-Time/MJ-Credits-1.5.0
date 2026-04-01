<?php
namespace MJ\Credits\XF\Service\Thread;

class Creator extends XFCP_Creator
{
    protected $bonusCreator;

    public function setOfferReward($rewardAmount)
    {
        $this->thread->reward_amount = $rewardAmount;
    }

    public function setCurrencyId($currencyId)
    {
        $this->thread->reward_currency_id = $currencyId;
    }

    /**
     * @throws \XF\PrintableException
     */
    protected function _validate()
    {
        $thread = $this->thread;
        $reward = \XF::app()->request()->filter([
                'reward_amount' => 'uint',
                'reward_currency_id' => 'uint'
            ]);

        $errors = $thread->getErrors();
        $visitor = \XF::visitor();

        if($reward['reward_amount'] > 0) {
            $currencies = $this->repository('MJ\Credits:Currency')->useableCurrencies('offerReward');
            $currency = $currencies[$reward['reward_currency_id']];

            $currencyId = $currency['currency_id'];

            if ($thread->discussion_type != 'question') {
                $typeErrors[] = \XF::phraseDeferred('mjc_credits_please_select_a_thread_to_post');
                return $errors = array_merge($errors, $typeErrors);
            }

            if ($visitor->get($currency['column']) < $reward['reward_amount']) {
                throw \XF::phrasedException('mjc_not_enough_x_to_offer_reward', [
                    'x' => \MJ\Credits\Util\Money::formatAmount($reward['reward_amount'], $currencyId)
                ]);
            }
        }

        return parent::_validate();
    }

    public function setBonusCreator(\MJ\Credits\Service\Bonus\Creator $result = null)
    {
        $this->bonusCreator = $result;
    }

    protected function _save()
    {
        \XF::db()->beginTransaction();

        $response = parent::_save();

        if ($this->bonusCreator) {
            $this->bonusCreator->save();
        }

        \XF::db()->commit();

        return $response;
    }
}