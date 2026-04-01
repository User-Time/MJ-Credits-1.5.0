<?php

namespace MJ\Credits\Admin\Controller;

use MJ\Credits\Service\Event\Trigger;
use MJ\Credits\Service\Stats\Grapher;
use XF\Entity\OptionGroup;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\Reply\Exception;
use XF\Stats\Grouper\AbstractGrouper;

class Credit extends AbstractController
{
    /**
     * @param $action
     * @param ParameterBag $params
     * @throws Exception
     */
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('mjcCredits');
	}

	public function actionIndex()
	{
		$grouping = $this->filter('grouping', 'str');
		if (!$grouping || !isset($this->app['stats.groupings'][$grouping]))
		{
			$grouping = 'daily';
		}

		/** @var AbstractGrouper $grouper */
		$grouper = $this->app->create('stats.grouper', $grouping);

		$currencies = $this->getCurrencyRepo()->findCurrenciesForList()->fetch();

		$currencyIds = $this->filter('display_types', 'array-str');
		if (!$currencyIds)
		{
			$currencyIds = $currencies->keys();
		}

		if (!$start = $this->filter('start', 'datetime'))
		{
			//$start = strtotime('-1 week');
			$start = $grouper->getDefaultStartDate();
		}

		if (!$end = $this->filter('end', 'datetime'))
		{
			$end = \XF::$time;
		}

		$statsRepo = $this->getStatsRepo();

		/** @var Grapher $grapher */
		$grapher = $this->service('MJ\Credits:Stats\Grapher', $start, $end, $currencyIds);
		$earnData = $grapher->getGroupedData($grouper, 'earn');

		$spendData = $grapher->getGroupedData($grouper, 'spend');

		$currencyPhrased = [];
		foreach($currencies as $currencyId => $currency){
			$currencyPhrased[$currencyId] = $currency->title;
		}

		$wallet = $statsRepo->getTotal($currencies);
		$totalWallets = [];
		$totalValueWallets = [];

		$firstCurrency = $currencies->first();

		$hasWallet = false;
		foreach($wallet as $key => $total){
			$currencyId = intval(str_replace('mjc_total_', '', $key));
			$totalWallets[$currencyId] = [
				'label' => $currencies[$currencyId]->title/* .' ('.
					\MJ\Credits\Util\Money::formatAmount($total, $currencyId)
				.')'*/,
				'total' => $total
			];
			$realVal = ($total / $currencies[$currencyId]->exchange_rate)*$firstCurrency->exchange_rate;
			$totalValueWallets[$currencyId] = [
				'label' => $currencies[$currencyId]->title/* .' ('.
					\MJ\Credits\Util\Money::formatAmount($realVal, $firstCurrency->currency_id)
				.')'*/,
				'total' => $realVal
			];
			if(($total+0) > 0){
				$hasWallet = true;
			}
		}

		$boardStatistics = [
			'first_transaction_date' => $statsRepo->getFirstTransactionDate(),
			'last_transaction_date' => $statsRepo->getLastTransactionDate(),
			'total_transactions' => $this->finder('MJ\Credits:Transaction')->total(),
			'pending_transactions' => $this->finder('MJ\Credits:Transaction')->where('transaction_status', 'pending')->total(),
		];

		$userFinder = $this->finder('XF:User');
		$userStats = [];

		foreach($currencies as $currencyId => $currency){
			$userStats[$currencyId] = $userFinder
				->with('Option', true)
				->with('Profile', true)
				->isValidUser()
				->order($currency['column'], 'DESC')
				->where($currency['column'], '>', 0)
				->limit(10)
				->fetch();
		}

		$viewParams = [
			'boardStatistics'    => $boardStatistics,
			'hasWallet'          => $hasWallet,
			'grouping'           => $grouping,
			'currencies'         => $currencies,
			'userStats'          => $userStats,
			'currencyPhrased'    => $currencyPhrased,
			'earnData'           => $earnData,
			'spendData'          => $spendData,
			'currencyIds'        => $currencyIds,

			'totalWallets'       => $totalWallets,
			'totalValueWallets'  => $totalValueWallets,

			'start'              => $start,
			'end'                => $end,
			'endDisplay'         => ($end >= \XF::$time ? 0 : $end),
			'datePresets'        => \XF::language()->getDatePresets(),

			'contentTypePhrases' => $this->app->getContentTypePhrases(true)
		];
		return $this->view('MJ\Credits:Credit\Stats', 'mjc_stats', $viewParams);
	}

    public function actionOptions()
    {
        $this->setSectionContext('mjcCreditsOptions');
        $group = $this->assertOptionGroupExists('mjcCredits');

        if ($group->AddOn && !$group->AddOn->active) {
            return $this->error(\XF::phrase('option_group_belongs_to_disabled_addon', [
                'addon' => $group->AddOn->title,
                'link' => $this->buildLink('add-ons')
            ]));
        }

        $optionRepo = $this->getOptionRepo();

        $viewParams = [
            'group' => $group,
            'groups' => $optionRepo->findOptionGroupList()->fetch(),
            'canAdd' => $optionRepo->canAddOption()
        ];
        return $this->view('XF:Option\Listing', 'option_list', $viewParams);
    }

	public function actionUsers()
	{
		$criteria = $this->filter('criteria', 'array');
		$order = $this->filter('order', 'str');
		$direction = $this->filter('direction', 'str');

		$page = $this->filterPage();
		$perPage = 20;

		$showingAll = $this->filter('all', 'bool');
		if ($showingAll)
		{
			$page = 1;
			$perPage = 5000;
		}

		$this->setSectionContext('mjcUserList');

		$searcher = $this->searcher('XF:User', $criteria);

		if ($order && !$direction)
		{
			$direction = $searcher->getRecommendedOrderDirection($order);
		}

		$searcher->setOrder($order, $direction);

		$finder = $searcher->getFinder();
		$finder->limitByPage($page, $perPage);

		$filter = $this->filter('_xfFilter', [
			'text' => 'str',
			'prefix' => 'bool'
		]);
		if (strlen($filter['text']))
		{
			$finder->where('username', 'LIKE', $finder->escapeLike($filter['text'], $filter['prefix'] ? '?%' : '%?%'));
		}

		$total = $finder->total();
		$users = $finder->fetch();

		if (!strlen($filter['text']) && $total == 1 && ($user = $users->first()))
		{
			return $this->redirect($this->buildLink('users/edit', $user));
		}

		$currencies = $this->getCurrencyRepo()->findCurrenciesForList()->fetch();

		$viewParams = [
			'users'       => $users,
			'currencies'  => $currencies,

			'total'       => $total,
			'page'        => $page,
			'perPage'     => $perPage,

			'showingAll'  => $showingAll,
			'showAll'     => (!$showingAll && $total <= 5000),

			'criteria'    => $searcher->getFilteredCriteria(),
			'filter'      => $filter['text'],
			'sortOptions' => $searcher->getOrderOptions(),
			'order'       => $order,
			'direction'   => $direction
		];
		return $this->view('XF:User\Listing', 'mjc_user_list', $viewParams);
	}
    public function actionTransfer()
	{
		$this->setSectionContext('mjcTransfer');

		if ($this->isPost())
		{
			$this->repository('MJ\Credits:Currency')->clearAllUserCredits();
			return $this->redirect($this->buildLink('mjc-credits/reset'));
		}
		else
		{
			return $this->view('MJ\Credits:Credit\Reset', 'mjc_send_credits');
		}
	}

	public function actionTransferConfirm()
	{
		$this->setSectionContext('mjcImport');

		$importRepo = $this->repository('MJ\Credits:Import');
		$importer = $this->filter('importer', 'str');

		$viewParams = [
			'importer' => $importer,
		];

		$currencies = $this->app->container('mjc.currencies');
		$currencies = $this->app->repository('MJ\Credits:Currency')->prepareCurrencies($currencies, true);

		$importData = $this->filter('import_data', 'array');

		if ($this->isPost())
		{
			if(!$importData){
				return $this->error(\XF::phrase('mjc_invalid_import_data'));
			}

			$batch = $this->filter('batch', 'uint');
			$this->app->jobManager()->enqueueUnique('mjcImportCredits', 'MJ\Credits:Import', [
				'importer'   => $importer,
				'importData' => $importData,
				'batch'      => $batch
			]);
			return $this->redirect($this->buildLink(
				'mjc-credits/import',
				null,
				['importing' => true]
			));
		}
		else
		{
			$importer = $this->filter('importer', 'str');
			return $this->view('MJ\Credits:Credit\Reset', 'mjc_import_confirm', $viewParams);
		}
	}

	public function actionImport()
	{
		$this->setSectionContext('mjcImport');
		if ($this->isPost())
		{
			$importer = $this->filter('importer', 'str');
			return $this->redirect($this->buildLink('mjc-credits/import-confirm', [], [
				'importer' => $importer
			]), '');
		}
		else
		{
			$viewParams = [
			];

			return $this->view('MJ\Credits:Credit\Reset', 'mjc_import', $viewParams);
		}
	}

	public function actionImportConfirm()
	{
		$this->setSectionContext('mjcImport');

		$importRepo = $this->repository('MJ\Credits:Import');
		$importer = $this->filter('importer', 'str');

		$viewParams = [
			'importer' => $importer,
		];

		$currencies = $this->app->container('mjc.currencies');
		$currencies = $this->app->repository('MJ\Credits:Currency')->prepareCurrencies($currencies, true);

		$importData = $this->filter('import_data', 'array');

		switch ($importer) {
			/*case 'BdBank':
				$viewParams['sourceCurrencies'] = $importRepo->getAdCreditCurrencies();
				break;*/
			case 'AdCredits':
				$sourceCurrencies = $importRepo->getAdCreditCurrencies();
				if(!$sourceCurrencies){
					return $this->error(\XF::phrase('mjc_missing_ad_credits_add_on'));
				}
				if ($this->isPost()){
					foreach($importData as $sourceCurrencyId => &$data){
						if(empty($sourceCurrencies[$sourceCurrencyId]) ||
							empty($data['target_currency_id']) ||
							empty($currencies[$data['target_currency_id']])
						){
							unset($importData[$sourceCurrencyId]);
							continue;
						}
						$sourceCurrency = $sourceCurrencies[$sourceCurrencyId];
						$currency  = $currencies[$data['target_currency_id']];

						$data['import_type'] = !empty($data['import_type']) ? $data['import_type'] : 'merge';
						$data['from']        = $sourceCurrencyId;
						$data['to']          = $currency['column'];
					}
				}else{
					$viewParams['sourceCurrencies'] = $sourceCurrencies;
				}
				break;
			case 'DBTechCredits':
				$sourceCurrencies = $importRepo->getDbTechCreditCurrencies();
				if(!$sourceCurrencies){
					return $this->error(\XF::phrase('mjc_missing_dbtech_credits_add_on'));
				}
				if($this->isPost()){
					foreach($importData as $sourceCurrencyId => &$data){
						if(empty($sourceCurrencies[$sourceCurrencyId]) ||
							empty($data['target_currency_id']) ||
							empty($currencies[$data['target_currency_id']])
						){
							unset($importData[$sourceCurrencyId]);
							continue;
						}
						$sourceCurrency = $sourceCurrencies[$sourceCurrencyId];

						$currency  = $currencies[$data['target_currency_id']];

						$data['import_type'] = !empty($data['import_type']) ? $data['import_type'] : 'merge';

						if($sourceCurrency['table'] !== 'user'){
							$data['from']        = $sourceCurrency['column'];
							$data['to']          = $currency['column'];
						}else{
							$data['query'] = 'UPDATE `xf_user` as `user`
								INNER JOIN `' . ($sourceCurrency['use_table_prefix'] ? 'xf_' : '') . $sourceCurrency['table'] . '` as balance_table
									ON balance_table.`' . $sourceCurrency['user_id_column'] . '` = `user`.`' .
									($sourceCurrency['use_user_id'] ? 'user_id' : 'username') .'`
								SET `user`.`' . $currency['column'] .'` = '.
									($data['import_type'] == 'merge' ? ('`user`.`' . $currency['column'] .'` + ') : '')
									.' balance_table.`' . $sourceCurrency['column'] . '`';
						}
					}
				}else{
					$viewParams['sourceCurrencies'] = $sourceCurrencies;
				}
				break;
			case 'BrCredits':
				$sourceCurrencies = $importRepo->getBrCreditCurrencies();
				if(!$sourceCurrencies){
					return $this->error(\XF::phrase('mjc_missing_brivium_credits_add_on_table'));
				}

				if ($this->isPost()){
					foreach($importData as $sourceCurrencyId => &$data){
						if(empty($sourceCurrencies[$sourceCurrencyId]) ||
							empty($data['target_currency_id']) ||
							empty($currencies[$data['target_currency_id']])
						){
							unset($importData[$sourceCurrencyId]);
							continue;
						}
						$sourceCurrency = $sourceCurrencies[$sourceCurrencyId];
						$currency  = $currencies[$data['target_currency_id']];

						$data['import_type'] = !empty($data['import_type']) ? $data['import_type'] : 'merge';
						$data['from']        = $sourceCurrency['column'];
						$data['to']          = $currency['column'];
					}
				}else{
					$viewParams['sourceCurrencies'] = $sourceCurrencies;
				}
				break;
			case 'Trophy':
				if ($this->isPost()){
					foreach($importData as $sourceCurrencyId => &$data){
						if(empty($data['target_currency_id']) || empty($currencies[$data['target_currency_id']])){
							unset($importData[$sourceCurrencyId]);
							continue;
						}
						$data['import_type'] = !empty($data['import_type']) ? $data['import_type'] : 'merge';
						$data['from']        = 'trophy_points';
						$currency            = $currencies[$data['target_currency_id']];
						$data['to']          = $currency['column'];
					}
				}
				break;
			default:
				return $this->redirect($this->buildLink('mjc-credits/import'));
				break;
		}
		if ($this->isPost())
		{
			if(!$importData){
				return $this->error(\XF::phrase('mjc_invalid_import_data'));
			}

			$batch = $this->filter('batch', 'uint');
			$this->app->jobManager()->enqueueUnique('mjcImportCredits', 'MJ\Credits:Import', [
				'importer'   => $importer,
				'importData' => $importData,
				'batch'      => $batch
			]);
			return $this->redirect($this->buildLink(
				'mjc-credits/import',
				null,
				['importing' => true]
			));
		}
		else
		{
			$importer = $this->filter('importer', 'str');
			return $this->view('MJ\Credits:Credit\Reset', 'mjc_import_confirm', $viewParams);
		}
	}

	public function actionClear()
	{
		$this->setSectionContext('mjcReset');

		if ($this->isPost())
		{
			$this->repository('MJ\Credits:Currency')->clearAllUserCredits();
			return $this->redirect($this->buildLink('mjc-credits/reset'));
		}
		else
		{
			return $this->view('MJ\Credits:Credit\Reset', 'mjc_credits_clear');
		}
	}

	public function actionReset()
	{
		$this->setSectionContext('mjcReset');

		return $this->view('MJ\Credits:Credit\Reset', 'mjc_reset', $this->getSearcherParams([
			'reset' => $this->filter('reset', 'uint')
		]));
	}

	protected function prepareResetData()
	{
		$resetData = $this->filter([
			'currency_id'        => 'uint',

			'reset_credits'      => 'bool',
			'reset_transactions' => 'bool',
			'credit_target'      => 'num',
		]);

		if (!$resetData['reset_credits'] && !$resetData['reset_transactions'])
		{
			throw $this->exception($this->error(\XF::phraseDeferred('mjc_please_choose_reset_credits_or_transactions')));
		}
		$currency = null;
		if ($resetData['currency_id'])
		{
			$currency = $this->em()->find('MJ\Credits:Currency', $resetData['currency_id']);
			if (!$currency)
			{
				throw $this->exception($this->error(\XF::phraseDeferred('mjc_requested_currency_not_found')));
			}
		}

		$data = $this->plugin('XF:UserCriteriaAction')->getInitializedSearchData();

		$data['resetData'] = $resetData;
		$data['currency'] = $currency;

		return $data;
	}

	public function actionResetConfirm()
	{
		$this->setSectionContext('mjcReset');

		$this->assertPostOnly();

		$data = $this->prepareResetData();

		$viewParams = [
			'resetData' => $data['resetData'],
			'currency'  => $data['currency'],
			'total'     => $data['total'],
			'criteria'  => $data['criteria']
		];
		return $this->view('XF:Credits\ResetConfirm', 'mjc_reset_confirm', $viewParams);
	}

	public function actionResetProcess()
	{
		$this->setSectionContext('mjcReset');

		$this->assertPostOnly();

		$data = $this->prepareResetData();

		$this->app->jobManager()->enqueueUnique('mjcResetCredits', 'MJ\Credits:ResetCredits', [
			'criteria' => $data['criteria'],
			'resetData' => $data['resetData']
		]);

		return $this->redirect($this->buildLink(
			'mjc-credits/reset',
			null,
			['sent' => $data['total']]
		));
	}

	protected function getSearcherParams(array $extraParams = [])
	{
		$searcher = $this->searcher('XF:User');

		$viewParams = [
			'criteria' => $searcher->getFormCriteria(),
			'sortOrders' => $searcher->getOrderOptions()
		];
		return $viewParams + $searcher->getFormData() + $extraParams;
	}

    /**
     * @param string $groupId
     * @param array|string|null $with
     * @param null|string $phraseKey
     *
     * @return OptionGroup
     */
    protected function assertOptionGroupExists($groupId, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('XF:OptionGroup', $groupId, $with, $phraseKey);
    }

    /**
     * @return \XF\Repository\OptionRepository
     */
    protected function getOptionRepo()
    {
        return $this->repository('XF:OptionRepository');
    }

    /**
     * @param string $id
     * @param array|string|null $with
     * @param null|string $phraseKey
     *
     * @return \XF\Entity\User
     */
    protected function assertUserExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists(\XF\Entity\User::class, $id, $with, $phraseKey);
    }

	/**
	 * @return \MJ\Credits\Repository\Currency
	 */
	protected function getCurrencyRepo(): \MJ\Credits\Repository\Currency
    {
		return $this->repository('MJ\Credits:Currency');
	}

	/**
	 * @return \MJ\Credits\Repository\Stats
	 */
	protected function getStatsRepo()
	{
		return $this->repository('MJ\Credits:Stats');
	}
}
