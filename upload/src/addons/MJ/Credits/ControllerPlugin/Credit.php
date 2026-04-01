<?php

namespace MJ\Credits\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;
use XF\Mvc\Entity\Repository;

class Credit extends AbstractPlugin
{
	public function useableCurrencies($definitionId, $currencies = null, $user = null)
	{
		return $this->repository('MJ\Credits:Currency')->useableCurrencies($definitionId, $currencies, $user);
	}

	public function useableCurrency($definitionId, $currencyId, $currencies = null, $user = null)
	{
		return $this->repository('MJ\Credits:Currency')->useableCurrency($definitionId, $currencyId, $currencies, $user);
	}

    /**
     * @return Repository
     */
	protected function getCurrencyRepo()
	{
		return $this->repository('MJ\Credits:Currency');
	}
}
