<?php
namespace MJ\Credits\XF\Service\Thread;

class Editor extends XFCP_Editor
{
    public function setOfferReward($rewardAmount)
    {
        $this->thread->reward_amount = $rewardAmount;
    }

    public function setCurrencyId($currencyId)
    {
        $this->thread->reward_currency_id = $currencyId;
    }
}