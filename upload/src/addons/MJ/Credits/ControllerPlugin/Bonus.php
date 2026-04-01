<?php
namespace MJ\Credits\ControllerPlugin;

use MJ\Credits\Service\Bonus\Creator;
use XF\ControllerPlugin\AbstractPlugin;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;

class Bonus extends AbstractPlugin
{
    /**
     * @param Entity $content
     * @return Creator
     */
   public function setupBonusCreate(Entity $content)
   {
        $input = $this->getInput();
        $visitor =\XF::visitor();
        /** @var Creator $creator */
        $creator = $this->service('MJ\Credits:Bonus\Creator', $content);

        if (!$input['currency_id']) {
            $currency = reset($currencies);
            $input['currency_id'] = $currency['currency_id'];
        }

        $creator->setUserId($visitor->user_id);
        $creator->setCurrencyIds($input['currency_id']);
        $creator->setTotalPeople($input['total_people']);
        $creator->setTotalPoint($input['total_point']);
        $creator->setMessage($input['bonus_message']);

        return $creator;
    }

    public function getInput()
    {
        return $this->filter([
            'currency_id' => 'int',
            'total_point' => 'float',
            'total_people' =>'int',
            'bonus_message' => 'str'
        ]);
    }
}