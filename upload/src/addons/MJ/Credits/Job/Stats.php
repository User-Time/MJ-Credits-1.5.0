<?php

namespace MJ\Credits\Job;

class Stats extends \XF\Job\AbstractJob
{
	protected $defaultData = [
		'position' => 0,
		'batch' => 28,
		'delete' => false
	];

	public function run($maxRunTime)
	{
		$db = $this->app->db();

		if ($this->data['position'] == 0)
		{
			// delete old stats cache if required
			if ($this->data['delete'])
			{
				$db->emptyTable('xf_mjc_stats');
			}

			// an appropriate date from which to start... first transaction?
			$this->data['position'] = $db->fetchOne('SELECT MIN(transaction_date) FROM xf_mjc_transaction') ?: \XF::$time;

			// start on a 24 hour increment point
			$this->data['position'] = $this->data['position'] - $this->data['position'] % 86400;
		}
		else if ($this->data['position'] > \XF::$time)
		{
			return $this->complete();
		}

		$end = $this->data['position'] + $this->data['batch'] * 86400;

		/** @var \MJ\Credits\Repository\Stats $statsRepo */
		$statsRepo = $this->app->repository('MJ\Credits:Stats');
		$statsRepo->build($this->data['position'], $end);

		$this->data['position'] = $end;

		return $this->resume();
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('rebuilding');
		$typePhrase = \XF::phrase('mjc_daily_statistics');
		return sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, \XF::language()->date($this->data['position'], 'absolute'));
	}

	public function canCancel()
	{
		return true;
	}

	public function canTriggerByChoice()
	{
		return true;
	}
}