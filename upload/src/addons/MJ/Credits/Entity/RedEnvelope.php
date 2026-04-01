<?php
namespace MJ\Credits\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class RedEnvelope extends Entity
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
        $structure->table = 'xf_mjc_red_envelope';
        $structure->shortName = 'MJ\Credits:RedEnvelope';
        $structure->primaryKey = ['red_envelope_id'];
        $structure->columns = [
            'red_envelope_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'post_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'from_user_id' => ['type' => self::UINT, 'required' => true],
            'start_date' => ['type' => self::UINT, 'default' => 0],
            'end_date' => ['type' => self::UINT, 'default' => 0],
            'currency_id'            => ['type' => self::UINT, 'required' => true],
            'message'            => ['type' => self::STR, 'default' => ''],
            'status'            => ['type' => self::STR, 'default' => 'pending',
                'allowedValues' => ['pending', 'claimed']
            ],
            'amount' => ['type' => self::FLOAT, 'required' => true, 'default' => 0.0],
        ];
        $structure->getters = [
            'Currency' => true,
        ];
        $structure->relations = [
            'FromUser' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [['user_id', '=', '$from_user_id']],
                'primary' => true
            ],
        ];
        $structure->defaultWith = ['FromUser'];

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