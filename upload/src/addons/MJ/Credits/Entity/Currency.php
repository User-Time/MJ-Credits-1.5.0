<?php

namespace MJ\Credits\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $currency_id
 * @property bool $active
 * @property string $prefix
 * @property string $suffix
 * @property int $decimal_places
 * @property int $display_order
 * @property bool wallet_popup
 * @property array $allowed_user_group_ids
 * @property float $max_amount
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|\MJ\Credits\Entity\Event[] $Events
 **/

class Currency extends Entity
{
    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

	public function isUsableByUser(\XF\Entity\User $user = null)
	{
		$user = $user ?: \XF::visitor();

		foreach ($this->allowed_user_group_ids as $userGroupId)
		{
			if ($userGroupId == -1 || $user->isMemberOf($userGroupId))
			{
				return true;
			}
		}

		return false;
	}

	public function getPhraseName($title)
	{
		return 'mjc_currency_' . ($title ? 'title' : 'desc') . '.' . $this->currency_id;
	}

	public function getTitle()
	{
		return \XF::phrase($this->getPhraseName(true));
	}

	public function getDescription()
	{
		return \XF::phrase($this->getPhraseName(false));
	}

	public function getColumn()
	{
		return $this->getCurrencyRepo()->getColumn($this->currency_id);
	}

	public function getMasterPhrase($title)
	{
		$phrase = $title ? $this->MasterTitle : $this->MasterDescription;
		if (!$phrase)
		{
			$phrase = $this->_em->create('XF:Phrase');
			$phrase->title = $this->_getDeferredValue(function () use ($title) {
				return $this->getPhraseName($title);
			});
			$phrase->language_id = 0;
			$phrase->addon_id = '';
		}

		return $phrase;
	}

	protected function _preSave()
	{
		if ($this->getOption('check_duplicate'))
		{
			/*$existing = $this->finder('MJ\Credits:Currency')->where([
				'column' => $this->column
			])->fetchOne();
			if ($existing && $existing !== $this)
			{
				$this->error(\XF::phrase('mjc_currency_column_must_be_unique'), 'column');
			}*/
		}
	}

	protected function _postSave()
	{
		$this->rebuildCurrencyColumn();
		$this->rebuildCurrencyCache();

        // Make sure we have an adjust event
        $this->verifyAdjustEvent();
	}

	protected function _preDelete()
	{
		/*$curencyCount = $this->finder('MJ\Credits:Currency')->total();
		if ($curencyCount <= 1)
		{
			$this->error(\XF::phrase('mjc_it_is_not_possible_to_delete_last_currency'));
		}*/
	}

	protected function _postDelete()
	{
		$this->rebuildCurrencyCache();
		try
		{
			$alter = $this->db()->getSchemaManager()->newAlter('xf_user');
			$alter->dropColumns([$this->getColumn()]);
			$alter->apply();
		}
		catch (\LogicException $e)
		{
		}
	}

	protected function rebuildCurrencyColumn()
	{
		$column = $this->getColumn();
		$alter = $this->db()->getSchemaManager()->newAlter('xf_user');
		$existing = $alter->getColumnDefinition($column);

		if($this->isInsert()){
			if($existing){
				$alter->changeColumn($column, 'decimal', '19,8')->unsigned(false)->setDefault(0);
			}else{
				$alter->addColumn($column, 'decimal', '19,8')->unsigned(false)->setDefault(0);
			}
			$alter->apply();
		}else{
			if(!$existing){
				$alter->addColumn($column, 'decimal', '19,8')->unsigned(false)->setDefault(0);
				$alter->apply();
			}
		}
	}

	protected function rebuildCurrencyCache()
	{
		\XF::runOnce('mjcCurrencyCacheRebuild', function () {
			$this->getCurrencyRepo()->rebuildCurrencyCache();
		});
	}

	protected function _setupDefaults()
	{
		$this->active = true;
		$this->exchange_rate = 1;
	}


    /**
     * @param $criteria
     * @param string $key
     *
     * @return bool
     */
    public function isCriteriaSelected($criteria, string $key): bool
    {
        return (!is_array($criteria) || !isset($criteria['mjc_credits_currency_' . $this->currency_id .'_' . $key]))
            ? false
            : true;
    }

    /**
     * @param $criteria
     * @param string $key
     *
     * @return int
     */
    public function getCriteriaValue($criteria, string $key): int
    {
        return (!is_array($criteria) || !isset($criteria['mjc_credits_currency_' . $this->currency_id .'_' . $key]))
            ? 0
            : $criteria['mjc_credits_currency_' . $this->currency_id .'_' . $key]['amount'];
    }

    /**
     * @return bool
     * @throws \XF\PrintableException
     */
    public function verifyChargeEvent(): bool
    {
        if (!$this->exists())
        {
            return false;
        }

        // Whether we need to add a charge event
        $foundEvent = false;

        /** @var Event $event */
        foreach ($this->Events as $eventId => $event)
        {
            if ($event->definition_id == 'content')
            {
                $foundEvent = true;
                break;
            }
        }

        if (!$foundEvent)
        {
            /** @var Event $event */
            $event = $this->_em->create('MJ\Credits:Event');
            $event->bulkSet([
                'definition_id' => 'content',
                'currency_id' => $this->currency_id
            ]);
            $event->save();
        }

        return true;
    }


    /**
     * @return bool
     * @throws \XF\PrintableException
     */
    public function verifyAdjustEvent(): bool
    {
        if (!$this->exists())
        {
            return false;
        }

        // 是否需要添加调整事件
        $foundEvent = false;

        /** @var Event $event */
        foreach ($this->Events as $eventId => $event)
        {
            if ($event->definition_id == 'adjust')
            {
                $foundEvent = true;
            }
        }

        if (!$foundEvent)
        {
            $event = $this->_em->create('MJ\Credits:Event');

            $event->definition_id = 'adjust';
            $event->currency_id = $this->currency_id;
            $event->allowed_user_group_ids = [-1];

            $event->save();
        }

        return true;
    }

    /**
     * @param \XF\Entity\User|null $userInfo
     * @param bool $format
     *
     * @return bool|mixed|null
     */
    public function getValueFromUser(\XF\Entity\User $userInfo = null, $format = true)
    {
        /** @var \MJ\Credits\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        $userInfo = $userInfo ?: $visitor;

        $value = $userInfo->{$this->column};

        if ($format)
        {
            // We need to format the value
            $value = $this->getFormattedValue($value);

        }

        return $value;
    }

    /**
     * @param int $value
     *
     * @return mixed
     */
    public function getFormattedValue($value = 0)
    {
        if ($value < 0)
        {
            // Make sure this is displaying correctly
            $value = 0;
        }

        return \XF::language()->numberFormat($value, $this->decimal_places);
    }

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mjc_currency';
		$structure->shortName = 'MJ\Credits:Currency';
		$structure->contentType = 'mjc_currency';
		$structure->primaryKey = 'currency_id';
		$structure->columns = [
			'currency_id'            => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'code'                   => ['type' => self::STR, 'maxLength' => 25, 'default' => ''],

			'prefix'                 => ['type' => self::STR, 'maxLength' => 50, 'default' => '', 'noTrim' => true],
			'suffix'                 => ['type' => self::STR, 'maxLength' => 50, 'default' => '', 'noTrim' => true],
			'decimal_places'         => ['type' => self::UINT, 'default' => 0],

            'positive'               => ['type' => self::UINT, 'default' => 0],
			'exchange_rate'          => ['type' => self::FLOAT, 'required' => true],
            'max_amount'             => ['type' => self::FLOAT, 'default' => 0],
			'display_order'          => ['type' => self::UINT, 'default' => 0],
			'active'                 => ['type' => self::BOOL, 'default' => 1],
            'wallet_popup'           => ['type' => self::BOOL, 'default' => 0],
			'allowed_user_group_ids' => ['type' => self::LIST_COMMA, 'default' => '']
		];
		$structure->getters = [
			'title'       => true,
			'description' => true,
			'column'      => true,
		];
		$structure->relations = [
			'MasterTitle' => [
				'entity'     => 'XF:Phrase',
				'type'       => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'mjc_currency_title.', '$currency_id']
				]
			],
			'MasterDescription' => [
				'entity'     => 'XF:Phrase',
				'type'       => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'mjc_currency_desc.', '$currency_id']
				]
			],

            'Events' => [
                'entity' => 'MJ\Credits:Event',
                'type' => self::TO_MANY,
                'conditions' => 'currency_id',
                'cascadeDelete' => true
            ],
            'Transactions' => [
                'entity' => 'MJ\Credits:Transaction',
                'type' => self::TO_MANY,
                'conditions' => 'currency_id',
                'cascadeDelete' => true
            ]
        ];

		$structure->options = [
			'check_duplicate' => true
		];

		return $structure;
	}

    /**
     * @return \XF\Mvc\Entity\Repository
     */
	protected function getCurrencyRepo()
	{
		return $this->repository('MJ\Credits:Currency');
	}
}
