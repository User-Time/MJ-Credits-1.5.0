<?php

namespace MJ\Credits\Event;

use MJ\Credits\Entity\Event;
use XF\Mvc\Entity\Entity;
use XF\Entity\User;

class EventHandler
{
    /**
     * @var \XF\App
     */
	protected $app;

	protected $definition;

	protected $contextParams = [];

	protected $options;
	protected $defaultOptions = [];

	protected $editParams = [];

	public function __construct(\XF\App $app, $definition = null)
	{
		$this->app = $app;

		$this->definition = $definition;
	}

	public function getContentLink($contentType, $contentId, array $extraParams = [])
	{
		switch ($contentType) {
			case 'thread':
				return \XF::app()->router('public')->buildLink('threads', ['thread_id' => $contentId], $extraParams);
				break;
			case 'post':
				return \XF::app()->router('public')->buildLink('posts', ['post_id' => $contentId], $extraParams);
				break;
			case 'attachment':
				return \XF::app()->router('public')->buildLink('attachments', ['attachment_id' => $contentId], $extraParams);
				break;
			case 'conversation':
				return \XF::app()->router('public')->buildLink('conversations', ['conversation_id' => $contentId], $extraParams);
				break;
			case 'profile_post':
				return \XF::app()->router('public')->buildLink('profile-posts', ['profile_post_id' => $contentId], $extraParams);
				break;
			case 'resource':
				return \XF::app()->router('public')->buildLink('resources', ['resource_id' => $contentId], $extraParams);
				break;
			default:
				return false;
				break;
		}
	}

	public function getHandlerOption($key)
	{
		$handlerOptions = $this->getHandlerOptions();
		if(isset($handlerOptions[$key])){
			return $handlerOptions[$key];
		}else{
			return null;
		}
	}

	public function getHandlerOptions()
	{
		$handlerOptions = [];
		if(empty($this->definition['definition_id'])){
			return $handlerOptions;
		}
		switch ($this->definition['definition_id']) {
            case 'daily_clock':
                $handlerOptions['hasFeeOptions'] = false;
                break;

			case 'exchange':
            case 'red_envelope':
            case 'reward':
            case 'content':
            case 'transfer':
				$handlerOptions['hideAmountField'] = true;
                $handlerOptions['hasFeeOptions'] = true;
				break;

            case 'bonus':
            case 'payment':
            case 'offerReward':
            case 'adjust':
			case 'fine':
                $handlerOptions['hideAmountField'] = true;
                $handlerOptions['hasFeeOptions'] = false;
                break;

            case 'thread_new':
			case 'thread_delete':
            case 'soft_thread_delete':
			case 'thread_reply_receive':
			case 'thread_reply_lose':
			case 'thread_view':
			case 'thread_view_receive':
			case 'thread_sticky':
			case 'thread_unsticky':
			case 'thread_watch':
			case 'thread_unwatch':
			case 'thread_watch_receive':
			case 'thread_watch_lose':
			case 'post_new':
			case 'post_delete':
            case 'soft_post_delete':
			case 'post_like':
			case 'post_unlike':
			case 'post_like_receive':
			case 'post_like_lose':
			case 'post_report':
			case 'post_report_receive':
				$handlerOptions['hasForumOptions'] = true;
                $handlerOptions['hasFeeOptions'] = false;
				break;

			default:
				# code...
				break;
		}
		return $handlerOptions;
	}

	public function getDefaultEventOptions()
	{
		$handlerOptions = $this->getHandlerOptions();
		$eventOptions = [];
		if(!empty($handlerOptions['hasFeeOptions'])){
			$eventOptions['fee'] = 0;
			$eventOptions['tax'] = 0;
		}
		if(!empty($handlerOptions['hasForumOptions'])){
			$eventOptions['node_ids'] = [];
		}
		return $eventOptions;
	}

	public function getEventAddEditReply(\XF\Mvc\Controller $controller, Entity $event, $viewParams, $templateName, $viewClass)
	{
		if(!$event->event_id || !$event->options){
			$event->options = $this->getDefaultEventOptions();
		}
		$event->options = array_replace($this->getDefaultEventOptions(), $event->options);

		$viewParams['handlerOptions'] = $event->handler->getHandlerOptions();
		if(!empty($viewParams['handlerOptions']['hasForumOptions'])){
			$viewParams['forumOptions'] = $this->repository('XF:Node')->getNodeOptionsData(false, 'Forum');
		}

		$this->prepareAddEditReply($event, $viewParams, $templateName, $viewClass);
		$this->editParams = $viewParams;
		return $controller->view($viewClass, $templateName, $viewParams);
	}

	protected function prepareAddEditReply(Entity $event, &$viewParams, &$templateName, &$viewClass)
	{
	}

    /**
     * @param array $event
     * @param $params
     * @param null $error
     * @return bool
     * @throws \Exception
     */
	public function validateEvent(array $event, $params, &$error = null): bool
    {
		$valid = $this->repository('MJ\Credits:Event')->validateEvent(
			$event,
			$params['target_user'],
			$params['ignore_event_privilege'],
			$error
		);
		if(!$valid){
			return false;
		}

		if(!empty($event['options']['node_ids'])){
			$validNodeIds = array_filter($event['options']['node_ids']);
			if($validNodeIds && $params['node_id'] && !in_array($params['node_id'], $validNodeIds)){
				$error = \XF::phrase('mjc_this_event_cannot_trigger_in_this_forum');
				return false;
			}
		}

		if(!$this->checkMaximumApply($event, $params, $error)){
			return false;
		}

		return $this->_validateEvent($event, $params, $error);
	}

    /**
     * @param array $event
     * @param $params
     * @param null $error
     * @return bool
     * @throws \Exception
     */
	public function checkMaximumApply(array $event, $params, &$error = null)
	{
		if(!empty($params['maximum_apply'])){
			$maximumApply = $params['maximum_apply'];
			$fromDate = false;
			$max = 0;

			$date = new \DateTime('@' . \XF::$time);
			$date->setTimezone(new \DateTimeZone('UTC'));
			$date->setTime(0, 0, 0);

			if(!empty($maximumApply['daily']) && $maximumApply['daily'] > 0)
			{
				$fromDate = $date->format('U');
				$max = $maximumApply['daily'];
				$errorString = 'mjc_you_can_trigger_x_only_y_times_per_day';
			}

			if(!empty($maximumApply['weekly']) && $maximumApply['weekly'] > 0)
			{
				$dateWeek = $date;
				$dateWeek->modify(($dateWeek->format('w') === '0') ? 'monday last week' : 'monday this week');
				$fromDate = $dateWeek->format('U');
				$max = $maximumApply['weekly'];
				$errorString = 'mjc_you_can_trigger_x_only_y_times_per_week';
			}

			if(!empty($maximumApply['monthly']) && $maximumApply['monthly'] > 0)
			{
				$dateMonth = $date;
				$dateMonth->modify('first day of this month');
				$fromDate = $dateMonth->format('U');
				$max = $maximumApply['monthly'];
				$errorString = 'mjc_you_can_trigger_x_only_y_times_per_month';
			}

			if(!empty($maximumApply['yearly']) && $maximumApply['yearly'] > 0)
			{
				$dateYear = $date;
				$dateYear->modify('first day of january ' . $dateYear->format('Y'));
				$fromDate = $dateYear->format('U');
				$max = $maximumApply['yearly'];
				$errorString = 'mjc_you_can_trigger_x_only_y_times_per_year';
			}

			if(!empty($maximumApply['max']) && $maximumApply['max'] > 0)
			{
				if(isset($maximumApply['from_date'])){
					$fromDate = $maximumApply['from_date'];
				}else if(!empty($maximumApply['time_period'])){
					$fromDate = \XF::$time - $maximumApply['time_period'];
				}else{
					$fromDate = 0;
				}
				$max = $maximumApply['max'];
				$errorString = 'mjc_you_can_trigger_x_only_y_times_in_period_of_time';
			}

			if($fromDate !== false && $max){
				$transactionFinder = $this->finder('MJ\Credits:Transaction')
					->forEventId($event['event_id'])
					->forUser($params['target_user'])
					->byUser($params['trigger_user'])
					->byTransactionDate('>=', $fromDate);
				if(!empty($maximumApply['with_content'])){
					$transactionFinder
						->where('content_type', $params['content_type'])
						->where('content_id', $params['content_id']);
				}
				if($transactionFinder->total() >= $max){
					$error = \XF::phrase($errorString, [
						'event' => \XF::phrase($event['title_phrase']),
						'count' => $max,
					]);
					return false;
				}
			}
		}

		return true;
	}

	public function _validateEvent(array $event, $params, &$error = null)
	{
		return true;
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$errors = null)
	{
		if(!$options){
			$options = $this->getDefaultEventOptions();
		}else{
			$options = array_replace($this->getDefaultEventOptions(), $options);
		}
		return true;
	}

	public function beforeNewEvent($controller, Editor $editor, Event $event)
	{
		return;
	}

	public function onNewEvent($controller, Event $event)
	{
		return;
	}

	public function trigger($trigger, array $event, $params, &$error = null)
    {
        $visitor = \XF::visitor();

        $db = \XF::app()->db();

        $options = \XF::options();

        date_default_timezone_set('Asia/Hong_Kong');
        // Get today's timestamp
        $dt = new \DateTime('today', new \DateTimeZone($options->guestTimeZone));

        $today = $dt->getTimestamp();

        if (!$this->validateEvent($event, $params, $error)) {
            return false;
        }

        $optionCurrency = implode(',', $options->mjc_credits_every_day_limit_currency);

        $total = $db->fetchOne("
        SELECT SUM(amount) FROM xf_mjc_transaction
        WHERE transaction_status = 'completed' 
          AND transaction_date BETWEEN ? AND ? 
          AND user_id = ?
          AND amount > 0
          AND currency_id IN (?)",
            [$today, time(), $visitor->user_id, $optionCurrency]);

        if ($total >= $options->mjc_credits_every_day_limit) {
            return false;
        }

        $currencies = $this->app->container('mjc.currencies');

        $currency = $currencies[$event['currency_id']];


        $amount = $this->getAmount($event, $params, $error);

        if ($amount === false) {
            $error = \XF::phrase('mjc_invalid_amount');
            return false;
        }

        if (!empty($event['moderate_transactions']) && $amount > 0) {
            $params['pending_transaction'] = true;
        }

        if (!empty($params['multiple'])) {
            $amount = $amount * $params['multiple'];
        }

        if($visitor->get($currency['column']) > $currency['max_amount'] && $currency['max_amount'] != 0 && $amount > 0)
        {
            return \XF::app()->error()->logException(new \Exception(\XF::phrase('mjc_user_currency_exceeding_limit',['username' => \XF::visitor()->username, 'currency' => $currency['suffix']])));
        }

        return $trigger->addTriggerData($event, $params, $amount);
    }

	public function getAmount(array $event, &$params, &$error = null)
    {
        $amount = 0;
        if (!empty($params['amount'])) {
            $amount = $params['amount'];
        } else {
            if (!empty($event['options']['type']) && $event['options']['rand'] > 0) {
                if($event['options']['min_rand'] > 1)
                {
                    $randAmount = $event['options']['min_rand'];
                }else{
                    $randAmount = 1;
                }
                $amount = $event['amount'] + mt_rand($randAmount, $event['options']['rand']);
            } else {
                $amount = $event['amount'];
            }
        }
        return $amount;
    }

	public function transactionDescription($transaction)
	{
	}

	public function getAlertTemplate()
	{
	}

	public function getAlertData(Entity $event, &$data)
	{
	}

	public function getEditTemplateData(Entity $event, $position, &$templateName, &$params)
	{
	}

	public function renderEdit($event, $position)
	{
		$templateName = '';
		$params = array_replace($this->editParams, [
			'event' => $event
		]);
		$this->getEditTemplateData($event, $position, $templateName, $this->editParams);

		if (!$templateName)
		{
			return '';
		}
		return $this->app->templater()->renderTemplate(
			'admin:' . $templateName,
			$params
		);
	}

	/**
	 * @return \XF\App
	 */
	public function app()
	{
		return $this->app;
	}

	/**
	 * @return \XF\Db\AbstractAdapter
	 */
	public function db()
	{
		return $this->app->db();
	}

	/**
	 * @return \XF\Mvc\Entity\Manager
	 */
	public function em()
	{
		return $this->app->em();
	}

	/**
	 * @param string $repository
	 *
	 * @return \XF\Mvc\Entity\Repository
	 */
	public function repository($repository)
	{
		return $this->app->repository($repository);
	}

	/**
	 * @param $finder
	 *
	 * @return \XF\Mvc\Entity\Finder
	 */
	public function finder($finder)
	{
		return $this->app->finder($finder);
	}

	/**
	 * @param string $class
	 *
	 * @return \XF\Service\AbstractService
	 */
	public function service($class)
	{
		return call_user_func_array([$this->app, 'service'], func_get_args());
	}

    /**
     * @return \XF\Mvc\Entity\Repository
     */
	protected function getTransactionRepo()
	{
		return $this->repository('MJ\Credits:Transaction');
	}
}