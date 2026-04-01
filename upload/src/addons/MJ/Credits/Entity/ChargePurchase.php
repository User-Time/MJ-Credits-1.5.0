<?php

namespace MJ\Credits\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int post_id
 * @property string content_hash
 * @property int user_id
 *
 * RELATIONS
 * @property \MJ\Credits\Entity\Charge Charge
 * @property \XF\Entity\Post Post
 * @property \XF\Entity\User User
 */
class ChargePurchase extends Entity
{
    /**
     * @return Currency
     */
    public function getCurrency(): Currency
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
     * @return Structure
     */
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mjc_credits_charge_purchase';
		$structure->shortName = 'MJ\Credits:ChargePurchase';
        $structure->primaryKey = ['content_type', 'content_id', 'content_hash', 'user_id'];
        $structure->columns = [
            'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
            'content_id'   => ['type' => self::UINT, 'required' => true],
            'content_hash' => ['type' => self::STR, 'required' => true],
            'user_id'      => ['type' => self::UINT, 'required' => true],
        ];
		$structure->getters = [
			'Currency' => true,
		];
		$structure->relations = [
            'Charge' => [
                'entity'     => 'MJ\Credits:Charge',
                'type'       => self::TO_ONE,
                'conditions' => [
                    ['content_type', '=', '$content_type'],
                    ['content_id', '=', '$content_id'],
                    ['content_hash', '=', '$content_hash']
                ],
            ],
            'Post'   => [
                'entity'     => 'XF:Post',
                'type'       => self::TO_ONE,
                'primary'    => true,
                'conditions' => [
                    ['post_id', '=', '$content_id'],
                ],
                'with'       => ['Thread']
            ],
            'User'   => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary'    => true
            ],
		];
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