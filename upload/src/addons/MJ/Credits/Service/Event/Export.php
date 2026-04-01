<?php

namespace MJ\Credits\Service\Event;

use MJ\Credits\Entity\Currency;
use XF\Util\Xml;

class Export extends \XF\Service\AbstractService
{
	const EXPORT_VERSION_ID = 2;

	/**
	 * @var Currency
	 */
	protected $currency;

	protected $definitionIds;

	/**
	 * @var \XF\Entity\AddOn|null
	 */
	protected $addOn;

	public function __construct(\XF\App $app, Currency $currency)
	{
		parent::__construct($app);
		$this->setCurrency($currency);
	}

	public function setCurrency(Currency $currency)
	{
		$this->currency = $currency;
	}

	public function getCurrency()
	{
		return $this->currency;
	}

	public function setAddOn(\XF\Entity\AddOn $addOn = null)
	{
		$this->addOn = $addOn;
	}

	public function setDefinitionIds($definitionIds)
	{
		$this->definitionIds = array_filter($definitionIds);
	}

	public function getAddOn()
	{
		$this->addOn;
	}

	public function exportToXml()
	{
		$document = $this->createXml();
		$currencyNode = $this->getCurrencyNode($document);
		$document->appendChild($currencyNode);

		foreach ($this->getExportableEvent() as $event)
		{
			$eventNode = $this->getEventNode($document, $event);
			$currencyNode->appendChild($eventNode);
		}

		return $document;
	}

	public function getExportFileName()
	{
		$addOnLimit = $this->addOn ? '-' . $this->addOn->addon_id : '';

		return "mjc-credits-events{$addOnLimit}.xml";
	}

	/**
	 * @return \DOMDocument
	 */
	protected function createXml()
	{
		$document = new \DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		return $document;
	}

	/**
	 * @param \DOMDocument $document
	 * @return \DOMElement
	 */
	protected function getCurrencyNode(\DOMDocument $document)
	{
		$currency = $this->currency;

		$currencyNode = $document->createElement('events');
		if ($this->addOn)
		{
			$currencyNode->setAttribute('addon_id', $this->addOn->addon_id);
		}
		$currencyNode->setAttribute('export_version', self::EXPORT_VERSION_ID);
		$document->appendChild($currencyNode);

		return $currencyNode;
	}

	protected function getEventNode(\DOMDocument $document, array $event)
	{
		$eventNode = $document->createElement('event');
		$eventNode->setAttribute('definition_id', $event['definition_id']);
		$eventNode->setAttribute('currency_id', $event['currency_id']);
		$eventNode->setAttribute('amount', $event['amount']);
		$eventNode->setAttribute('moderate_transactions', $event['moderate_transactions']);
		$eventNode->setAttribute('send_alert', $event['send_alert']);
		$eventNode->setAttribute('active', $event['active']);

		$eventNode->appendChild(
			Xml::createDomElement($document, 'options', \XF\Util\Json::jsonEncodePretty($event['options']))
		);

		$eventNode->appendChild(
			$document->createElement('allowed_user_group_ids', $event['allowed_user_group_ids'])
		);

		$titlePhrase = \XF::phrase('mjc_event.' . $event['event_id']);
		$title = $titlePhrase->render('html', ['nameOnInvalid' => false]) ?: '';
		$eventNode->appendChild(
			Xml::createDomElement($document, 'title', $title)
		);

		$descPhrase = \XF::phrase('mjc_event_desc.' . $event['event_id']);
		$desc = $descPhrase->render('html', ['nameOnInvalid' => false]) ?: '';
		$eventNode->appendChild(
			Xml::createDomElement($document, 'description', $desc)
		);
		return $eventNode;
	}

	/**
	 * @return array
	 */
	protected function getExportableEvent()
	{
		$currency = $this->currency;

		$db = $this->db();

		$addonLimitSql = '';
		$addonLimitSql .= ($this->addOn ? " AND definition.addon_id = " . $db->quote($this->addOn->addon_id) : '');

		$addonLimitSql .= ($this->definitionIds ? " AND event.definition_id IN (" . $db->quote($this->definitionIds) .")" : '');

		return $db->fetchAll("
			SELECT event.*
			FROM xf_mjc_event AS event
				INNER JOIN xf_mjc_event_definition AS definition
					ON (definition.definition_id = event.definition_id)
			WHERE event.currency_id = ?
				$addonLimitSql
			ORDER BY event.event_id
		", $currency->currency_id);
	}
}
