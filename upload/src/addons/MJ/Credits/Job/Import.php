<?php

namespace MJ\Credits\Job;

class Import extends \XF\Job\AbstractJob
{
	protected $defaultData = [
		'importer'   => null,
		'count'      => 0,
		'importData' => [],
		'start'      => 0,
		'batch'      => 500
	];

	public function run($maxRunTime)
	{
		$startTime = microtime(true);
		if (!$this->data['importer'] || !$this->data['importData'])
		{
			throw new \InvalidArgumentException('Cannot import credits without importer and importData.');
		}

		$db = $this->app->db();
		$users = $db->fetchAllKeyed($db->limit(
			"
				SELECT *
				FROM xf_user
				WHERE user_id > ?
				ORDER BY user_id
			", $this->data['batch']
		), 'user_id', $this->data['start']);

		if (!$users)
		{
			return $this->complete();
		}

		$importData = $this->data['importData'];

		$done = 0;

		foreach($importData as $data)
		{
			if(!empty($data['query'])){
				$db->query($data['query']);
				continue;
			}

			$field = [];
			if(empty($data['from']) ||
				empty($data['to']) ||
				!isset($data['to']) ||
				!isset($data['from'])
			){
				continue;
			}

			if($data['import_type'] == 'merge'){
				$field[] = '`'. $data['to'] . '` = `' . $data['from'] . '` + `' . $data['to'] . '`';
			}else{
				$field[] = '`'. $data['to'] . '` = `' . $data['from'] . '`';
			}
			if(!empty($data['remove_source'])){
				$field[] = '`'. $data['from'] . '` = 0';
			}
			$fields = implode(', ', $field);
			$db->query(
				'UPDATE `xf_user`
				SET ' . $fields
			);
		}

		return $this->complete();
		/*foreach ($users as $user)
		{
			$this->data['start'] = $user->user_id;
			foreach($importData as $data){

				if(empty($data['from']) ||
					empty($data['to']) ||
					!$user->offsetExists($data['from']) ||
					!$user->offsetExists($data['to'])){
					continue;
				}

				if($data['import_type'] == 'merge'){
					$user->set($data['to'], ($user->get($data['from']) + $user->get($data['to'])));
				}else{
					$user->set($data['to'], $user->get($data['from']));
				}
				if(!empty($data['remove_source'])){
					$user->set($data['from'], 0);
				}
				$user->save();
			}

			$done++;

			if (microtime(true) - $startTime >= $maxRunTime)
			{
				break;
			}
		}*/

		$this->data['batch'] = $this->calculateOptimalBatch($this->data['batch'], $done, $startTime, $maxRunTime, 1000);

		return $this->resume();
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('importing');
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