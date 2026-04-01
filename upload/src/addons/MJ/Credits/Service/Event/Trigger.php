<?php

namespace MJ\Credits\Service\Event;

use MJ\Credits\Entity\Transaction;
use MJ\Credits\Entity\Event;
use MJ\Credits\Util\Money;
use XF\App;

class Trigger extends \XF\Service\AbstractService
{
	/**
	 * @var Event
	 */
	protected $event;
    /**
     * @var App
     */
    protected $app;

	protected $quickTrigger;

	protected $defaultParams = [
		'target_user'            => null,
		'trigger_user'           => null,
		'trigger_user_id'        => false,

		'currency_id'            => 0,
		'event_id'               => 0,
		'event'                  => null,

		'amount'                 => 0,
		'multiple'               => 0,
		'message'                => '',

		'content_type'           => '',
		'content_id'             => 0,

		'node_id'                => 0,
        'post_id'                => false,

		'bypass_permission'      => false,
		'pending_transaction'    => false,
		'update_user_credit'     => true,
		'ignore_event_privilege' => false,
		'send_alert'             => true,

		'trigger_once'           => false,

		'transaction_extra_data' => [],
		'alert_extra_data'       => [],
		'extra_data'             => [],
	];

	protected $transactions = [];
	protected $alerts = [];
	protected $updateUsers = [];

	protected $validationComplete = false;
	protected $_errors = [];

	public function __construct(\XF\App $app, $quickTrigger = true)
	{
		parent::__construct($app);
        $this->app = $app;
		$this->quickTrigger = $quickTrigger;
	}

	public function printErrors()
	{
		if ($this->_errors)
		{
			throw new \XF\PrintableException($this->_errors);
		}
	}

	public function getErrors()
	{
		return $this->_errors;
	}

    /**
     * [triggerEvent description]
     * @param string $definitionId
     * @param int $targetUserId
     * @param array $params
     * @param string &$error
     * @return array|bool
     * @throws \XF\PrintableException
     */
	public function triggerEvent($definitionId, $targetUserId, $params = [], &$error = null)
	{
		$params = array_merge($this->defaultParams, $params);

		$cEvent = $this->app->container('mjcEvent');
		$eventDefinitions = $this->app->container('mjc.eventDefinition');
		if(empty($eventDefinitions[$definitionId])){
			$error = \XF::phrase('mjc_no_event_definition_exists_with_definition_id_of_x', ['identifier' => $definitionId]);
			return false;
		}
		$definition = $eventDefinitions[$definitionId];

		$handler = $cEvent->handler($definition['definition_id']);
		if(!$handler){
			$error = \XF::phrase('mjc_no_event_handler_for_x', ['identifier' => $definitionId]);
			return false;
		}

		if(!isset($params['currency_checked'])){
			$params['currency_checked'] = false;
		}

		$currencies = $this->app->container('mjc.currencies');

		$currency = [];

		if(!empty($params['currency'])){
			$currency = $params['currency'];
		}else if(!empty($params['currency_id'])){
			if(empty($currencies[$params['currency_id']])){
				$error = \XF::phrase('mjc_requested_currency_not_found');
				return false;
			}
			$currency = $currencies[$params['currency_id']];
		}

		if(!empty($params['event']) && isset($params['event']['event_id'])){
			$events = [$params['event']['event_id'] => $params['event']];
		}else{
			$options = [];
			if(!empty($params['event_id'])){
				$options['event_id'] = $params['event_id'];
			}
			else if($currency)
			{
				$options['currency_id'] = $currency['currency_id'];
			}

			$events = $cEvent->getEvents($definitionId, $options);
			if(!$events){
				$error = \XF::phrase('mjc_no_events_defined_with_x', ['event' => $definitionId]);
				return false;
			}
		}

		$visitor = \XF::visitor();

		if(!empty($params['target_user']) && $params['target_user'] instanceof \XF\Entity\User){
			$targetUser = $params['target_user'];
		}else{
			if(!$targetUserId || $targetUserId == $visitor->user_id){
				$targetUser = $visitor;
			}else{
				$targetUser = $this->em()->find('XF:User', $targetUserId, ['Option']);
			}
		}
		if(!$targetUser){
			$error = \XF::phrase('mjc_requested_target_user_not_found');
			return false;
		}

		if(!$targetUser->hasPermission('mjcCredits', 'useCredits')){
			if(!$params['bypass_permission']){
				$error = \XF::phrase('mjc_x_do_not_have_permission_to_use_credits', ['user' => $targetUser->username]);
				return false;
			}
			$params['send_alert'] = false;
		}
		$currencyRepo = $this->repository('MJ\Credits:Currency');
		if($currency){
			if(!$currencyRepo->validateCurrency($currency, $targetUser, $error)){
				return false;
			}
			$currencyChecked = true;
		}

		$triggerUser = false;
		if(!empty($params['trigger_user']) && $params['trigger_user'] instanceof \XF\Entity\User){
			$triggerUser = $params['trigger_user'];
		}else{
			if(!empty($params['trigger_user_id'])){
				if($params['trigger_user_id'] == $visitor->user_id){
					$triggerUser = $visitor;
				}else if($params['trigger_user_id'] == $targetUser->user_id){
					$triggerUser = $targetUser;
				}else{
					$triggerUser = $this->em()->find('XF:User', $params['trigger_user_id'], ['Option']);
				}
			}
		}
		if(!$triggerUser){
			$triggerUser = $visitor;
		}
		$params['target_user'] = $targetUser;
		$params['trigger_user'] = $triggerUser;

		if($params['trigger_user_id'] === false){
			$params['trigger_user_id'] = $triggerUser->user_id;
		}
		$triggered = [];

		$params['alert_extra_data'] = $params['extra_data'] + $params['alert_extra_data'];
		$params['transaction_extra_data'] = $params['extra_data'] + $params['transaction_extra_data'];

		foreach($events as $event)
		{
			if(!$currency || $event['currency_id'] != $currency['currency_id'])
			{
				if(empty($currencies[$event['currency_id']])){
					$error = \XF::phrase('mjc_requested_currency_not_found');
					$this->_errors[$event['event_id']] = $error;
					continue;
				}
				$currency = $currencies[$event['currency_id']];
				if(!$currencyRepo->validateCurrency($currency, $targetUser, $error)){
					$this->_errors[$event['event_id']] = $error;
					continue;
				}
			}
			$params['currency'] = $currency;
			$transactionHash = $handler->trigger($this, $event, $params, $error);
			if(!$transactionHash){
				$this->_errors[$event['event_id']] = $error;
				continue;
			}
			$triggered[$event['event_id']] = $transactionHash;
			if(!empty($params['trigger_once'])){
				break;
			}

            if($event['amount'] < 0) {
                $positive = abs($event['amount']);

                if ($positive > $visitor->get($currency['column'])) {
                    throw new \XF\PrintableException(\XF::phrase('mjc_not_enough_x_unable_to_action', [
                        'amount' => Money::formatAmount($positive, $currency['currency_id']),
                        'title' => \XF::phrase($event['title_phrase'])
                    ]));
                }
            }
		}

		if(!$triggered){
			return false;
		}
		if($this->quickTrigger){
			$this->fire();
		}
		return $triggered;
	}

	public function addTriggerData($event, $params, $amount)
	{
		$params['update_user_credit'] = $params['update_user_credit'] && (!$params['pending_transaction'] || $amount < 0);

		$transactionHash = $this->queueTransaction($event, $params, $amount);

		$params['transaction_hash'] = $transactionHash;
		if(!empty($params['send_alert']) && !empty($event['send_alert'])){
			$this->queueAlert($event, $params, $amount);
		}
		if($params['update_user_credit']){
			$this->queueUpdateUser($event, $params, $amount);
		}
		return $transactionHash;
	}

	public function queueTransaction($event, $params, $amount)
	{
		$transaction = [
			'definition_id'       => $event['definition_id'],
			'event_id'            => $event['event_id'],
			'currency_id'         => $event['currency_id'],
			'user_id'             => $params['target_user']->user_id,
            'post_id'             => $params['post_id'],
			'trigger_user_id'     => $params['trigger_user_id'],
			'amount'              => $amount,
			'message'             => $params['message'],
			'content_type'        => $params['content_type'],
			'content_id'          => $params['content_id'],
			'update_user_credit'  => $params['update_user_credit'],
			'transaction_status'  => $params['pending_transaction'] ? 'pending' : 'completed',
			'transaction_date'    => \XF::$time,
			'extra_data'          => $params['transaction_extra_data'],
		];

		$transaction['extra_data']['uniqid'] = \XF\Util\Random::getRandomString(18);
		$transaction['extra_data'] = \XF\Util\Php::safeSerialize($transaction['extra_data']);
		$transactionHash = \XF\Util\Hash::hashText(\XF\Util\Php::safeSerialize($transaction), 'sha256');

		$transaction['transaction_hash'] = $transactionHash;
		$this->transactions[] = $transaction;

		return $transactionHash;
	}

	public function queueAlert($event, $params, $amount)
	{
		$params['alert_extra_data']['currency_id']         = $event['currency_id'];
		$params['alert_extra_data']['amount']              = $amount;
		$params['alert_extra_data']['update_user_credit']  = $params['update_user_credit'];
		$params['alert_extra_data']['definition_id']       = $event['definition_id'];
		$params['alert_extra_data']['transaction_hash']    = $params['transaction_hash'];

		$alert = [
			'receiver'    => $params['target_user'],
			'senderId'    => $params['trigger_user']->user_id,
			'senderName'  => $params['trigger_user']->username,
			'contentType' => 'mjc_event',
			'contentId'   => $event['event_id'],
			'action'      => $event['definition_id'],
			'extra'       => $params['alert_extra_data'],
		];
		$this->alerts[] = $alert;
	}

	public function queueUpdateUser($event, $params, $amount)
	{
		$userId = $params['target_user']->user_id;
		if(!isset($this->updateUsers[$userId])){
			$this->updateUsers[$userId] = [];
		}
		if(empty($this->updateUsers[$userId][$params['currency']['column']])){
			$this->updateUsers[$userId][$params['currency']['column']] = 0;
		}
		$this->updateUsers[$userId][$params['currency']['column']] += $amount;
	}


	protected function _fire($newTransaction = true, $cleanError = true)
	{
		if(!$this->transactions){
			return false;
		}
		if($cleanError){
			$this->resetValidation();
		}

		$db = $this->db();

		if ($newTransaction)
		{
			$db->beginTransaction();
		}
		try
		{
			$db->insertBulk('xf_mjc_transaction', $this->transactions);

			if($this->updateUsers){
				foreach($this->updateUsers as $userId => $cols){
					if (!$cols)
					{
						continue;
					}
					$sqlValues = [];
					$bind = [];

					foreach ($cols as $col => $value)
					{
						$bind[] = $value;
						$sqlValues[] = "`$col` = `$col` + ?";
					}
					$db->query(
						"UPDATE `xf_user` SET " . implode(', ', $sqlValues)
						. ' WHERE user_id = ' . $db->quote($userId),
						$bind
					);
				}
			}

			if($this->alerts){
				$alertRepo = $this->repository('XF:UserAlert');
				foreach($this->alerts as $alert){
					$alertRepo->alert(
						$alert['receiver'],
						$alert['senderId']?$alert['senderId']:0,
						$alert['senderName']?$alert['senderName']:'',
						$alert['contentType'],
						$alert['contentId'],
						$alert['action'],
						$alert['extra']
					);
				}
			}
		}
		catch (\Exception $e)
		{
			if ($newTransaction)
			{
				$db->rollback();
			}

			throw $e;
		}

		if ($newTransaction)
		{
			$db->commit();
		}

		return true;
	}

	public function fire($newTransaction = true, $cleanError = true)
	{
		return $this->_fire($newTransaction, $cleanError);
	}

	public function resetValidation()
	{
		$this->validationComplete = false;
		$this->_errors = [];
	}
}
