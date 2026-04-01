<?php

namespace MJ\Credits\Admin\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\FormAction;
use XF\Admin\Controller\AbstractController;

class Transaction extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
        $this->assertAdminPermission('mjcCredits');
		$this->assertAdminPermission('mjcTransaction');
	}

	public function actionIndex()
	{
		$filters = $this->filterSearchConditions();
		$conditions = [];

		$page = $this->filterPage();
		$perPage = 20;

		if ($this->request->exists('delete_transactions'))
		{
			return $this->rerouteController(__CLASS__, 'delete');
		}

		$transactionRepo = $this->getTransactionRepo();
		$transactionFinder = $transactionRepo->findTransactionsForList(false)->limitByPage($page, $perPage);

		if ($username = $filters['username'])
		{
			$user = $this->finder('XF:User')->where('username', $username)->fetchOne();
			if ($user)
			{
				$transactionFinder->where('user_id', $user->user_id);
				$conditions['username'] = $user->username;
			}
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

		if ($filters['transaction_id'])
		{
			$transactionFinder->where('transaction_id', $filters['transaction_id']);
			$conditions['transaction_id'] = $filters['transaction_id'];
		}

		if ($filters['transaction_status'])
		{
			$transactionFinder->where('transaction_status', $filters['transaction_status']);
			$conditions['transaction_status'] = $filters['transaction_status'];
		}

		if ($filters['event_id'])
		{
			$transactionFinder->where('event_id', $filters['event_id']);
			$conditions['event_id'] = $filters['event_id'];
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
		$total = $transactionFinder->total();
		$this->assertValidPage($page, $perPage, $total, 'mjc-credits/transactions');

		$viewParams = [
			'transactions' => $transactionFinder->fetch(),

			'page'         => $page,
			'perPage'      => $perPage,
			'total'        => $total,

			'conditions'   => $conditions,

			'datePresets'  => \XF::language()->getDatePresets()
		];

		return $this->view('MJ\Credits:Transaction\Listing', 'mjc_transaction_list', $viewParams);
	}

	protected function transactionAddEdit(\MJ\Credits\Entity\Transaction $transaction)
	{
		$viewParams = [
			'transaction' => $transaction,
		];
		return $this->view('MJ\Credits:Transaction\Edit', 'mjc_transaction_edit', $viewParams);
	}

	public function actionAdd()
	{
		/** @var \MJ\Credits\Entity\Transaction $transaction */
		$transaction = $this->em()->create('MJ\Credits:Transaction');
		return $this->transactionAddEdit($transaction);
	}

	public function actionEdit(ParameterBag $params)
	{
		$transaction = $this->assertTransactionExists($params->transaction_id);

		return $this->transactionAddEdit($transaction);
	}

	protected function transactionSaveProcess(\MJ\Credits\Entity\Transaction $transaction)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'message'            => 'str',
			'transaction_status' => 'str',
			'is_hidden'          => 'bool',
		]);

		$form->basicEntitySave($transaction, $input);

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		if ($params->transaction_id)
		{
			$transaction = $this->assertTransactionExists($params->transaction_id);
		}
		else
		{
			/** @var \MJ\Credits\Entity\Transaction $transaction */
			$transaction = $this->em()->create('MJ\Credits:Transaction');
		}

		$this->transactionSaveProcess($transaction)->run();

		if ($this->request->exists('exit'))
		{
			$redirect = $this->buildLink('mjc-credits/transactions') . $this->buildLinkHash($transaction->transaction_id);
		}
		else
		{
			$redirect = $this->buildLink('mjc-credits/transactions/edit', $transaction);
		}

		return $this->redirect($redirect);
	}

	public function actionDelete(ParameterBag $params)
	{
		$conditions = $this->filterSearchConditions();
		$transactionIds = $this->filter('transaction_ids', 'array-uint');
		if ($transactionId = $this->filter('transaction_id', 'uint', $params->transaction_id))
		{
			$transactionIds[] = $transactionId;
		}
		$transactionIds = array_unique($transactionIds);
		if(count($transactionIds)==1){
			$transactionId = reset($transactionIds);
			$transaction = $this->assertTransactionExists($transactionId);

			if (!$transaction->preDelete())
			{
				return $this->error($transaction->getErrors());
			}
		}

		if (!$transactionIds)
		{
			return $this->redirect($this->buildLink('mjc-credits/transactions', null, $conditions));
		}

		if ($this->isPost() && !$this->request->exists('delete_transactions'))
		{
			foreach ($transactionIds as $transactionId)
			{
				/** @var \MJ\Credits\Entity\Transaction $transaction */
				$transaction = $this->em()->find('MJ\Credits:Transaction', $transactionId);
				$transaction->delete(false);
			}

			return $this->redirect($this->buildLink('mjc-credits/transactions', null, $conditions));
		}
		else
		{
			$viewParams = [
				'transactionIds' => $transactionIds,
				'conditions'     => $conditions
			];
			if(!empty($transaction)){
				$viewParams['transaction'] = $transaction;
			}
			return $this->view('MJ\Credits:Transaction\Delete', 'mjc_transaction_delete', $viewParams);
		}
	}

	public function actionClear()
	{
		$this->setSectionContext('mjcReset');

		if ($this->isPost())
		{
			$this->repository('MJ\Credits:Transaction')->clearTransactions();
			return $this->redirect($this->buildLink('mjc-credits/reset'));
		}
		else
		{
			return $this->view('MJ\Credits:Credit\Reset', 'mjc_transaction_clear');
		}
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
			'transaction_id'     => 'uint',
		]);
		if($removeEmpty){
			$conditions = array_filter($conditions);
		}
		return $conditions;
	}

	public function actionRefineSearch()
	{
		$conditions = $this->filterSearchConditions();

		$viewParams = [
			'currencies'       => $this->repository('MJ\Credits:Currency')->findCurrenciesForList()->fetch(),
			'eventDefinitions' => $this->repository('MJ\Credits:Event')->getEventDefinitionTitlePairs(true),
			'conditions'       => $conditions,
			'datePresets'      => \XF::language()->getDatePresets()
		];
		return $this->view('XF:Template\RefineSearch', 'mjc_transaction_refine_search', $viewParams);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \MJ\Credits\Entity\Transaction
	 */
	protected function assertTransactionExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('MJ\Credits:Transaction', $id, $with, 'mjc_requested_transaction_not_found');
	}

	/**
	 * @return \MJ\Credits\Repository\Transaction
	 */
	protected function getTransactionRepo()
	{
		return $this->repository('MJ\Credits:Transaction');
	}

	/**
	 * @return \MJ\Credits\Repository\Product
	 */
	protected function getProductRepo()
	{
		return $this->repository('MJ\Credits:Product');
	}
}
