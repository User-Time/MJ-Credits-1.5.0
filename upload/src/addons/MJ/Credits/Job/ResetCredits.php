<?php

namespace MJ\Credits\Job;

class ResetCredits extends \XF\Job\AbstractUserCriteriaJob
{
	protected $defaultData = [
		'resetData' => []
	];

	protected $resetData;

	protected function actionSetup()
	{
		$this->resetData = $this->data['resetData'];
	}

	protected function executeAction(\XF\Entity\User $user)
	{

	}

	public function run($maxRunTime)
	{
		$startTime = microtime(true);
		$em = $this->app->em();

		$ids = $this->prepareUserIds();

		if (!$ids)
		{
			return $this->complete();
		}

		$this->actionSetup();

		$transaction = $this->wrapTransaction();

		$db = $this->app->db();
		if ($transaction)
		{
			$db->beginTransaction();
		}

		if(!empty($this->resetData['reset_credits'])){
			/** @var \MJ\Credits\Repository\Currency $currencyRepo */
			$currencyRepo = $this->app->repository('MJ\Credits:Currency');
			$currencyRepo->resetUserCredits($ids, $this->resetData['currency_id'], $this->resetData['credit_target']);
		}

		if(!empty($this->resetData['reset_transactions'])){
			/** @var \MJ\Credits\Repository\Transaction $transactionRepo */
			$transactionRepo = $this->app->repository('MJ\Credits:Transaction');
			$transactionRepo->deleteTransaction($ids, $this->resetData['currency_id']);
		}

		$this->data['count'] += count($ids);
		$this->data['start'] = end($ids);
		if ($transaction)
		{
			$db->commit();
		}

		return $this->resume();
	}

	protected function getActionDescription()
	{
		$actionPhrase = \XF::phrase('mjc_resetting');
		$typePhrase = \XF::phrase('mjc_credits');

		return sprintf('%s... %s', $actionPhrase, $typePhrase);
	}

	public function canCancel()
	{
		return true;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}