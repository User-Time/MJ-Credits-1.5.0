<?php

namespace MJ\Credits\Entity;

use MJ\Credits\Event\EventHandler;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int content_id
 * @property string content_hash
 * @property float cost
 *
 * GETTERS
 * @property Currency Currency
 *
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|ChargePurchase[] Purchases
 * @property \XF\Entity\Post Post
 */
class Charge extends Entity
{
    /**
     * @return EventHandler|null
     * @throws \XF\PrintableException
     * @throws \Exception
     */
    public function getHandler(): ?EventHandler
    {
        $currency = $this->Currency;
        $currency->verifyChargeEvent();

        return $this->getEventRepo()->getHandler('content');
    }
    
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
        $structure->table = 'xf_mjc_credits_charge';
        $structure->shortName = 'MJ\Credits:Charge';
        $structure->primaryKey = ['content_type', 'content_id', 'content_hash'];
        $structure->columns = [
            'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
            'content_id' => ['type' => self::UINT, 'required' => true],
            'content_hash' => ['type' => self::STR, 'required' => true],
            'cost' => ['type' => self::FLOAT, 'required' => true, 'default' => 0.0],
        ];
        $structure->getters = [
            'Currency' => true,
        ];
        $structure->relations = [
            'Purchases' => [
                'entity' => 'MJ\Credits:ChargePurchase',
                'type' => self::TO_MANY,
                'conditions' => [
                    ['content_type', '=', '$content_type'],
                    ['content_id', '=', '$content_id'],
                    ['content_hash', '=', '$content_hash']
                ],
                'with' => ['User'],
                'key' => 'user_id'
            ],
            'ResourceUpdate' => [
                'entity' => 'XFRM:ResourceUpdate',
                'type' => self::TO_ONE,
                'primary' => true,
                'conditions' => [
                    ['resource_update_id', '=', '$content_id'],
                ]
            ],
            'Post' => [
                'entity' => 'XF:Post',
                'type' => self::TO_ONE,
                'primary' => true,
                'conditions' => [
                    ['post_id', '=', '$content_id'],
                ],
                'with' => ['Thread']
            ],
        ];

        $structure->defaultWith = ['Post'];

        return $structure;
    }

	/**
	 * @return \MJ\Credits\Repository\Event|Repository
	 */
	protected function getEventRepo()
	{
		return $this->repository('MJ\Credits:Event');
	}
}