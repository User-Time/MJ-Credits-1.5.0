<?php

namespace MJ\Credits;

use MJ\Credits\Entity\Currency;
use XF\Mvc\Entity\Entity;
use XF\CustomField\DefinitionSet;

class Listener
{
    /**
     * @param \XF\App $app
     * @throws \XF\Db\Exception
     */
	public static function appSetup(\XF\App $app)
	{
		$container = $app->container();
		$container->extend('templater.config.functions', function (array $functions) {
			$functions['mjc_format'] = ['\\MJ\\Credits\\Template\\Templater', 'fnFormat'];
			return $functions;
		});

		$container->extend('templater.config.filters', function (array $filters) {
			$filters['mjc_currency'] = ['\\MJ\\Credits\\Template\\Templater', 'filterCurrencies'];
			return $filters;
		});

		$container['mjc.eventDefaultClass'] = 'MJ\Credits\Event\EventHandler';

		$container['mjc.currencies'] = $app->fromRegistry(
			'mjcCurrencies',
			function (\XF\Container $c) {
				return $c['em']->getRepository('MJ\Credits:Currency')->rebuildCurrencyCache();
			}
		);
		$container['mjc.eventDefinition'] = $app->fromRegistry(
			'mjcEventDefinition',
			function(\XF\Container $c) {
				return $c['em']->getRepository('MJ\Credits:Event')->rebuildEventDefinitionCache();
			}
		);

		$container['mjc.eventCache'] = $app->fromRegistry(
			'mjcEvents',
			function (\XF\Container $c)
			{
				return $c['em']->getRepository('MJ\Credits:Event')->rebuildEventCache();
			},
			function(array $events)
			{
				return new DefinitionSet($events);
			}
		);

		$container['mjcEvent'] = function($c)
		{
			$app = \XF::app();
			$class = $app->extendClass('MJ\Credits\SubContainer\Event');
			return new $class($c, $app);
		};
	}

    /**
     * @param string $rule
     * @param array $data
     * @param \XF\Entity\User $user
     * @param bool $returnValue
     */
    public static function criteriaUser(string $rule, array $data, \XF\Entity\User $user, bool &$returnValue)
    {
        $container = \XF::app()->container();

        if (isset($container['mjc.currencies']) && $currencies = $container['mjc.currencies'])
        {
            foreach ($currencies as $currencyId => $currency)
            {
                if ($rule == 'mjc_credits_currency_' . $currencyId . '_less')
                {
                    if ($user->{$currency['column']} < $data['amount'])
                    {
                        $returnValue = true;
                        break;
                    }
                }
                elseif ($rule == 'mjc_credits_currency_' . $currencyId . '_more')
                {
                    if ($user->{$currency['column']} > $data['amount'])
                    {
                        $returnValue = true;
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * @param \XF\Template\Templater $templater
     * @param string $type
     * @param string $template
     * @param string $name
     * @param array $arguments
     * @param array $globalVars
     */
    public static function templaterMacroPreRender(
        \XF\Template\Templater $templater,
        string &$type,
        string &$template,
        string &$name,
        array &$arguments,
        array &$globalVars
    ): void {
        if (!empty($arguments['group']) && $arguments['group']->group_id  == 'mjcCredits')
        {
            // 覆盖模板名称
            $template = 'mjc_credits_option_macros';

            // 或使用'option_form_block_tabs'选项卡
            $name = 'option_form_block_tabs';

            // 您的模块标题配置
            $arguments['headers'] = [
                'generalOptions'      => [
                    'label'           => \XF::phrase('general_options'),
                    'minDisplayOrder' => 0,
                    'maxDisplayOrder' => 2000,
                    'active'          => true
                ],
                'eventTriggerOptions' => [
                    'label'           => \XF::phrase('mjc_credits_event_trigger_options'),
                    'minDisplayOrder' => 3000,
                    'maxDisplayOrder' => -1
                ],
            ];
        }
    }

	public static function userSearcherOrders(\XF\Searcher\User $userSearcher, array &$sortOrders)
	{
		$currencies = \XF::app()->container('mjc.currencies');
		foreach($currencies as $currency){
			$sortOrders[$currency['column']] = '[BRC] ' . \XF::phrase('mjc_currency_title' . '.' . $currency['currency_id']);
		}
	}

	public static function userEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
	{
		$currencies = \XF::app()->container('mjc.currencies');
		foreach($currencies as $currency){
			$structure->columns[$currency['column']] = ['type' => Entity::FLOAT, 'default' => 0];
		}
	}

    /**
     * @param \XF\Pub\App $app
     * @param \XF\Http\Response $response
     * @throws \Exception
     */
    public static function appPubComplete(\XF\Pub\App $app, \XF\Http\Response &$response)
    {
        $visitor = \XF::visitor();

        $options = \XF::options();

        /** @var \XF\Mvc\Entity\ArrayCollection $currencies */
        $container = \XF::app()->container();

        if ($visitor->user_id && isset($container['mjc.currencies']) && $currencies = $container['mjc.currencies']) {

            // Get today's timestamp
            $dt = new \DateTime('today', new \DateTimeZone($options->guestTimeZone));

            $today = $dt->getTimestamp();

            foreach ($currencies as $currencyId => $currency) {
                $trigger = \XF::app()->service('MJ\Credits:Event\Trigger');

                if (\XF::visitor()->user_id
                    && $today > $visitor->mjc_credits_last_daily
                ) {
                    $trigger->triggerEvent('daily_login', \XF::visitor()->user_id, [
                        'maximum_apply' => [
                            'daily' => 1
                        ]
                    ]);
                }
                $visitor->fastUpdate('mjc_credits_last_daily', \XF::$time);
            }
        }
    }


    /**
     * @param \XF\Pub\App $app
     * @param array $navigationFlat
     * @param array $navigationTree
     */
    public static function navigationSetup(\XF\Pub\App $app, array &$navigationFlat, array &$navigationTree): void
    {
        if (!isset($navigationFlat['mjcCredits']) OR !isset($navigationTree['mjcCredits']))
        {
            return;
        }
    }

    
    /**
     * @param array $data
     * @param \XF\Mvc\Controller $controller
     */
    public static function editorDialog(array &$data, \XF\Mvc\Controller $controller)
    {
        $data['template'] = 'mjc_credits_editor_dialog_charge';
        $data['params']['currency'] = \XF::repository('MJ\Credits:Currency')->getChargeCurrency();
    }
}
