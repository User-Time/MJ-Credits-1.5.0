<?php
namespace MJ\Credits\Widget;

use XF\Widget\AbstractWidget;

class ClockInEveryDay extends AbstractWidget
{
    protected $defaultOptions = [
        'currency_ids' => []
    ];

    public function render()
    {
        $visitor = \XF::visitor();
        $db = \XF::app()->db();

        if (!$visitor->user_id) {
            return '';
        }

        $options = $this->options;
        $currencyIds = $options['currency_ids'];

        /** @var \MJ\Credits\Repository\Currency $currencyRepo */
        $currencyRepo = $this->getCurrencyRepo();
        $currencies = $currencyRepo->useableCurrencies('daily_clock');

        // 今天起始时间
        date_default_timezone_set('Asia/Hong_Kong');
        $dt = new \DateTime('today', new \DateTimeZone(\XF::options()->guestTimeZone));

        $today = $dt->getTimestamp();

        $clockStats = [];
        $allSignedToday = true;

        foreach ($currencies as $currency) {
            // 跳过不在配置范围内的货币
            if (!empty($currencyIds) && !in_array(0, $currencyIds) && !in_array($currency['currency_id'], $currencyIds)) {
                continue;
            }

            $currencyId = $currency['currency_id'];

            // 本月签到奖励总额
            $total = $db->fetchOne("
            SELECT SUM(amount)
            FROM xf_mjc_transaction
            WHERE transaction_status = 'completed'
              AND DATE_FORMAT(FROM_UNIXTIME(transaction_date), '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
              AND user_id = ?
              AND amount > 0
              AND currency_id = ?
              AND definition_id = 'daily_clock'
        ", [$visitor->user_id, $currencyId]);

            // 本月签到次数
            $clockCount = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_mjc_transaction
            WHERE transaction_status = 'completed'
              AND DATE_FORMAT(FROM_UNIXTIME(transaction_date), '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
              AND user_id = ?
              AND currency_id = ?
              AND definition_id = 'daily_clock'
        ", [$visitor->user_id, $currencyId]);

            // 今天是否已签到
            $signedToday = $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_mjc_transaction
            WHERE transaction_status = 'completed'
              AND transaction_date BETWEEN ? AND ?
              AND user_id = ?
              AND amount > 0
              AND currency_id = ?
              AND definition_id = 'daily_clock'
        ", [$today, \XF::$time, $visitor->user_id, $currencyId]);

            // 是否还有没签到的
            if (!$signedToday) {
                $allSignedToday = false;
            }

            $clockStats[] = [
                'currency' => $currency,
                'clockCount' => $clockCount,
                'total' => \MJ\Credits\Util\Money::formatAmount($total, $currency),
                'signedToday' => $signedToday
            ];
        }

        $viewParams = [
            'currencies'      => $currencies,
            'clockStats'      => $clockStats,
            'allSignedToday'  => $allSignedToday
        ];

        return $this->renderer('mjc_widget_user_clock_in_every_day', $viewParams);
    }

    protected function getDefaultTemplateParams($context)
    {
        $params = parent::getDefaultTemplateParams($context);
        if ($context == 'options')
        {
            $currencyRepo = $this->app->repository('MJ\Credits:Currency');
            $currencies = $this->app->container('mjc.currencies');
            $currencies = $currencyRepo->prepareCurrencies($currencies, true);

            $dailyClockAbleCurrencies = $currencyRepo->useableCurrencies('daily_clock', $currencies);
            $params['currencies'] = $dailyClockAbleCurrencies;
        }
        return $params;
    }

    public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
    {
        $options = $request->filter([
            'currency_ids' => 'array-uint'
        ]);

        if (in_array(0, $options['currency_ids']))
        {
            $options['currency_ids'] = [0];
        }

        return true;
    }

    protected function getCurrencyRepo()
    {
        return $this->repository('MJ\Credits:Currency');
    }
}