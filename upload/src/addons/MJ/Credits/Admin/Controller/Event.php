<?php

namespace MJ\Credits\Admin\Controller;

use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Admin\Controller\AbstractController;
use MJ\Credits\Entity\EventDefinition;

class Event extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
        $this->assertAdminPermission('mjcCredits');
		if (preg_match('/^(definition)/i', $action))
		{
			$this->assertDebugMode();
		}
		else
		{
			$this->assertAdminPermission('mjcEvent');
		}
	}

	public function actionIndex()
	{
		$currencyId = $this->filter('currency_id', 'uint');
		$currencies = $this->getCurrencyRepo()->findCurrenciesForList()->fetch();

		if(!empty($currencies[$currencyId])){
			$currency = $currencies[$currencyId];
		}else{
			$currency = $currencies->first();
		}
		$events = [];
		if($currency){
			$events = $this->getEventRepo()->findEventsOfCurrency($currency->currency_id)->fetch();
		}
		$viewParams = [
			'events'          => $events,
			'currentCurrency' => $currency,
			'currencies'      => $currencies
		];
		return $this->view('MJ\Credits:Event\Listing', 'mjc_event_list', $viewParams);
	}

	public function actionUpdate()
	{
		$currencyId = $this->filter('currency_id', 'uint');
		$updateEvents = $this->filter('events', 'array');

		if(!empty($updateEvents)){
			$events = $this->finder('MJ\Credits:Event')
				->where('event_id', array_keys($updateEvents))
				->fetch();

			foreach($updateEvents as $eventId => $updateEvent){
				$event = $events->offsetGet($eventId);
				if(!empty($updateEvent['active'])){
					$event->active = 1;
				}else{
					$event->active = 0;
				}
				if(!empty($updateEvent['send_alert'])){
					$event->send_alert = 1;
				}else{
					$event->send_alert = 0;
				}
				if(!empty($updateEvent['moderate_transactions'])){
					$event->moderate_transactions = 1;
				}else{
					$event->moderate_transactions = 0;
				}
				if(isset($updateEvent['amount'])){
					$event->amount = strval(floatval($updateEvent['amount'])) + 0;
				}
				$event->setOption('rebuild_cache', false);
				$event->saveIfChanged($saved);
			}
		}
		\XF::runOnce('mjcEventCacheRebuild', function () {
			$this->getEventRepo()->rebuildEventCache();
		});

		return $this->redirect($this->buildLink('mjc-credits/events', null, ['currency_id' => $currencyId]));
	}

	protected function eventAddEdit(\MJ\Credits\Entity\Event $event)
	{
		if(!$event->handler){
			return $this->error(\XF::phrase('mjc_no_event_definition_exists_with_definition_id_of_x', [
				'identifier' => $event->definition_id
			]));
		}
		$viewParams = [
			'event'           => $event,
			'handler'         => $event->handler,
			'eventDefinition' => $event->EventDefinition,
		];
		return $event->handler->getEventAddEditReply($this, $event, $viewParams, 'mjc_event_edit', 'MJ\Credits:Event\Edit');
	}

	public function actionAdd()
	{
		$definitionId = $this->filter('definition_id', 'str');
		$currencyId = $this->filter('currency_id', 'uint');
		if (!$definitionId)
		{
			if (!$this->isPost())
			{
				$eventRepo = $this->getEventRepo();
				$eventDefinitions = $eventRepo->getEventDefinitionTitlePairs(true);

				if(count($eventDefinitions) == 1){
					return $this->redirect($this->buildLink('mjc-credits/events/add', [], [
						'definition_id' => key($eventDefinitions),
						'currency_id'   => $currencyId
					]));
				}
				$viewParams = [
					'eventDefinitions' => $eventDefinitions,
					'currencyId'   => $currencyId
				];
				return $this->view('MJ\Credits:Event\Add', 'mjc_event_definition_chooser', $viewParams);
			}
		}
		if ($this->isPost())
		{
			if ($definitionId)
			{
				return $this->redirect($this->buildLink('mjc-credits/events/add', [], [
					'definition_id' => $definitionId,
					'currency_id'   => $currencyId
				]));
			}
			else
			{
				return $this->error(\XF::phrase('mjc_you_must_select_event_definition_to_use_for_your_new_event'));
			}
		}
		/** @var \MJ\Credits\Entity\Event $event */
		$event = $this->em()->create('MJ\Credits:Event');
		$event->definition_id = $definitionId;
		$event->currency_id = $currencyId;

		return $this->eventAddEdit($event);
	}

	public function actionEdit(ParameterBag $params)
	{
		$event = $this->assertEventExists($params->event_id);
		return $this->eventAddEdit($event);
	}

	protected function eventSaveProcess(\MJ\Credits\Entity\Event $event)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'amount'                => 'num',
			'moderate_transactions' => 'bool',
			'send_alert'            => 'bool',
			'active'                => 'bool',
		]);

		$usableUserGroups = $this->filter('usable_user_group', 'str');
		if ($usableUserGroups == 'all')
		{
			$input['allowed_user_group_ids'] = [-1];
		}
		else
		{
			$input['allowed_user_group_ids'] = $this->filter('usable_user_group_ids', 'array-uint');
		}

		$form->validate(function(FormAction $form) use ($event)
		{
			$options = $this->filter('options', 'array');
			$request = new \XF\Http\Request($this->app->inputFilterer(), $options, [], []);
			$handler = $event->getHandler();
			if ($handler && !$handler->verifyOptions($request, $options, $errors))
			{
				$form->logErrors($errors);
			}
			$event->options = $options;
		});

		$form->basicEntitySave($event, $input);

		$phraseInput = $this->filter([
			'title' => 'str',
			'description' => 'str'
		]);
		$form->apply(function() use ($phraseInput, $event)
		{
			$title = $event->getMasterPhrase(true);
			$title->phrase_text = $phraseInput['title'];
			$title->save();

			$description = $event->getMasterPhrase(false);
			$description->phrase_text = $phraseInput['description'];
			$description->save();
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		if ($params->event_id)
		{
			$event = $this->assertEventExists($params->event_id);
		}
		else
		{
			/** @var \MJ\Credits\Entity\Event $event */
			$event = $this->em()->create('MJ\Credits:Event');
			$definitionId = $this->filter('definition_id', 'str');
			$currencyId = $this->filter('currency_id', 'uint');

			$event->currency_id = $currencyId;
			$event->definition_id = $definitionId;
		}

		$this->eventSaveProcess($event)->run();

		return $this->redirect($this->buildLink('mjc-credits/events', null, ['currency_id' => $event->currency_id]) . $this->buildLinkHash($event->event_id));
	}

	public function actionBulkDelete(ParameterBag $params)
	{
		$currencyId = $this->filter('currency_id', 'uint');
		$currencies = $this->getCurrencyRepo()->findCurrenciesForList()->fetch();
		if ($this->request->exists('delete_events'))
		{
			return $this->rerouteController(__CLASS__, 'delete');
		}
		if(!empty($currencies[$currencyId])){
			$currency = $currencies[$currencyId];
		}else{
			$currency = $currencies->first();
		}
		$events = [];
		if($currency){
			$events = $this->getEventRepo()->findEventsOfCurrency($currency->currency_id)->fetch();
		}
		$viewParams = [
			'events'          => $events,
			'currentCurrency' => $currency,
			'currencies'      => $currencies
		];
		return $this->view('MJ\Credits:Event\Listing', 'mjc_event_bulk_delete', $viewParams);
	}

	public function actionDelete(ParameterBag $params)
	{
		$eventIds = $this->filter('event_ids', 'array-uint');
		if ($eventId = $this->filter('event_id', 'uint', $params->event_id))
		{
			$eventIds[] = $eventId;
		}
		$eventIds = array_unique($eventIds);
		if(count($eventIds)==1){
			$eventId = reset($eventIds);
			$event = $this->assertEventExists($eventId);

			if (!$event->preDelete())
			{
				return $this->error($event->getErrors());
			}
		}

		if (!$eventIds)
		{
			return $this->redirect($this->buildLink('mjc-credits/events'));
		}

		if ($this->isPost() && !$this->request->exists('delete_events'))
		{
			$currencyId = 0;
			foreach ($eventIds as $eventId)
			{
				/** @var \MJ\Credits\Entity\Event $event */
				$event = $this->em()->find('MJ\Credits:Event', $eventId);
				if($event){
					$currencyId = $event->currency_id;
				}
				$event->delete(false);
			}

			return $this->redirect($this->buildLink('mjc-credits/events', null, ['currency_id' => $currencyId]));
		}
		else
		{
			$viewParams = [
				'eventIds' => $eventIds
			];
			if(!empty($event)){
				$viewParams['event'] = $event;
			}
			return $this->view('MJ\Credits:Event\Delete', 'mjc_event_delete', $viewParams);
		}
	}

	public function actionDefinition()
	{
		$eventRepo = $this->getEventRepo();
		$finder = $eventRepo->findEventDefinitionsForList();

		$viewParams = [
			'eventDefinitions' => $finder->fetch()
		];
		return $this->view('MJ\Credits:Event\Definition\Listing', 'mjc_event_definition_list', $viewParams);
	}

	protected function definitionAddEdit(EventDefinition $eventDefinition)
	{
		$viewParams = [
			'eventDefinition' => $eventDefinition
		];
		return $this->view('MJ\Credits:Event\Definition\Edit', 'mjc_event_definition_edit', $viewParams);
	}

	public function actionDefinitionEdit(ParameterBag $params)
	{
		$eventDefinition = $this->assertEventDefinitionExists($params->definition_id);
		return $this->definitionAddEdit($eventDefinition);
	}

	public function actionDefinitionAdd()
	{
		$eventDefinition = $this->em()->create('MJ\Credits:EventDefinition');
		return $this->definitionAddEdit($eventDefinition);
	}

	protected function eventDefinitionSaveProcess(EventDefinition $eventDefinition)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'definition_id'    => 'str',
			'definition_class' => 'str',
			'addon_id'         => 'str',
			'display_order'    => 'uint'
		]);

		$form->basicEntitySave($eventDefinition, $input);

		$phraseInput = $this->filter([
			'title'       => 'str',
			'description' => 'str'
		]);

		$form->validate(function (FormAction $form) use ($phraseInput) {
			if ($phraseInput['title'] === '')
			{
				$form->logError(\XF::phrase('please_enter_valid_title'), 'title');
			}
		});

		$form->apply(function () use ($phraseInput, $eventDefinition) {
			$title = $eventDefinition->getMasterPhrase(true);
			$title->phrase_text = $phraseInput['title'];
			$title->save();

			$description = $eventDefinition->getMasterPhrase(false);
			$description->phrase_text = $phraseInput['description'];
			$description->save();
		});

		return $form;
	}

	public function actionDefinitionSave(ParameterBag $params)
	{
		if ($params->definition_id)
		{
			$eventDefinition = $this->assertEventDefinitionExists($params->definition_id);
		}
		else
		{
			$eventDefinition = $this->em()->create('MJ\Credits:EventDefinition');
		}

		$this->eventDefinitionSaveProcess($eventDefinition)->run();

		return $this->redirect($this->buildLink('mjc-credits/events/definitions') . $this->buildLinkHash($eventDefinition->definition_id));
	}

	public function actionDefinitionDelete(ParameterBag $params)
	{
		$eventDefinition = $this->assertEventDefinitionExists($params->definition_id);
		if (!$eventDefinition->preDelete())
		{
			return $this->error($eventDefinition->getErrors());
		}

		if ($this->isPost())
		{
			$eventDefinition->delete();
			return $this->redirect($this->buildLink('mjc-credits/events/definitions'));
		}
		else
		{
			$viewParams = [
				'eventDefinition' => $eventDefinition
			];
			return $this->view('MJ\Credits:Event\Definition\Delete', 'mjc_event_definition_delete', $viewParams);
		}
	}

	public function actionImport()
	{
		$currencyId = $this->filter('currency_id', 'uint');

		if ($this->isPost())
		{
			$upload = $this->request->getFile('upload', false);
			if (!$upload)
			{
				return $this->error(\XF::phrase('mjc_please_upload_valid_events_xml_file'));
			}

			/** @var \MJ\Credits\Service\Event\Import $eventImporter */
			$eventImporter = $this->service('MJ\Credits:Event\Import');

			try
			{
				$document = \XF\Util\Xml::openFile($upload->getTempFile());
			}
			catch (\Exception $e)
			{
				$document = null;
			}

			if (!$eventImporter->isValidXml($document, $error))
			{
				return $this->error($error);
			}
			$currency = $this->assertRecordExists('MJ\Credits:Currency', $currencyId);

			$definitionIds = $this->filter('definition_ids', 'array-str');
			$eventImporter->setDefinitionIds($definitionIds);

			$eventImporter->setCurrency($currency);
			$eventImporter->importFromXml($document);

			return $this->redirect($this->buildLink('mjc-credits/events', null, ['currency_id' => $currencyId]));
		}
		else
		{
			$eventRepo = $this->getEventRepo();
			$eventDefinitions = $eventRepo->getEventDefinitionTitlePairs(true);

			$viewParams = [
				'currencyId'       => $currencyId,
				'eventDefinitions' => $eventDefinitions
			];
			return $this->view('MJ\Credits:Event\Import', 'mjc_event_import', $viewParams);
		}
	}

	public function actionExport(ParameterBag $params)
	{
		$input = $this->filter([
			'currency_id' => 'uint',
		]);

		if ($this->isPost())
		{
			$this->setResponseType('xml');

			$currency = $this->assertCurrencyExists($input['currency_id']);

			/** @var \MJ\Credits\Service\Event\Export $eventExporter */
			$eventExporter = $this->service('MJ\Credits:Event\Export', $currency);

			$addOnId = $this->filter('addon_id', 'str');
			$definitionIds = $this->filter('definition_ids', 'array-str');
			$addOn = $addOnId ? $this->assertRecordExists('XF:AddOn', $addOnId) : null;

			$eventExporter->setAddOn($addOn);
			$eventExporter->setDefinitionIds($definitionIds);

			$viewParams = [
				'currency' => $currency,
				'xml'      => $eventExporter->exportToXml(),
				'filename' => $eventExporter->getExportFileName()
			];
			return $this->view('MJ\Credits:Event\Export', '', $viewParams);
		}
		else
		{
			$eventRepo = $this->getEventRepo();
			$eventDefinitions = $eventRepo->getEventDefinitionTitlePairs(true);

			$viewParams = [
				'currencyId'       => $input['currency_id'],
				'eventDefinitions' => $eventDefinitions
			];
			return $this->view('MJ\Credits:Event\Export', 'mjc_event_export', $viewParams);
		}
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return EventDefinition
	 */
	protected function assertEventDefinitionExists(
		$id, $with = null, $phraseKey = null
	)
	{
		return $this->assertRecordExists('MJ\Credits:EventDefinition', $id, $with, $phraseKey);
	}

	/**
	 * @param int $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \MJ\Credits\Entity\Event
	 */
	protected function assertEventExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('MJ\Credits:Event', $id, $with, 'mjc_requested_event_not_found');
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \MJ\Credits\Entity\Currency
	 */
	protected function assertCurrencyExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('MJ\Credits:Currency', $id, $with, 'mjc_requested_currency_not_found');
	}

	/**
	 * @return \MJ\Credits\Repository\Event
	 */
	protected function getEventRepo()
	{
		return $this->repository('MJ\Credits:Event');
	}

	/**
	 * @return \MJ\Credits\Repository\Currency
	 */
	protected function getCurrencyRepo()
	{
		return $this->repository('MJ\Credits:Currency');
	}
}
