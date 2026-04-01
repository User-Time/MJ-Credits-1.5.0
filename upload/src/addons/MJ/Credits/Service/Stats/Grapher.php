<?php

namespace MJ\Credits\Service\Stats;

class Grapher extends \XF\Service\AbstractService
{
	protected $start;
	protected $end;
	protected $types;
	protected $currencyIds;

	public function __construct(\XF\App $app, $start, $end, array $currencyIds = [])
	{
		parent::__construct($app);

		$this->setDateRange($start, $end);
		$this->currencyIds = $currencyIds;
	}

	public function addCurrencyId($currencyId)
	{
		$this->currencyIds[] = $currencyId;
	}

	public function addType($type)
	{
		$this->types[] = $type;
	}

	public function setDateRange($start, $end)
	{
		$start = intval($start);
		$start -= $start % 86400; // make sure we always get the start of the day
		$end = intval($end);

		if ($end < $start)
		{
			$end = $start;
		}

		$this->start = $start;
		$this->end = $end;
	}

	protected function getRawData($groupType)
	{
		if (!$this->currencyIds)
		{
			throw new \LogicException("Must have at least one type selected");
		}

		$output = [];
		$db = $this->db();
		$column = 'earn';
		if($groupType == 'earn'){
			$column = 'spend';
		}
		$stats = $db->query('
			SELECT stats_date, currency_id, `'. $column .'` as counter
			FROM xf_mjc_stats
			WHERE stats_date BETWEEN ? AND ?
				AND currency_id IN (' . $db->quote($this->currencyIds) . ')
			ORDER BY stats_date
		', [$this->start, $this->end]);

		while ($stat = $stats->fetch())
		{
			if(!isset($output[$stat['stats_date']])){
				$output[$stat['stats_date']] = [];
			}
			if(!isset($output[$stat['stats_date']][$stat['currency_id']])){
				$output[$stat['stats_date']][$stat['currency_id']] = 0;
			}
			if($stat['counter'] < 0){
				$stat['counter'] *= -1;
			}
			$output[$stat['stats_date']][$stat['currency_id']] += $stat['counter'];
		}

		return $output;
	}

	public function getGroupedData(\XF\Stats\Grouper\AbstractGrouper $grouper, $groupType)
	{
		$baseValues = [];
		foreach ($this->currencyIds as $currencyId)
		{
			$baseValues[$currencyId] = 0;
		}

		$groupings = [];
		foreach ($grouper->getGroupingsInRange($this->start, $this->end) as $k => $grouping)
		{
			$grouping['count'] = 0;
			$grouping['values'] = $baseValues;
			$grouping['averages'] = $baseValues;
			$groupings[$k] = $grouping;
		}

		$rawData = $this->getRawData($groupType);

		foreach ($rawData as $timestamp => $typeValues)
		{
			$groupValue = $grouper->getGrouping($timestamp);
			if (!isset($groupings[$groupValue]))
			{
				throw new \LogicException("Grouping $groupValue not found. This should have been created internally. Report as a bug.");
			}

			$groupings[$groupValue]['count']++;

			foreach ($typeValues as $type => $value)
			{
				if (isset($groupings[$groupValue]['values'][$type]))
				{
					$groupings[$groupValue]['values'][$type] += $value;
				}
				else
				{
					$groupings[$groupValue]['values'][$type] = $value;
				}
			}
		}

		foreach ($groupings as $key => $grouping)
		{
			foreach ($grouping['values'] as $type => $value)
			{
				$average = $value / $grouping['days'];
				if ($grouping['days'] > 1)
				{
					$average = round($average, 2);
				}

				$groupings[$key]['averages'][$type] = $average;
			}
		}

		return $groupings;
	}

	public function getGroupedData1(\XF\Stats\Grouper\AbstractGrouper $grouper)
	{
		$baseValues = [];
		foreach ($this->currencyIds as $currencyId)
		{
			$baseValues[$currencyId] = [
				'spend' => 0,
				'earn' => 0,
			];
		}

		$groupings = [];
		foreach ($grouper->getGroupingsInRange($this->start, $this->end) as $k => $grouping)
		{
			$grouping['count'] = 0;
			$grouping['values'] = $baseValues;
			$grouping['averages'] = $baseValues;
			$groupings[$k] = $grouping;
		}

		$rawData = $this->getRawData();

		foreach ($rawData as $timestamp => $typeValues)
		{
			$groupValue = $grouper->getGrouping($timestamp);
			if (!isset($groupings[$groupValue]))
			{
				throw new \LogicException("Grouping $groupValue not found. This should have been created internally. Report as a bug.");
			}

			$groupings[$groupValue]['count']++;

			foreach ($typeValues as $currencyId => $value)
			{
				if (isset($groupings[$groupValue]['values'][$currencyId]))
				{
					$groupings[$groupValue]['values'][$currencyId]['spend'] += $value['spend'];
					$groupings[$groupValue]['values'][$currencyId]['earn'] += $value['earn'];
				}
				else
				{
					$groupings[$groupValue]['values'][$currencyId]['spend'] = $value['spend'];
					$groupings[$groupValue]['values'][$currencyId]['earn'] = $value['earn'];
				}
			}
		}

		foreach ($groupings as $key => $grouping)
		{
			foreach ($grouping['values'] as $currencyId => $value)
			{
				$averageEarn = $value['earn'] / $grouping['days'];
				if ($grouping['days'] > 1)
				{
					$averageEarn = round($averageEarn, 2);
				}
				$averageSpend = $value['spend'] / $grouping['days'];
				if ($grouping['days'] > 1)
				{
					$averageSpend = round($averageSpend, 2);
				}

				$groupings[$key]['averages'][$currencyId]['earn'] = $averageEarn;
				$groupings[$key]['averages'][$currencyId]['spend'] = $averageSpend;
			}
		}

		return $groupings;
	}
}
