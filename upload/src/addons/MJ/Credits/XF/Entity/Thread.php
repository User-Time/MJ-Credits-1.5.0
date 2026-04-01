<?php

namespace MJ\Credits\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class Thread
 * @package MJ\Credits\XF\Entity
 * @property bool reward
 */
class Thread extends XFCP_Thread
{
	protected function _postSave()
    {
        $trigger = $this->app()->service('MJ\Credits:Event\Trigger');

        if ($this->isInsert() && $this->sticky) {
            $trigger->triggerEvent('thread_sticky', $this->user_id, [
                'content_type' => 'thread',
                'content_id' => $this->thread_id,
                'node_id' => $this->node_id,
            ]);
        } else if ($this->isUpdate() && $this->isChanged('sticky')) {
            if ($this->sticky) {
                $trigger->triggerEvent('thread_sticky', $this->user_id, [
                    'content_type' => 'thread',
                    'content_id' => $this->thread_id,
                    'node_id' => $this->node_id,
                ]);
            } else {
                $trigger->triggerEvent('thread_unsticky', $this->user_id, [
                    'content_type' => 'thread',
                    'content_id' => $this->thread_id,
                    'node_id' => $this->node_id,
                ]);
            }
        }
        return parent::_postSave();
    }

	protected function _postDelete()
	{
		$trigger = $this->app()->service('MJ\Credits:Event\Trigger');
		$trigger->triggerEvent('thread_delete', $this->user_id, [
			'content_type' => 'thread',
			'content_id'   => $this->thread_id,
			'node_id'      => $this->node_id,
		]);
		return parent::_postDelete();
	}

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);
        $structure->columns['reward'] = ['type' => self::BOOL, 'default' => true];
        $structure->columns['reward_amount'] = ['type' => self::FLOAT, 'default' => 0];
        $structure->columns['reward_currency_id'] = ['type' => self::UINT, 'default' => 0];
        $structure->columns['offer_reward'] = ['type' => self::BOOL, 'default' => false];

        $structure->relations['OpenBonusTemp'] = [
            'entity' => 'MJ\Credits:OpenBonusTemp',
            'type' => entity::TO_ONE,
            'conditions' => 'thread_id',
            'primary' => true
        ];

        return $structure;
    }

    public function getBonus()
    {
        $threadId = $this->thread_id;

        return \XF::finder('MJ\Credits:Bonus')
            ->where('thread_id', $threadId)
            ->where('total_point','>',0)
            ->fetchOne();
    }

    public function getBonusUser()
    {
        return $this->finder('MJ\Credits:OpenBonusTemp')
            ->where('thread_id', $this->thread_id)
            ->fetchOne();
    }

    public function _preDelete()
    {
        $threadId = $this->thread_id;
        if ($this->thread_id) {
            $bonusFinder = \XF::finder('MJ\Credits:Bonus')
                ->where('thread_id', $threadId)
                ->fetchOne();

            $bonusTemps = \XF::finder('MJ\Credits:OpenBonusTemp')
                ->where('thread_id', $threadId)
                ->fetch();
            if ($bonusTemps) {
                foreach ($bonusTemps as $bonusTemp) {
                    $bonusTemp->delete();
                }
            }
            if ($bonusFinder) {
                $bonusFinder->delete();
            }
        }
       return parent::_preDelete();
    }

    public function softDelete($reason = '', User|\XF\Entity\User|null $byUser = null)
    {
        $soft = parent::softDelete($reason, $byUser);

        $trigger = $this->app()->service('MJ\Credits:Event\Trigger');
        $trigger->triggerEvent('soft_thread_delete', $this->user_id, [
            'content_type' => 'thread',
            'content_id'   => $this->thread_id,
            'node_id'      => $this->node_id,
        ]);

        return $soft;
    }
}