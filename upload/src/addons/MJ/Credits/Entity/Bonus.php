<?php
namespace MJ\Credits\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Bonus extends Entity
{
    /**
     * @return Currency
     */
    public function getCurrency()
    {
        return $this->repository('MJ\Credits:Currency')
            ->getChargeCurrency();
    }

    /**
     * @param Currency|null $currency
     */
    public function setCurrency(Currency $currency = null)
    {
        $this->_getterCache['Currency'] = $currency;
    }

    /**
     * @param Structure $structure
     *
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_mjc_bonus';
        $structure->shortName = 'MJ\Credits:Bonus';
        $structure->primaryKey = ['bonus_id'];
        $structure->columns = [
            'bonus_id'         => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'user_id'          => ['type' => self::UINT, 'required' => true],
            'currency_id'      => ['type' => self::UINT, 'required' => true],
            'message'          => ['type' => self::STR, 'default' => ''],
            'total_point'      => ['type' => self::FLOAT, 'required' => true, 'default' => 0.0],
            'total_people'     => ['type' => self::UINT, 'required' => true],
            'thread_id'        => ['type' => self::UINT, 'default' => 0],
            'extra_data'       => ['type' => self::LIST_COMMA, 'default' => []],
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
            ]
        ];
        $structure->defaultWith = ['User'];

        return $structure;
    }

    /**
     * @return \MJ\Credits\Repository\Event|\XF\Mvc\Entity\Repository
     */
    protected function getEventRepo()
    {
        return $this->repository('MJ\Credits:Event');
    }
}