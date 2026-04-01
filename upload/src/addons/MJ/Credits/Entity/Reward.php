<?php
namespace MJ\Credits\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $reward_id
 * @property int $user_id
 * @property int $reward_date
 * @property int $post_id
 * @property int $currency_id
 * @property float $amount
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \XF\Entity\Thread $Thread
 * @property \MJ\Credits\Entity\Currency $Currency
 */
class Reward extends Entity
{
  /**
     * @param Structure $structure
     *
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_mjc_reward';
        $structure->shortName = 'MJ\Credits:Reward';
        $structure->primaryKey = ['reward_id'];
        $structure->columns = [
            'reward_id'        => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'post_id'          => ['type' => self::UINT, 'default' => 0],
            'user_id'          => ['type' => self::UINT, 'required' => true],
            'currency_id'      => ['type' => self::UINT, 'required' => true],
            'amount'           => ['type' => self::FLOAT, 'required' => true, 'default' => 0.0],
            'reward_date'      => ['type' => self::UINT, 'default' => \XF::$time],
        ];
        $structure->getters = [];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [['user_id', '=', '$user_id']],
                'primary' => true
            ],
            'Currency' =>[
                'entity' => 'MJ\Credits:Currency',
                'type' => self::TO_ONE,
                'conditions' => 'currency_id',
                'primary' => true
            ],
            'Post' =>[
                'entity' => 'XF:Post',
                'type' => self::TO_ONE,
                'conditions' => 'post_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}