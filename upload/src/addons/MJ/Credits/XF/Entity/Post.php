<?php

namespace MJ\Credits\XF\Entity;

use XF\Mvc\Entity\Structure;

class Post extends XFCP_Post
{
    /**
     * @param $inner
     * @return string
     */
    public function getQuoteWrapper($inner)
    {
        return parent::getQuoteWrapper(preg_replace(
            '#\[' . preg_quote(\XF::options()->mjc_credits_event_trigger_content_bbcode, '#') . '=(\d+|\d+[.,](\d+))\](.*)\[\/' . preg_quote(\XF::options()->mjc_credits_event_trigger_content_bbcode, '#') . '\]#si',
            \XF::phrase('mjc_credits_stripped_content'),
            $inner
        ));
    }

    protected function _postSave()
    {
        if ($this->isInsert()) {
            $thread = $this->Thread;
            if ($thread) {
                $trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
                if ($this->isFirstPost()) {
                    $trigger->triggerEvent('thread_new', $this->user_id, [
                        'trigger_user_id' => $this->user_id,
                        'content_type' => 'post',
                        'content_id' => $this->post_id,
                        'node_id' => $thread->node_id,
                    ]);
                } else {
                    $db = \XF::db();

                    // 查询用户是否已经在该主题下获得过积分
                    $exists = $db->fetchOne("
                SELECT 1
                FROM xf_mjc_transaction
                WHERE user_id = ?
                  AND content_id IN (
                      SELECT post_id
                      FROM xf_post
                      WHERE thread_id = ?
                  )
                LIMIT 1
            ", [$this->user_id, $thread->thread_id]);

                    if (!$exists || !\XF::options()->mjc_credits_get_same_thread_once) {
                        $trigger->triggerEvent('post_new', $this->user_id, [
                            'trigger_user_id' => $this->user_id,
                            'content_type' => 'post',
                            'content_id' => $this->post_id,
                            'node_id' => $thread->node_id,
                        ]);
                    }

                    if ($thread->user_id != $this->user_id) {
                        $trigger->triggerEvent('thread_reply_receive', $thread->user_id, [
                            'trigger_user_id' => $this->user_id,
                            'content_type' => 'post',
                            'content_id' => $this->post_id,
                            'node_id' => $thread->node_id,
                        ]);
                    }
                }
                $trigger->fire();
            }
        }

        if (!$this->user_id) {
            return parent::_postSave();
        }

        if ($this->isUpdate()) {
            if ($this->isChanged('message')) {
                // Get rid of any existing charge tags, the BBCode renderer will re-process them
                $this->db()->delete('xf_mjc_credits_charge', 'content_id = ' . $this->post_id);
            }
        }

        return parent::_postSave();
    }

    protected function _postDelete()
    {
        if (!$this->isFirstPost()) {
            $thread = $this->Thread;
            if ($thread) {
                $trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
                $trigger->triggerEvent('post_delete', $this->user_id, [
                    'trigger_user_id' => $this->user_id,
                    'content_type' => 'post',
                    'content_id' => $this->post_id,
                    'node_id' => $thread->node_id,
                ]);
                $trigger->triggerEvent('thread_reply_lose', $thread->user_id, [
                    'content_type' => 'post',
                    'content_id' => $this->post_id,
                    'node_id' => $thread->node_id,
                ]);
                $trigger->fire();
            }
        }

        return parent::_postDelete();
    }

    public function softDelete($reason = '', User|\XF\Entity\User|null $byUser = null)
    {
        $soft = parent::softDelete($reason, $byUser);

        if (!$this->isFirstPost()) {
            $thread = $this->Thread;
            $trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);
            $trigger->triggerEvent('soft_post_delete', $this->user_id, [
                'trigger_user_id' => $this->user_id,
                'content_type' => 'post',
                'content_id' => $this->post_id,
                'node_id' => $thread->node_id,
            ]);
            $trigger->triggerEvent('thread_reply_lose', $thread->user_id, [
                'content_type' => 'post',
                'content_id' => $this->post_id,
                'node_id' => $thread->node_id,
            ]);
            $trigger->fire();
        }
        return $soft;
    }

    public function getPurchaser()
    {
        $postId = $this->post_id;

        return \XF::finder('MJ\Credits:ChargePurchase')
            ->where('content_id', $postId)
            ->fetchOne();
    }

    /**
     * @return |null
     * @throws \XF\PrintableException
     */
    public function getRedEnvelope()
    {
        $postId = $this->post_id;

        $user = \XF::finder('MJ\Credits:RedEnvelope')
            ->where('post_id', $postId)
            ->where('end_date', '>', time())
            ->fetchOne();

        if ($user) {
            return $user->user_id;
        } else {
            return false;
        }
    }

    public function getRedEnvelopeFrom()
    {
        $postId = $this->post_id;

        $userIds = \XF::finder('MJ\Credits:RedEnvelope')
            ->where('post_id', $postId)
            ->where('end_date', '>', time())
            ->fetch()
            ->pluckNamed('FromUser', 'user_id');

        return $userIds;
    }

    public function getRedEnvelopeStatus()
    {
        $postId = $this->post_id;
        $redEnvelopeStatus = \XF::finder('MJ\Credits:RedEnvelope')
            ->where('post_id', $postId)
            ->fetchOne();

        if (!$redEnvelopeStatus) {
            return false;
        }
        return $redEnvelopeStatus->status;
    }

    public function getRedEnvelopeMessage(): ?\XF\Mvc\Entity\Entity
    {
        $postId = $this->post_id;

        return $this->finder('MJ\Credits:RedEnvelope')
            ->where('post_id', '=', $postId)
            ->where('status', '=', 'pending')
            ->fetchOne();
    }

    public function getBonus()
    {
        $threadId = $this->thread_id;

        return \XF::finder('MJ\Credits:Bonus')
            ->where('thread_id', $threadId)
            ->where('total_point', '>', 0)
            ->fetchOne();
    }

    public function getBonusUser()
    {
        return $this->finder('MJ\Credits:OpenBonusTemp')
            ->where('thread_id', $this->thread_id)
            ->fetchOne();
    }

    public function getBonusMessage()
    {
        $threadId = $this->thread_id;

        return \XF::finder('MJ\Credits:Bonus')
            ->where('thread_id', '=', $threadId)
            ->fetchOne();
    }

    /**
     * @param \XF\Mvc\Entity\Structure $structure
     *
     * @return \XF\Mvc\Entity\Structure
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->relations += [
            'Reward' => [
                'entity' => 'MJ\Credits:Reward',
                'type' => self::TO_MANY,
                'conditions' => [
                    ['post_id', '=', '$post_id']
                ]
            ]
        ];
        return $structure;
    }
}
