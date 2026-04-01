<?php

namespace MJ\Credits\Widget;

use XF\Widget\AbstractWidget;

class Balance extends AbstractWidget
{
	protected $defaultOptions = [
		'style'        => 'compact',
		'currency_ids' => []
	];

	protected function getDefaultTemplateParams($context)
	{
		$params = parent::getDefaultTemplateParams($context);
		if ($context == 'options')
		{
			$currencies = $this->app->container('mjc.currencies');
			$params['currencies'] = $this->repository('MJ\Credits:Currency')->prepareCurrencies($currencies);
		}
		return $params;
	}

	public function render()
	{
		if(!\XF::visitor()->user_id){
			return '';
		}
		$currencies = $this->getCurrencies();

		if(!$currencies){
			return '';
		}

		$options = $this->options;

		$viewParams = [
			'currencies' => $currencies,
			'style'      => $options['style']
		];
		return $this->renderer('mjc_widget_balance', $viewParams);
	}

	protected function getCurrencies()
	{
		$options = $this->options;
		$limit = $options['limit'];
		$currencyIds = $options['currency_ids'];

		$currencyRepo = $this->app->repository('MJ\Credits:Currency');
		$currencies = $this->app->container('mjc.currencies');
		$currencies = $currencyRepo->prepareCurrencies($currencies, true);

		$transferableCurrencies = $currencyRepo->useableCurrencies('transfer', $currencies);

		foreach($currencies as $currencyId => $currency){
			if($currencyIds && !in_array(0, $currencyIds) && !in_array($currencyId, $currencyIds)){
				unset($currencies[$currencyId]);
				continue;
			}
			if(isset($transferableCurrencies[$currencyId])){
				$currencies[$currencyId]['canTransfer'] = true;
			}
		}
		return $currencies;
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint',
			'style' => 'str',
			'filter' => 'str',
			'currency_ids' => 'array-uint'
		]);
		if (in_array(0, $options['currency_ids']))
		{
			$options['currency_ids'] = [0];
		}
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}
}
