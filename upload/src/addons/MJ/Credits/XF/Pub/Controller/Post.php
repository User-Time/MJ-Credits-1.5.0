<?php
namespace MJ\Credits\XF\Pub\Controller;

use MJ\Credits\ControllerPlugin\Credit;
use MJ\Credits\Entity\Reward;
use MJ\Credits\Service\Event\Trigger;
use XF\Mvc\ParameterBag;

class Post extends XFCP_Post
{
    protected $alert = false;

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionReward(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        /** @var Credit $creditPlugin */
        $creditPlugin = $this->plugin('MJ\Credits:Credit');

        $user = $post->User;
        $username = $post->User->username;
        $currencies = $creditPlugin->useableCurrencies('reward');
        if (!$currencies) {
            return $this->noPermission();
        }

        $input = $this->filter([
            'amount' => 'unum',
            'currency_id' => 'uint',
        ]);

        if (!$input['currency_id']) {
            $currency = reset($currencies);
            $input['currency_id'] = $currency['currency_id'];
        }

        if ($this->isPost()) {
            if (!isset($currencies[$input['currency_id']])) {
                return $this->error(\XF::phrase('mjc_requested_currency_not_found'));
            }

            $currency = $currencies[$input['currency_id']];

            $currencyId = $currency['currency_id'];

            $userNotFound = true;

            $visitor = \XF::visitor();

            if ($username) {

                if ($user && $user->user_id == $visitor->user_id) {
                    return $this->error(\XF::phrase('mjc_you_may_not_reward_yourself'));
                }
                if ($user) {
                    $userNotFound = false;
                }
            }

            if ($userNotFound) {
                return $this->error(\XF::phrase('requested_user_not_found'));
            }

            if (!$input['amount'] || $input['amount'] <= 0) {
                return $this->error(\XF::phrase('mjc_please_enter_valid_amount'));
            }

            /** @var Trigger $trigger */
            $trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);

            $sendAmount = $input['amount'];
            $event = $currency['event'];

            $fee = $this->repository('MJ\Credits:Event')->calculateFee($sendAmount, $event);

            if ($visitor->get($currency['column']) < ($sendAmount + $fee)) {
                return $this->error(\XF::phrase('mjc_not_enough_x_to_reward', [
                    'x' => \MJ\Credits\Util\Money::formatAmount(($sendAmount + $fee), $currencyId)
                ]));
            }

            $trigger->triggerEvent('reward', $visitor->user_id, [
                'amount' => -($sendAmount + $fee),
                'content_id' => $post->post_id,
                'content_type' => 'reward',
                'currency' => $currency,
                'event' => $event,
                'extra_data' => [
                    'type' => 'reward',
                    'send_user_id' => $visitor->user_id,
                    'receive_user_id' => $user->user_id,
                ]
            ], $error);

            $currencies = $creditPlugin->useableCurrencies('reward');

            $trigger->triggerEvent('reward', $user->user_id, [
                'amount' => $sendAmount,
                'content_id' => $post->post_id,
                'content_type' => 'reward',
                'currency' => $currency,
                'extra_data' => [
                    'type' => 'receive',
                    'send_user_id' => $visitor->user_id,
                    'receive_user_id' => $user->user_id,
                ]
            ], $error);

            $thread = $post->Thread;

            /** @var Reward $reward */
            $reward = $this->em()->create('MJ\Credits:Reward');
            $reward->post_id = $post->post_id;
            $reward->currency_id = $currencyId;
            $reward->user_id = $visitor->user_id;
            $reward->amount = $sendAmount;
            $reward->save();

            $trigger->fire();

            return $this->redirect($this->getDynamicRedirect($this->buildLink('threads', $thread), false));
        } else {
            $viewParams = [
                'currencies' => $currencies,
                'post' => $post,
                'username' => $username,
                'amount' => $input['amount'],
                'currencyId' => $input['currency_id'],
                'user' => $user
            ];

            return $this->view('XF:Post\Reward', 'mjc_reward', $viewParams);
        }
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionRedEnvelope(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);
        $user = $post->User;
        $username = $post->User->username;

        /** @var Credit $creditPlugin */
        $creditPlugin = $this->plugin('MJ\Credits:Credit');
        $currencies = $creditPlugin->useableCurrencies('red_envelope');

        $fromUser = \XF::visitor();
        if (!$currencies) {
            return $this->noPermission();
        }

        $input = $this->filter([
            'username' => 'str',
            'amount' => 'unum',
            'currency_id' => 'uint',
            'message' => 'str',
        ]);

        $visitor = \XF::visitor();

        if (!$input['currency_id']) {
            $currency = reset($currencies);
            $input['currency_id'] = $currency['currency_id'];
        }

        $redEnvelopFinder = \XF::finder('MJ\Credits:RedEnvelope')
            ->where('post_id', '=', $post->post_id)
            ->where('from_user_id', '=', $visitor->user_id)
            ->where('status', '=', 'pending')
            ->fetchOne();

        if (!$redEnvelopFinder) {
            if ($this->isPost()) {
                if (!isset($currencies[$input['currency_id']])) {
                    return $this->error(\XF::phrase('mjc_requested_currency_not_found'));
                }

                $currency = $currencies[$input['currency_id']];
                $currencyId = $currency['currency_id'];

                $userNotFound = true;

                if ($username) {
                    $user = $this->em()->findOne('XF:User', ['username' => $username]);
                    if ($user && $user->user_id == $visitor->user_id) {
                        return $this->error(\XF::phrase('mjc_you_may_not_send_credit_yourself'));
                    }
                    if ($user) {
                        $userNotFound = false;
                    }
                }

                if ($userNotFound) {
                    return $this->error(\XF::phrase('requested_user_not_found'));
                }

                if (!$input['amount'] || $input['amount'] <= 0) {
                    return $this->error(\XF::phrase('mjc_please_enter_valid_amount'));
                }

                $sendAmount = $input['amount'];

                if ($visitor->get($currency['column']) < $sendAmount) {
                    return $this->error(\XF::phrase('mjc_not_enough_x_to_red_envelope', [
                        'x' => \MJ\Credits\Util\Money::formatAmount($sendAmount, $currencyId)
                    ]));
                }

                $redEnvelope = $this->em()->create('MJ\Credits:RedEnvelope');
                $redEnvelope->post_id = $post->post_id;
                $redEnvelope->user_id = $user->user_id;
                $redEnvelope->from_user_id = $fromUser->user_id;
                $redEnvelope->currency_id = $currencyId;
                $redEnvelope->start_date = time();
                $redEnvelope->end_date = time() + 24 * 86400;
                $redEnvelope->message = $input['message'];
                $redEnvelope->amount = $input['amount'];
                $redEnvelope->save();

                $trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);

                $event = $currency['event'];

                $fee = $this->repository('MJ\Credits:Event')->calculateFee($sendAmount, $event);

                $trigger->triggerEvent('red_envelope', $visitor->user_id, [
                    'amount' => -($sendAmount + $fee),
                    'content_id' => $post->post_id,
                    'content_type' => 'red_envelope',
                    'currency' => $currency,
                    'extra_data' => [
                        'type' => 'sendRedEnvelope',
                        'user_id' => $user->user_id,
                        'from_user_id' => $visitor->user_id,
                    ]
                ]);

                $trigger->fire();

                $alertRed = \XF::finder('MJ\Credits:RedEnvelope')
                    ->where('post_id', '=', $post->post_id)
                    ->where('status', '=', 'pending')
                    ->fetchOne();

                $alertRepo = $this->repository('XF:UserAlert');

                if (!empty($alertRed)) {

                    $userId = $alertRed->user_id;

                    $user = $this->assertUserExists($userId);

                    $extra = [
                        'title' => $post->Thread->title,
                        'prefix_id' => $post->Thread->prefix_id,
                        'targetLink' => $this->app->router('public')->buildLink('nopath:posts', $post),
                        'user' => $user->username
                    ];
                    $alertRepo->alert(
                        $user,
                        $alertRed->from_user_id,
                        '',
                        'user',
                        $alertRed->from_user_id,
                        'red_envelope_from_alert', $extra);
                }

                /** @var \XF\Entity\Thread $thread */
                $thread = $post->Thread;

                return $this->redirect($this->getDynamicRedirect($this->buildLink('threads',$thread), false));
            }
        } else {
            return $this->error(\XF::phrase('mjc_you_have_already_sent_a_red_envelope'));
        }

        $viewParams = [
            'currencies' => $currencies,
            'post' => $post,
            'user' => $user,
            'username' => $username
        ];

        return $this->view('XF:Post\RedEnvelope', 'mjc_red_envelope', $viewParams);
    }

    public function actionRedEnvelopeList(ParameterBag $params)
    {
        return $this->view('', 'mjc_red_envelope_list');
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionOpenRedEnvelope(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        $visitor = \XF::visitor();

        $redEnvelopFinder = \XF::finder('MJ\Credits:RedEnvelope')
            ->where('post_id', '=', $post->post_id)
            ->where('status', '=', 'pending')
            ->fetchOne();

        /** @var Credit $creditPlugin */
        $creditPlugin = $this->plugin('MJ\Credits:Credit');

        $currencies = $creditPlugin->useableCurrencies('red_envelope');

        if (!$currencies) {
            return $this->noPermission();
        }

        if ($redEnvelopFinder) {
            if ($visitor->user_id == $redEnvelopFinder->user_id) {
                if ($this->isPost()) {
                    $redEnvelopeAmount = $redEnvelopFinder->amount;
                    /** @var Trigger $trigger */
                    $trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);

                    $currency = $currencies[$redEnvelopFinder->Currency->currency_id];

                    $trigger->triggerEvent('red_envelope', $visitor->user_id, [
                        'amount' => $redEnvelopeAmount,
                        'content_id' => $post->post_id,
                        'content_type' => 'red_envelope',
                        'currency' => $currency,
                        'extra_data' => [
                            'type' => 'openRedEnvelope',
                            'user_id' => $visitor->user_id,
                            'from_user_id' => $redEnvelopFinder->from_user_id,
                        ]
                    ], $error);

                    $trigger->fire();

                    $redEnvelopFinder->delete();

                    /** @var \XF\Entity\Thread $thread */
                    $thread = $post->Thread;

                    return $this->redirect($this->getDynamicRedirect($this->buildLink('threads',$thread), false));
                }
            } else {
                return $this->error(\XF::phrase('mjc_not_your_red_envelope'));
            }
            return $this->view('XF:Post\openRedEnvelope');
        } else {
            return $this->error(\XF::phrase('mjc_have_already_received_the_red_envelope'));
        }
    }

    public function actionDeleteBonus(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        $threadId = $post->thread_id;

        if ($this->isPost()) {

            $bonusFinder = \XF::finder('MJ\Credits:Bonus')
                ->where('thread_id', '=', $threadId)
                ->where('total_point', '>', 0)
                ->fetchOne();
            if (!$bonusFinder) {
                return false;
            }
            $bonusFinder->delete();

            /** @var Credit $creditPlugin */
            $creditPlugin = $this->plugin('MJ\Credits:Credit');
            $currencies = $creditPlugin->useableCurrencies('bonus');

            if (!$currencies) {
                return $this->noPermission();
            }

            /** @var Trigger $trigger */
            $trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);

            $currency = $currencies[$bonusFinder->Currency->currency_id];

            $trigger->triggerEvent('bonus', $bonusFinder->user_id, [
                'amount' => $bonusFinder->total_point,
                'content_id' => $post->post_id,
                'content_type' => 'sendBonusBalance',
                'currency' => $currency,
                'extra_data' => [
                    'type' => 'sendBonusBalance',
                    'user_id' => $bonusFinder->user_id
                ]
            ], $error);

            $trigger->fire();

            /** @var \XF\Entity\Thread $thread */
            $thread = $post->Thread;

            return $this->redirect(
                $this->getDynamicRedirect($this->buildLink('threads', $thread), false)
            );
        }
        else
        {
            $viewParams = [
                'post' => $post,
                'thread' => $post->Thread,
            ];
            return $this->view('XF:Post\DeleteBonus', 'mjc_bonus_delete', $viewParams);
        }

    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply
     * @throws \XF\Db\Exception
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionOpenBonus(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);
        $threadId = $post->thread_id;

        $visitor = \XF::visitor();
        $bonusFinder = \XF::finder('MJ\Credits:Bonus')
            ->where('thread_id', '=', $threadId)
            ->where('total_point', '>', 0)
            ->fetchOne();

        /** @var Credit $creditPlugin */
        $creditPlugin = $this->plugin('MJ\Credits:Credit');
        $currencies = $creditPlugin->useableCurrencies('bonus');

        if (!$currencies) {
            return $this->noPermission();
        }

        $receivedUser = $this->finder('MJ\Credits:Transaction')
            ->where('content_id', $threadId)
            ->where('content_type', '=', 'bonus')
            ->where('user_id', $visitor->user_id)
            ->fetchOne();

        if ($visitor) {
            if ($bonusFinder) {
	            $oldReceivedUserId = $bonusFinder->extra_data;
                if (in_array($visitor->user_id, $oldReceivedUserId) || $receivedUser) {
                    return $this->error(\XF::phrase('mjc_credits_you_have_received_a_red_envelope'));
                } else {
                    if ($this->isPost()) {
                        $bonusMoney = $bonusFinder->total_point;
                        $bonusNum = $bonusFinder->total_people;

                        $arr = array_slice(self::hongbao($bonusMoney, $bonusNum), -1, 1);
                        $getAmount = current($arr);

                        $balance = $bonusMoney - $getAmount;

                        \XF::db()->query("
                                UPDATE                                
                                 xf_mjc_bonus 
                                SET total_point = ?, 
                                    total_people = ?
                                WHERE thread_id = '$threadId'
                                      ", [$balance, $bonusNum - 1]);

                        /** @var Trigger $trigger */
                        $trigger = $this->app()->service('MJ\Credits:Event\Trigger', false);

                        $currency = $currencies[$bonusFinder->currency_id];

                        $trigger->triggerEvent('bonus', $visitor->user_id, [
                            'amount' => $getAmount,
                            'content_id' => $post->Thread->thread_id,
                            'content_type' => 'bonus',
                            'currency' => $currency,
                            'event' => $currency['event'],
                            'extra_data' => [
                                'send_user_id' => $post->Thread->user_id,
                                'receive_user_id' => $visitor->user_id,
                            ]
                        ], $error);

                        $trigger->fire();

                        /** @var \MJ\Credits\Entity\OpenBonusTemp $openBonus */
                        $openBonus = $this->em()->create('MJ\Credits:OpenBonusTemp');
                        $openBonus->user_id = $visitor->user_id;
                        $openBonus->total_point = $getAmount;
                        $openBonus->thread_id = $post->thread_id;
                        $openBonus->currency_id = $bonusFinder->currency_id;
                        $openBonus->save();

                        if(\XF::options()->openBonusPostNew) {
                            $input = $this->filter([
                                'bonus_message' => 'str'
                            ]);

                            $basePosition = \XF::app()->db()->fetchOne("
				                       SELECT position
				                       FROM xf_post
				                       WHERE thread_id = ?
				                       ORDER BY `post_date` DESC
			                            ", $post->thread_id);

                            $newpost = $this->em()->create('XF:Post');
                            $newpost->thread_id = $post->thread_id;
                            $newpost->user_id = $visitor->user_id;
                            $newpost->username = $visitor->username;
                            $newpost->post_date = time();
                            $newpost->message = $input['bonus_message'] . "\r\n" . "\r\n" . $visitor->username . '领取了您的红包获得了' . $getAmount . $bonusFinder->Currency->title;
                            $newpost->ip_id = $post->ip_id;
                            $newpost->position = $basePosition + 1;
                            $newpost->last_edit_date = 0;

                            $newpost->save();
                        }

                        /** @var \XF\Entity\Thread $thread */
                        $thread = $post->Thread;
                        return $this->redirect($this->getDynamicRedirect($this->buildLink('threads', $thread), false));
                    }
                }
            }
            return $this->error('来晚啦，红包领完了！');
        }
        return $this->error('登录后再领吧');
    }

  public function hongbao($money,$number,$ratio = 0.99)
  {
      $res = array(); //结果数组
      $min = ($money / $number) * (1 - $ratio);   //最小值
      $max = ($money / $number) * (1 + $ratio);   //最大值
      /*--- 第一步：分配低保 ---*/
      for ($i = 0; $i < $number; $i++) {
          $res[$i] = $min;
      }
      $money = $money - $min * $number;
      /*--- 第二步：随机分配 ---*/
      $randRatio = 100;
      $randMax = ($max - $min) * $randRatio;
      for ($i = 0; $i < $number; $i++) {
          //随机分钱
          $randRes = mt_rand(0, $randMax);
          $randRes = $randRes / $randRatio;
          if ($money >= $randRes) { //余额充足
              $res[$i] += $randRes;
              $money -= $randRes;
          } elseif ($money > 0) {     //余额不足
              $res[$i] += $money;
              $money -= $money;
          } else {                   //没有余额
              break;
          }
      }
      /*--- 第三步：平均分配上一步剩余 ---*/
      if ($money > 0) {
          $avg = $money / $number;
          for ($i = 0; $i < $number; $i++) {
              $res[$i] += $avg;
          }
          $money = 0;
      }
      /*--- 第四步：打乱顺序 ---*/
      shuffle($res);
      /*--- 第五步：格式化金额(可选) ---*/
      foreach ($res as $k => $v) {
          //两位小数，不四舍五入
          preg_match('/^\d+(\.\d{1,2})?/', $v, $match);
          $match[0] = number_format($match[0], 2);
          $res[$k] = $match[0];
      }

      return $res;
  }

    public function actionMarkSolution(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        if (!$post->canMarkAsQuestionSolution($error)) {
            return $this->noPermission($error);
        }

        $thread = $post->Thread;
        $existingSolution = $thread->Question->Solution ?? null;

        if (!$existingSolution) {
            $type = 'add';
        } else if ($post->post_id == $existingSolution->post_id) {
            $type = 'remove';
        } else {
            $type = 'replace';
        }

        // for replacement cases, we want to force an explicit confirmation, even if receiving a post request
        // (which might come from JS)

        if (
            $this->isPost()
            && ($type != 'replace' || $this->filter('confirm', 'bool'))
        ) {
            /** @var \XF\Service\ThreadQuestion\MarkSolution $markSolution */
            $markSolution = $this->service('XF:ThreadQuestion\MarkSolution', $thread);

            if ($type == 'remove') {
                $markSolution->unmarkSolution();
                $switchKey = 'removed';

                $rewardAmount = $thread->reward_amount;

                if ($rewardAmount > 0) {
                    /** @var Credit $creditPlugin */
                    $creditPlugin = $this->plugin('MJ\Credits:Credit');
                    $currencies = $creditPlugin->useableCurrencies('offerReward');

                    if (!$currencies) {
                        return $this->noPermission();
                    }

                    $currency = $currencies[$post->Thread->reward_currency_id];

                    /** @var Trigger $trigger */
                    $trigger = \XF::app()->service('MJ\Credits:Event\Trigger', false);

                    $event = $currency['event'];

                    $trigger->triggerEvent('offerReward', $post->user_id, [
                        'amount' => -$rewardAmount,
                        'content_id' => $thread->thread_id,
                        'content_type' => 'offerReward',
                        'currency' => $currency,
                        'event' => $event,
                        'extra_data' => [
                            'type' => 'offer_reward',
                            'send_user_id' => $thread->user_id,
                            'receive_user_id' => $post->user_id,
                        ]
                    ], $error);

                    $trigger->fire();
                }
            } else {
                $markSolution->markSolution($post);
                $switchKey = $existingSolution ? "replaced:{$existingSolution->post_id}" : 'marked';

                $rewardAmount = $thread->reward_amount;
                if ($rewardAmount > 0) {
                    /** @var Credit $creditPlugin */
                    $creditPlugin = $this->plugin('MJ\Credits:Credit');
                    $currencies = $creditPlugin->useableCurrencies('offerReward');

                    if (!$currencies) {
                        return $this->noPermission();
                    }

                    if ($post->Thread->reward_currency_id != 0) {
                        $currency = $currencies[$post->Thread->reward_currency_id];
                    }

                    /** @var Trigger $trigger */
                    $trigger = \XF::app()->service('MJ\Credits:Event\Trigger', false);

                    $event = $currency['event'];

                   $trigger->triggerEvent('offerReward', $post->user_id, [
                        'amount' => $rewardAmount,
                        'content_id' => $thread->thread_id,
                        'content_type' => 'offerReward',
                        'currency' => $currency,
                        'event' => $event,
                        'extra_data' => [
                            'type' => 'offer_reward',
                            'send_user_id' => $thread->user_id,
                            'receive_user_id' => $post->user_id,
                        ]
                    ], $error);

                    $trigger->fire();
                }
            }

            $reply = $this->redirect($this->buildLink('posts', $post));
            $reply->setJsonParam('switchKey', $switchKey);

            return $reply;
        }

        return parent::actionMarkSolution($params);
    }

    public function actionMoreReceiveUser(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        $page = $this->filterPage();
        $perPage = 40;

        $finder = $this->finder('MJ\Credits:OpenBonusTemp')
            ->where('thread_id', $post->thread_id)
            ->with('User')
            ->limitByPage($page, $perPage);

       $users = $finder->fetch();
       $total = $finder->total();

        $viewParams = [
            'users' => $users,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
        ];
        return $this->view('XF:Post\MoreReceiveUserList', 'mjc_more_receive_user_list', $viewParams);
    }

    public function actionMoreRewardUser(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        $page = $this->filterPage();
        $perPage = 40;

        $finder = $this->finder('MJ\Credits:Reward')
            ->where('thread_id', $post->thread_id)
            ->with('User')
            ->limitByPage($page, $perPage);

        $users = $finder->fetch();
        $total = $finder->total();

        $viewParams = [
            'users' => $users,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
        ];
        return $this->view('XF:Post\MoreRewardUserList', 'mjc_more_reward_user_list', $viewParams);
    }

    public function actionMoreChargePurchasers(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        $page = $this->filterPage();
        $perPage = 40;

        $finder = $this->finder('MJ\Credits:ChargePurchase')
            ->where('content_id', $post->post_id)
            ->with('User')
            ->limitByPage($page, $perPage);

        $users = $finder->fetch();
        $total = $finder->total();

        $viewParams = [
            'users' => $users,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
        ];
        return $this->view('XF:Post\MoreChargePurchasersList', 'mjc_more_charge_purchasers_list', $viewParams);
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
        return $this->assertRecordExists('XF:User', $id, $with, $phraseKey);
    }

    /**
     * @return \XF\Mvc\Entity\Repository
     */
    protected function getTransactionRepo()
    {
        return $this->repository('MJ\Credits:Transaction');
    }
}