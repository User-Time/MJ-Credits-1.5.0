<?php

namespace MJ\Credits\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Stats extends Repository
{
	public function getTotal($currencies)
	{
		$db = $this->db();

		$sumRows = [];
		foreach($currencies as $currency){
			$sumRows[] = 'SUM(`'. $currency['column'] .'`) AS mjc_total_'. $currency['currency_id'];
		}
		$sumRows = implode(' , ', $sumRows);
		return $db->fetchRow(
			'SELECT ' . $sumRows .
			' FROM xf_user'
		);
	}

	public function getFirstTransactionDate()
	{
		return $this->db()->fetchOne('
			SELECT transaction_date
			FROM xf_mjc_transaction
			ORDER BY transaction_date ASC
		');
	}

	public function getLastTransactionDate()
	{
		return $this->db()->fetchOne('
			SELECT transaction_date
			FROM xf_mjc_transaction
			ORDER BY transaction_date DESC
		');
	}

	public function build($start, $end)
	{
		$db = $this->db();
		$db->beginTransaction();

		$eventDefinitions = $this->app()->container('mjc.eventDefinition');
		$currencies = $this->app()->container('mjc.currencies');

		$statsData = [];

		foreach ($eventDefinitions as $definitionId => $handler)
		{
			$db = $this->db();
			$statsData[$definitionId] = [];
			foreach ($currencies as $currencyId => $currency) {
				$statsData[$definitionId][$currencyId] = [];
				$earnStats = $db->fetchPairs(
					$this->getBasicDataQuery(true),
					[$start, $end, $definitionId, $currencyId]
				);
				foreach($earnStats as $date => $earn){
					$statsData[$definitionId][$currencyId][$date]['earn'] = $earn;
				}
				$spendStats = $db->fetchPairs(
					$this->getBasicDataQuery(false),
					[$start, $end, $definitionId, $currencyId]
				);
				foreach($spendStats as $date => $spend){
					$statsData[$definitionId][$currencyId][$date]['spend'] = $spend;
				}
			}
		}

		foreach ($statsData as $definitionId => $dateData)
		{
			foreach ($dateData as $currencyId => $records)
			{
				foreach ($records as $date => $counter)
				{
					$counter['earn'] = !empty($counter['earn']) ? $counter['earn'] : 0;
					$counter['spend'] = !empty($counter['spend']) ? $counter['spend'] : 0;

					$db->insert('xf_mjc_stats', [
						'stats_date' => $date,
						'stats_type' => $definitionId,
						'currency_id' => $currencyId,
						'earn' => $counter['earn'],
						'spend' => $counter['spend'],
					], false, 'earn = ' . $counter['earn'] . ', spend = ' . $counter['spend']);
				}
			}
		}

		$db->commit();
	}

	protected function getBasicDataQuery($earn)
	{
		return '
			SELECT
				transaction_date - transaction_date % 86400 AS unixDate,
				SUM(amount)
			FROM xf_mjc_transaction
			WHERE transaction_date BETWEEN ? AND ?
				AND definition_id = ? AND currency_id = ? AND amount '. ($earn ? '> 0' : ' < 0'). '
			GROUP BY unixDate
		';
	}
}
