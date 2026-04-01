<?php

namespace MJ\Credits\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Transaction extends Repository
{
	public function findTransactionsForList($visibility = true)
	{
		$finder = $this->finder('MJ\Credits:Transaction')
			->with('User', 'TriggerUser')
			->setDefaultOrder([['transaction_date', 'DESC'], ['transaction_id', 'DESC']]);

		if ($visibility)
		{
			$finder->applyGlobalVisibilityChecks();
		}
		return $finder;
	}

	public function findTransactionsForUser($user, $visibility = true)
	{
		$finder = $this->finder('MJ\Credits:Transaction')
			->forUser($user)
			->with('User', 'TriggerUser')
			->setDefaultOrder([['transaction_date', 'DESC'], ['transaction_id', 'DESC']]);
		if ($visibility)
		{
			$finder->applyGlobalVisibilityChecks();
		}
		return $finder;
	}

	public function deleteTransaction($userId = 0, $currencyId = 0)
	{
		$db = $this->db();
		$where = '1 = 1';

		if($userId){
			if(is_array($userId)){
				$where .= ' AND user_id IN(' . $db->quote($userId) . ')';
			}else{
				$where .= ' AND user_id = ' . $db->quote($userId);
			}
		}
		if($currencyId){
			if(is_array($currencyId)){
				$where .= ' AND currency_id IN(' . $db->quote($currencyId) . ')';
			}else{
				$where .= ' AND currency_id = ' . $db->quote($currencyId);
			}
		}
		$db->delete('xf_mjc_transaction', $where);
	}

	public function clearTransactions()
	{
		$this->db()->query('TRUNCATE `xf_mjc_transaction`');
	}
}
