<?php

namespace MJ\Credits\Admin\Controller;

use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Admin\Controller\AbstractController;

class Currency extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
        $this->assertAdminPermission('mjcCredits');
		$this->assertAdminPermission('mjcCurrency');
	}

	public function actionIndex()
	{
		$viewParams = [
			'currencies' => $this->getCurrencyRepo()->findCurrenciesForList()->fetch()
		];
		return $this->view('MJ\Credits:Currency\Listing', 'mjc_currency_list', $viewParams);
	}

	protected function currencyAddEdit(\MJ\Credits\Entity\Currency $currency)
	{
		$viewParams = [
			'currency' => $currency,
		];
		return $this->view('MJ\Credits:Currency\Edit', 'mjc_currency_edit', $viewParams);
	}

	public function actionAdd()
	{
		/** @var \MJ\Credits\Entity\Currency $currency */
		$currency = $this->em()->create('MJ\Credits:Currency');
		return $this->currencyAddEdit($currency);
	}

	public function actionEdit(ParameterBag $params)
	{
		$currency = $this->assertCurrencyExists($params->currency_id);
		return $this->currencyAddEdit($currency);
	}

	protected function currencySaveProcess(\MJ\Credits\Entity\Currency $currency)
	{
		$form = $this->formAction();

		$input = $this->filter([
			//'code'           => 'str',
			'prefix'         => 'str,no-trim',
			'suffix'         => 'str,no-trim',
			'decimal_places' => 'uint',
            'positive'       => 'uint',
			'exchange_rate'  => 'unum',
            'max_amount'     => 'float',
			'display_order'  => 'uint',
			'wallet_popup'   => 'bool',
			'active'         => 'bool',
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

		$form->basicEntitySave($currency, $input);

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

		$form->apply(function () use ($phraseInput, $currency) {
			$title = $currency->getMasterPhrase(true);
			$title->phrase_text = $phraseInput['title'];
			$title->save();

			$description = $currency->getMasterPhrase(false);
			$description->phrase_text = $phraseInput['description'];
			$description->save();
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		if ($params->currency_id)
		{
			$currency = $this->assertCurrencyExists($params->currency_id);
		}
		else
		{
			/** @var \MJ\Credits\Entity\Currency $currency */
			$currency = $this->em()->create('MJ\Credits:Currency');
		}

		$this->currencySaveProcess($currency)->run();

		return $this->redirect($this->buildLink('mjc-credits/currencies') . $this->buildLinkHash($currency->currency_id));
	}

	public function actionToggle()
	{
		/** @var \XF\ControllerPlugin\Toggle $plugin */
		$plugin = $this->plugin('XF:Toggle');
		return $plugin->actionToggle('MJ\Credits:Currency', 'active');
	}

	public function actionDelete(ParameterBag $params)
	{
		$currency = $this->assertCurrencyExists($params->currency_id);
		if (!$currency->preDelete())
		{
			return $this->error($currency->getErrors());
		}

		if ($this->isPost())
		{
			$currency->delete();

			return $this->redirect($this->buildLink('mjc-credits/currencies'));
		}
		else
		{
			$viewParams = [
				'currency' => $currency
			];
			return $this->view('MJ\Credits:Currency\Delete', 'mjc_currency_delete', $viewParams);
		}
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
	 * @return \MJ\Credits\Repository\Currency
	 */
	protected function getCurrencyRepo()
	{
		return $this->repository('MJ\Credits:Currency');
	}
}
