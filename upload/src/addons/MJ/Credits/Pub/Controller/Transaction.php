<?php

namespace MJ\Credits\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Transaction extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		/** @var \MJ\Credits\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewCredits($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}

	public function actionIndex(ParameterBag $params)
	{
		if ($params->transaction_id)
		{
			return $this->rerouteController(__CLASS__, 'view', $params);
		}

		$filters = $this->filterSearchConditions();
		$conditions = [];

		$page = $this->filterPage();
		$perPage = $this->options()->mjcTransactionsPerPage;

		$transactionRepo = $this->getTransactionRepo();
		$transactionFinder = $transactionRepo->findTransactionsForList();

		$visitor = \XF::visitor();

		$canViewOthers = $visitor->hasPermission('mjcCredits', 'viewOthers');
		if($canViewOthers){
			if ($username = $filters['username'])
			{
				$user = $this->finder('XF:User')->where('username', $username)->fetchOne();
				if ($user)
				{
					$transactionFinder->where('user_id', $user->user_id);
					$conditions['username'] = $user->username;
				}
			}
		}else{
			$transactionFinder->where('user_id', $visitor->user_id);
		}

		if ($triggerUsername = $filters['trigger_username'])
		{
			$user = $this->finder('XF:User')->where('username', $triggerUsername)->fetchOne();
			if ($user)
			{
				$transactionFinder->where('trigger_user_id', $user->user_id);
				$conditions['trigger_username'] = $user->username;
			}
		}

		if ($filters['start'])
		{
			$transactionFinder->where('transaction_date', '>', $filters['start']);
			$conditions['start'] = $filters['start'];
		}

		if ($filters['end'])
		{
			$transactionFinder->where('transaction_date', '<', $filters['end']);
			$conditions['end'] = $filters['end'];
		}

		if ($filters['currency_id'])
		{
			$transactionFinder->where('currency_id', $filters['currency_id']);
			$conditions['currency_id'] = $filters['currency_id'];
		}

		if ($filters['transaction_status'])
		{
			$transactionFinder->where('transaction_status', $filters['transaction_status']);
			$conditions['transaction_status'] = $filters['transaction_status'];
		}

		if ($filters['definition_id'])
		{
			$transactionFinder->where('definition_id', $filters['definition_id']);
			$conditions['definition_id'] = $filters['definition_id'];
		}

		if ($order = $filters['order'])
		{
			switch ($order) {
				case 'amount':
				case 'last_update':
					break;

				default:
					$order = 'transaction_date';
					break;
			}
			$direction = $filters['direction'];

			switch ($direction) {
				case 'desc':
				case 'asc':
					break;
				default:
					$direction = 'desc';
					break;
			}
			$transactionFinder->order($order, $direction);

			$conditions['order'] = $order;
			$conditions['direction'] = $direction;
		}

		if ($conditions && $this->isPost())
		{
			return $this->redirect($this->buildLink('mjc-credits/transactions', null, $conditions), '');
		}

		$transactionFinder->limitByPage($page, $perPage);
		$totalTransactions = $transactionFinder->total();

		/** @var \MJ\Credits\Entity\Transaction[] $transactions */
		$transactions = $transactionFinder->fetch();

		$this->assertValidPage($page, $perPage, $totalTransactions, 'mjc-credits/transactions');

		$viewParams = [
			'transactions'     => $transactions,
			'canViewOthers'    => $canViewOthers,

			'page'             => $page,
			'perPage'          => $perPage,
			'total'            => $totalTransactions,

			'conditions'       => $conditions,
			'currencies'       => $this->repository('MJ\Credits:Currency')->findCurrenciesForList()->fetch(),
			'eventDefinitions' => $this->repository('MJ\Credits:Event')->getEventDefinitionTitlePairs(true),
			'pageSelected'     => 'transaction',
		];
		return $this->view('MJ\Credit:Transaction\Index', 'mjc_transaction_list', $viewParams);
	}

	protected function filterSearchConditions($removeEmpty = false)
	{
		$conditions = $this->filter([
			'username'           => 'str',
			'trigger_username'   => 'str',
			'start'              => 'datetime',
			'end'                => 'datetime',
			'currency_id'        => 'uint',
			'transaction_status' => 'str',
			'event_id'           => 'uint',
			'definition_id'      => 'str',
			'order'              => 'str',
			'direction'          => 'str',
		]);
		if($removeEmpty){
			$conditions = array_filter($conditions);
		}
		return $conditions;
	}

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
	public function actionView(ParameterBag $params)
	{
		$transaction = $this->assertViewableTransaction($params->transaction_id);

		$viewParams = [
			'transaction'  => $transaction,
			'pageSelected' => 'transaction',
		];
		return $this->view('MJ\Credits:Transaction\View', 'mjc_transaction_view', $viewParams);
	}

	/**
	 * @param $transactionId
	 * @param array $extraWith
	 *
	 * @return \MJ\Credits\Entity\Transaction
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableTransaction($transactionId, array $extraWith = [])
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'User';
		$extraWith[] = 'TriggerUser';
		$extraWith[] = 'Event';
		$extraWith[] = 'Currency';

		/** @var \MJ\Credits\Entity\Transaction $transaction */
		$transaction = $this->em()->find('MJ\Credits:Transaction', $transactionId, $extraWith);
		if (!$transaction)
		{
			throw $this->exception($this->notFound(\XF::phrase('mjc_requested_transaction_not_found')));
		}

		if (!$transaction->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $transaction;
	}

    /**
     * @return \XF\Mvc\Entity\Repository
     */
	protected function getTransactionRepo()
	{
		return $this->repository('MJ\Credits:Transaction');
	}
}
