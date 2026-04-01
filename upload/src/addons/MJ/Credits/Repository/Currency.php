<?php

namespace MJ\Credits\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Currency extends Repository
{
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

    public function getChargeCurrency()
    {
        $options = $this->options();
        $currencyId = $options->mjc_credits_event_trigger_content_currency;

        if (!$currencyId) {
            /** @var \MJ\Credits\Entity\Currency $currency */
            $currency = $this->finder('MJ\Credits:Currency')
                ->fetchOne();

            /** @var \XF\Repository\OptionRepository $optionRepo */
            $optionRepo = $this->repository('XF:Option');
            $optionRepo->updateOptions([
                'mjc_credits_event_trigger_content_currency' => $currency->currency_id
            ]);

            $currencyId = $currency->currency_id;
        }

        /** @var \MJ\Credits\Entity\Currency $currency */
        $currency = $this->em->find('MJ\Credits:Currency', $currencyId);

        return $currency;
    }

    /**
     * @return mixed|\XF\Mvc\Entity\ArrayCollection
     */
    public function getCurrencies()
    {
        $container = $this->app()->container();
        if (isset($container['mjc.currencies']) && $currencies = $container['mjc.currencies'])
        {
            $em = \XF::em();

            $entities = [];
            foreach ($currencies as $currencyId => $currency) {
                $entities[$currencyId] = $em->instantiateEntity('MJ\Credits:Currency', $currency);
            }

            return $em->getBasicCollection($entities);
        }

        return $currencies;
    }

    /**
     * @return \MJ\Credits\Entity\Currency[]|\XF\Mvc\Entity\ArrayCollection
     */
    public function getViewableCurrencies()
    {
        return $this->getCurrencies();
    }

	public function useableCurrencies($definitionId, $currencies = null, $user = null)
	{
		if(!$definitionId){
			return [];
		}
		if(!$currencies){
			$currencies = $this->app()->container('mjc.currencies');
			$currencies = $this->prepareCurrencies($currencies, true);
		}

		$cEvent = $this->app()->container('mjcEvent');
		foreach($currencies as $currencyId => &$currency){
			if(empty($currency['can_use'])){
				unset($currencies[$currencyId]);
				continue;
			}
			if($definitionId){
				$events = $cEvent->getEvents($definitionId, ['currency_id' => $currencyId, 'filter_valid' => true, 'user' => $user]);
				if(!$events){
					unset($currencies[$currencyId]);
					continue;
				}else{
					$currency['event'] = reset($events);
				}
			}
		}
		unset($currency);

		return $currencies;
	}

	public function useableCurrency($definitionId, $currencyId, $currencies = null, $user = null)
	{
		$currencies = $this->useableCurrencies($definitionId, $currencies, $user);

		return isset($currencies[$currencyId]) ? $currencies[$currencyId] : false;
	}

	public function getUseAbleCurrencies($definitionId)
    {
        return $this->useableCurrencies($definitionId);
    }

	public function prepareCurrencies(array $currencies, $removeUnuseable = false, $user = null)
	{
		if(!$user){
			$user = \XF::visitor();
		}
		foreach($currencies as $currencyId => &$currency){
			$currency['can_use'] = $this->canUseCurrency($currency, $user);

			if($removeUnuseable && (!$currency['can_use'] || !$this->validateCurrency($currency, $user))){

				unset($currencies[$currencyId]);
				continue;
			}
			$currency['title'] = \XF::phrase('mjc_currency_title.' . $currencyId);
		}

		unset($currency);

		return $currencies;
	}

	public function validateCurrency($currency, $user, &$error = null)
	{
		if(empty($currency['active'])){
			$error = \XF::phrase('mjc_this_currency_disabled');
			return false;
		}
		if(!$user->isValidColumn($currency['column'])){
			$error = \XF::phrase('mjc_column_x_was_not_found', ['column' => $currency['column']]);
			return false;
		}
		if(!$this->canUseCurrency($currency, $user)){
			$error = \XF::phrase('mjc_x_do_not_have_permission_to_use_this_currency', ['user' => $user->username]);
			return false;
		}
		return true;
	}

	public function canUseCurrency($currency, $user)
	{
		if(!empty($currency['allowed_user_group_ids']) &&
			!in_array(-1, $currency['allowed_user_group_ids']) &&
			!$user->isMemberOf($currency['allowed_user_group_ids'])){
			return false;
		}
		return true;
	}

	public function getColumn($currencyId)
	{
		if(!$currencyId){
			return '';
		}
		return 'mjc_' . $currencyId;
	}

	public function resetUserCredit($userId)
	{
		$cache = $this->getCurrencyCache();
		\XF::registry()->set('mjcCurrencies', $cache);
		return $cache;
	}

	public function resetUserCredits($userId = 0, $currencyId = 0, $targetCredit = 0)
	{
		$db = $this->db();
		$where = '1 = 1';

		if($userId){
			if(is_array($userId)){
				$where .= ' AND user_id IN(' . $db->quote($userId) . ')';
			}else{
				$where .= ' AND user_id = ' . $db->quote($userId);
			}
		}
		$cols = [];
		if($currencyId){
			if(is_array($currencyId)){
				foreach ($currencyId as $key => $id)
				{
					$cols[$this->getColumn($id)] = $targetCredit;
				}
			}else{
				$cols[$this->getColumn($currencyId)] = $targetCredit;
			}
		}else{
			$currencies = $this->findCurrenciesForList()->fetch();
			foreach ($currencies as $key => $currency)
			{
				$cols[$this->getColumn($currency->currency_id)] = 0;
			}
		}

		$db->update('xf_user', $cols, $where);
	}

	public function clearAllUserCredits()
	{
		$db = $this->db();
		$currencies = $this->findCurrenciesForList()->fetch();

		$cols = [];
		foreach ($currencies as $key => $currency)
		{
			$cols[$this->getColumn($currency->currency_id)] = 0;
		}
		if($cols){
			$db->update('xf_user', $cols, '1=1');
		}
	}

	public function rebuildCurrencyCache()
	{
		$cache = $this->getCurrencyCache();
		\XF::registry()->set('mjcCurrencies', $cache);
		return $cache;
	}

    /**
     * @param bool $onlyActive
     *
     * @return array|\XF\Mvc\Entity\ArrayCollection
     */
    public function getCurrencyTitlePairs($onlyActive = false)
    {
        $currencyFinder = $this->findCurrenciesForList();

        $currencies = $currencyFinder->fetch();
        if ($onlyActive)
        {
            $currencies = $currencies->filterViewable();
        }

        return $currencies->pluckNamed('title', 'currency_id');
    }

    /**
     * @param bool $includeEmpty
     * @param null $type
     *
     * @return array
     */
    public function getCurrencyOptionsData($includeEmpty = true, $type = null)
    {
        $choices = [];
        if ($includeEmpty)
        {
            $choices = [
                0 => ['_type' => 'option', 'value' => 0, 'label' => \XF::phrase('(none)')]
            ];
        }

        $currencies = $this->getCurrencyTitlePairs();

        foreach ($currencies AS $currencyId => $label)
        {
            $choices[$currencyId] = [
                'value' => $currencyId,
                'label' => $label
            ];
            if ($type !== null)
            {
                $choices[$currencyId]['_type'] = $type;
            }
        }

        return $choices;
    }
}
