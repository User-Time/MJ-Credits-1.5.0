<?php
namespace MJ\Credits\Service\Thread;

class Creator extends XFCP_Creator
{
   protected $bonusCreator;

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
