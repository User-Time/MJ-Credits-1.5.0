<?php

namespace MJ\Credits\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
	public function canViewCredits(&$error = null)
	{
		return $this->hasPermission('mjcCredits', 'useCredits');
	}

	public function canFine()
	{
		return $this->exists() && $this->hasPermission('mjcCredits', 'canFine');
	}

    /**
     * @return bool
     */
    public function canBypassMJCreditsCharge()
    {
        return ($this->canViewCredits()
            && $this->hasPermission('mjcCredits', 'bypassChargeTag')
        );
    }

    protected function _postSave()
    {
        $userId = $this->get('user_id');

        if ($this->isUpdate() && $this->isChanged('avatar_date')) {
            $trigger = $this->app()->service('MJ\Credits:Event\Trigger');
            if ($this->avatar_date && !$this->getPreviousValue('avatar_date')) {
                $trigger->triggerEvent('avatar_upload', $userId, [
                    'maximum_apply' => [
                        'max'          => 1,
                        'with_content' => true,
                        ]
                    ]);
            } else if (!$this->avatar_date && $this->getPreviousValue('avatar_date')) {
                $trigger->triggerEvent('avatar_delete', $userId);
            }
        }

       parent::_postSave();
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['xf_mjc_currency'] = true;

        $currencies = \XF::app()->container('mjc.currencies');
        foreach ($currencies as $currency) {
            $structure->columns[$currency['column']] = ['type' => Entity::FLOAT, 'default' => 0];
        }

        $structure->columns['mjc_credits_last_daily'] = ['type' => Entity::UINT, 'default' => 0, 'changeLog' => false];

        return $structure;
    }

}
