<?php

namespace MJ\Credits\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Transaction extends Entity
{
	public function canView(&$error = null)
	{
		$visitor = \XF::visitor();

		if (!$this->Event)
		{
			return false;
		}

		if (!$visitor->canViewCredits($error))
		{
			return false;
		}

		if($visitor->is_super_admin)
		{
			return true;
		}

		if (!$visitor->user_id || $visitor->user_id != $this->user_id)
		{
			$error = \XF::phraseDeferred('mjc_requested_transaction_not_found');
			return false;
		}

		return true;
	}

	public function getStatusPhrase()
	{
		return \XF::phrase('mjc_transaction_status.' . $this->transaction_status);
	}

	public function getEventHandler()
	{
		$cEvent = $this->app()->container('mjcEvent');
		$handler = $cEvent->handler($this->definition_id);

		return $handler;
	}

	public function getContentLink()
	{
		if($handler = $this->getEventHandler()){
			$handler->getContentLink($this->content_type, $this->content_id);
		}
		return '';
	}

	public function getTitle()
	{
		if($this->Event){
			return $this->Event->title;
		}
		if($this->EventDefinition){
			return $this->EventDefinition->title;
		}
	}

	public function getDescription()
	{
		if($handler = $this->getEventHandler()){
			return $handler->transactionDescription($this);
		}
		return '';
	}

	protected function _setupDefaults()
	{
		$this->active = true;
		$this->send_alert = true;
		$this->moderate_transactions = false;
	}

	protected function _preSave()
	{
		if($this->isUpdate())
		{
			$this->last_update = \XF::$time;
		}
	}

	protected function _postSave()
	{
		if($this->isUpdate() && $this->isChanged('transaction_status') && $this->getOption('update_user_credits'))
		{
			$amount = $this->amount;
			$newStatus = $this->getValue('transaction_status');
			$oldStatus = $this->getExistingValue('transaction_status');

			if(
				(
					($oldStatus == 'pending' && $newStatus == 'completed' && $amount > 0) ||
					($oldStatus == 'reverted' && $newStatus == 'pending' && $amount < 0) ||
					($oldStatus == 'reverted' && $newStatus == 'completed')
				) && !$this->update_user_credit
			){
				$this->updateUserCredit($amount);
				$this->fastUpdate('update_user_credit', 1);
			}else if(
				(
					($oldStatus == 'pending' && $newStatus == 'reverted' && $amount < 0) ||
					($oldStatus == 'completed' && $newStatus == 'pending' && $amount > 0) ||
					($oldStatus == 'completed' && $newStatus == 'reverted')
				) && $this->update_user_credit
			){
				$this->updateUserCredit(-$amount);
				$this->fastUpdate('update_user_credit', 0);
			}
		}
	}

	protected function updateUserCredit($amount)
	{
		$column = $this->repository('MJ\Credits:Currency')->getColumn($this->currency_id);
		$this->db()->query(
			'UPDATE `xf_user` SET `'. $column .'` = `'. $column.'` + ?
			WHERE user_id = ?',
			[$amount, $this->user_id]
		);
	}

	protected function updateUserCredi1($revert = true)
	{
		if(!$revert){
			$bind = [$this->amount, $this->user_id];
		}else{
			$bind = [$this->amount * (-1), $this->user_id];
		}
		$db = $this->db();
		$column = $this->repository('MJ\Credits:Currency')->getColumn($this->currency_id);
		$db->query(
			'UPDATE `xf_user` SET `'. $column .'` = `'. $column.'` - ?
			WHERE user_id = ?',
			$bind
		);
	}

	/**
	 * Post-delete behaviors.
	 */
	protected function _postDelete()
	{
		//mj-todo mjcRefundAfterDeleteTransaction
		if($this->update_user_credit){
			$this->updateUserCredit(-$this->amount);
			//mj-todo update stats by transaction_date
		}

        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');
        $alertRepo->fastDeleteAlertsForContent('mjc_event', $this->transaction_id);
    }

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_mjc_transaction';
		$structure->shortName = 'MJ\Credits:Transaction';
		$structure->contentType = 'mjc_transaction';
		$structure->primaryKey = 'transaction_id';
		$structure->columns = [
			'transaction_id'     => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'transaction_hash'   => ['type' => self::STR, 'maxLength' => 64, 'required' => true],
			'definition_id'      => ['type' => self::STR, 'maxLength' => 50, 'match' => 'alphanumeric', 'required' => true],
			'event_id'           => ['type' => self::UINT, 'required' => true],
			'currency_id'        => ['type' => self::UINT, 'required' => true],
			'trigger_user_id'    => ['type' => self::UINT, 'required' => true],
			'user_id'            => ['type' => self::UINT, 'default' => 0],
            'post_id'            => ['type' => self::UINT, 'nullable' => true, 'default' => 0],
			'amount'             => ['type' => self::FLOAT, 'default' => 0],
			'message'            => ['type' => self::STR, 'default' => ''],
			'content_type'       => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
			'content_id'         => ['type' => self::UINT, 'required' => true],
			'update_user_credit' => ['type' => self::BOOL, 'default' => false],
			'last_update'        => ['type' => self::UINT, 'default' => 0],
			'transaction_status' => ['type' => self::STR, 'default' => 'completed',
				'allowedValues'  => ['pending', 'completed', 'reverted']
			],
			'is_hidden'          => ['type' => self::BOOL, 'default' => false],
			'transaction_date'   => ['type' => self::UINT, 'default' => \XF::$time],
			'extra_data'         => ['type' => self::SERIALIZED_ARRAY, 'default' => []]
		];
		$structure->behaviors = [];

		$structure->getters = [
			'title'        => true,
			'description'  => true,
			'content_link' => true,
		];

		$structure->relations = [
			'User' => [
				'entity'     => 'XF:User',
				'type'       => self::TO_ONE,
				'conditions' => 'user_id',
				'primary'    => true
			],
			'TriggerUser' => [
				'entity'     => 'XF:User',
				'type'       => self::TO_ONE,
				'conditions' =>[['user_id', '=', '$trigger_user_id']],
				'primary'    => true
			],
			'Event' => [
				'entity'     => 'MJ\Credits:Event',
				'type'       => self::TO_ONE,
				'conditions' => 'event_id',
				'primary'    => true
			],
			'EventDefinition' => [
				'entity'     => 'MJ\Credits:EventDefinition',
				'type'       => self::TO_ONE,
				'conditions' => 'definition_id',
				'primary'    => true
			],
			'Currency' => [
				'entity'     => 'MJ\Credits:Currency',
				'type'       => self::TO_ONE,
				'conditions' => 'currency_id',
				'primary'    => true
			],
            'Post' => [
                'entity' => 'XF:Post',
                'type' => self::TO_ONE,
                'conditions' => 'post_id',
                'primary' => true,
                'with' => ['Thread']
            ]
		];

		$structure->options = [
			'update_user_credits' => true
		];

		$structure->defaultWith[] = 'Event';

		return $structure;
	}

	/**
	 * @return \MJ\Credits\Repository\Transaction
	 */
	protected function getTransactionRepo()
	{
		return $this->repository('MJ\Credits:Transaction');
	}
}
