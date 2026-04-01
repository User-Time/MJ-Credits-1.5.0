<?php

namespace MJ\Credits\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Import extends Repository
{
	public function getAdCreditCurrencies()
	{
		$db = $this->db();
		if($db->getSchemaManager()->tableExists('adcredit_currency')){
			return $db->fetchAllKeyed('
				SELECT *
				FROM adcredit_currency
				ORDER BY display_order
			', 'currency_id');
		}
		return [];
	}

	public function getDbTechCreditCurrencies()
	{
		$db = $this->db();
		if($db->getSchemaManager()->tableExists('xf_dbtech_credits_currency')){
			return $db->fetchAllKeyed('
				SELECT *
				FROM xf_dbtech_credits_currency
				ORDER BY display_order
			', 'currencyid');
		}
		return [];
	}

	public function getBrCreditCurrencies()
	{
		$db = $this->db();
		if($db->getSchemaManager()->tableExists('xf_brivium_credits_currency')){
			return $db->fetchAllKeyed('
				SELECT *
				FROM xf_brivium_credits_currency
				ORDER BY display_order
			', 'currency_id');
		}
		return [];
	}

	/**
	 * @return Finder
	 */
	public function findCurrenciesForList()
	{
		return $this->finder('MJ\Credits:Currency')->order(['display_order']);
	}

	public function getCurrencyCache()
	{
		$output = [];

		$currencies = $this->finder('MJ\Credits:Currency')
			->order('display_order');

		foreach ($currencies->fetch() as $key => $item)
		{
			$output[$key] = $item->toArray() + [
				'column' => $this->getColumn($item->currency_id)
			];
		}

		return $output;
	}
}
