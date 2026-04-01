<?php

namespace MJ\Credits\Service\Bonus;

use MJ\Credits\ControllerPlugin\Credit;
use XF\Mvc\Entity\Entity;
use MJ\Credits\Entity\Bonus;

class Creator extends \XF\Service\AbstractService
{
    use \XF\Service\ValidateAndSavableTrait;
    /** @var Entity */
    protected $content;

    /** @var Bonus */
    protected $bonus;

    public function __construct(\XF\App $app, Entity $content)
    {
        parent::__construct($app);

        $this->bonus = $this->em()->create('MJ\Credits:Bonus');
        $this->setContent($content);
    }

    protected function setContent(Entity $content)
    {
        $this->content = $content;

        /** @var Bonus $bonus */
        $bonus = $this->em()->create('MJ\Credits:Bonus');

        // might be created before the content has been made
        $id = $content->getEntityId();
        if (!$id)
        {
            $id = $bonus->em()->getDeferredValue(function() use ($content)
            {
                return $content->getEntityId();
            }, 'save');
        }

        $bonus->thread_id = $id;
        $this->bonus = $bonus;

    }

    public function setMessage($message)
    {
        $this->bonus->message = $message;
    }

    public function setCurrencyIds($currencyIds)
    {
        $this->bonus->currency_id = $currencyIds;
    }
    public function setTotalPeople($totalPeople)
    {
        $this->bonus->total_people = $totalPeople;
    }
    public function setUserId($userId)
    {
        $this->bonus->user_id = $userId;
    }
    public function setTotalPoint($totalPoint)
    {
        $this->bonus->total_point =$totalPoint;
    }

    protected function _validate()
    {
        $this->bonus->preSave();
        $bonus = $this->bonus;

        return $bonus->getErrors();
    }

    protected function _save()
    {
        $bonus = $this->bonus;

        $bonus->save();

        return $bonus;
    }

    /**
     * @return \XF\Mvc\Entity\Repository
     */
    protected function getCurrencyRepo()
    {
        return $this->repository('MJ\Credits:Currency');
    }
}