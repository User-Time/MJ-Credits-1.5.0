<?php

namespace MJ\Credits\Widget;

use XF\Widget\AbstractWidget;

class Richest extends AbstractWidget
{
	protected $defaultOptions = [
		'limit' => 10,
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
		$visitor = \XF::visitor();

		$options = $this->options;
		$limit = $options['limit'];
		$currencyIds = $options['currency_ids'];

		$router = $this->app->router('public');

		$currencies = $this->app->container('mjc.currencies');
		$currencies = $this->repository('MJ\Credits:Currency')->prepareCurrencies($currencies, true);

		$richestList = [];
		foreach($currencies as $currencyId => $currency){
			if($currencyIds && !in_array(0, $currencyIds) && !in_array($currencyId, $currencyIds)){
				continue;
			}
			$richestList[$currencyId] = $this->finder('XF:User')
				->with('Option', true)
				->with('Profile', true)
				->isValidUser()
				->order($currency['column'], 'DESC')
				->where($currency['column'], '>', 0)
				->limit($limit)
				->fetch();
		}

		if (!$richestList)
		{
			return '';
		}

		$viewParams = [
			'richestList' => $richestList,
			'currencies' => $currencies,
		];
		return $this->renderer('mjc_widget_richest_members', $viewParams);
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
