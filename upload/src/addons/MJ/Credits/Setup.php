<?php

namespace MJ\Credits;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends \XF\AddOn\AbstractSetup
{
    use \XF\AddOn\StepRunnerInstallTrait;
    use \XF\AddOn\StepRunnerUpgradeTrait;
    use \XF\AddOn\StepRunnerUninstallTrait;

    // ################################ INSTALLATION ################################

    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $closure)
        {
            $sm->createTable($tableName, $closure);
        }
    }

    public function installStep2()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('mjc_1', 'decimal', '19,8')->unsigned(false)->setDefault('0');
            $table->addColumn('mjc_2', 'decimal', '19,8')->unsigned(false)->setDefault('0');
            $table->addColumn('mjc_credits_last_daily', 'int')->setDefault(0);
        });
    }

    public function installStep3()
    {
        foreach ($this->getData() as $dataSql)
        {
            $this->query($dataSql);
        }

        foreach ($this->getDefaultWidgetSetup() as $widgetKey => $widgetFn)
        {
            $widgetFn($widgetKey);
        }
    }

    public function installStep4()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_thread', function(Alter $table)
        {
            $table->addColumn('reward', 'tinyint', 3)->setDefault(0);
            $table->addColumn('reward_amount', 'decimal', '19,8')->unsigned(false)->setDefault(0);
            $table->addColumn('reward_currency_id', 'int', 10)->setDefault(0);
            $table->addColumn('offer_reward', 'tinyint', 3)->setDefault(0);
        });
    }

    public function installStep5()
    {
        $this->query("
			REPLACE INTO `xf_payment_provider`
				(`provider_id`, `provider_class`, `addon_id`)
			VALUES
				('mjc_credits', 'MJ\\\\Credits:Credits', X'4D4A2F43726564697473')
		");
    }

    public function postInstall(array &$stateChanges)
    {
        if ($this->applyDefaultPermissions())
        {
            // since we're running this after data imports, we need to trigger a permission rebuild
            // if we changed anything
            $this->app->jobManager()->enqueueUnique(
                'permissionRebuild',
                'XF:PermissionRebuild',
                [],
                false
            );
        }

        \XF::repository('MJ\Credits:Event')->rebuildEventDefinitionCache();
        \XF::repository('MJ\Credits:Event')->rebuildEventCache();
        \XF::repository('MJ\Credits:Currency')->rebuildCurrencyCache();
    }

    // ################################ UPGRADE ################################

    public function upgrade1000031Step1()
    {
        $this->query("
			UPDATE `xf_mjc_event_definition`
			SET
				`definition_class`='MJ\\\\Credits\\\\Event\\\\Attachment'
			WHERE
				definition_id = 'attachment_download' OR definition_id = 'attachment_download_receive'
		");
    }

    public function upgrade1000032Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_mjc_transaction', function(Alter $table)
        {
            $table->addColumn('transaction_hash', 'varbinary', 64);
        });
    }

    public function upgrade1000033Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_mjc_transaction', function(Alter $table)
        {
            $table->renameColumn('is_pending', 'update_user_credit');
            $table->renameColumn('complete_date', 'last_update');
            $table->addColumn('is_hidden', 'tinyint')->setDefault(0);
        });
    }

    public function upgrade1000034Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_mjc_transaction', function(Alter $table)
        {
            $table->addColumn('transaction_status', 'varchar', 25)->setDefault('completed');
        });
    }

    public function upgrade1000200Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('mjc_credits_last_daily', 'int')->setDefault(0);
        });
    }

    public function upgrade1000600Step1()
    {
        $sm = $this->schemaManager();

        $sm->createTable('xf_mjc_credits_charge', function(Create $table)
        {
            $table->addColumn('post_id', 'int');
            $table->addColumn('content_hash', 'char', 32);
            $table->addColumn('cost', 'double')->unsigned(false)->setDefault(0);
            $table->addPrimaryKey(['post_id', 'content_hash']);
        });
    }

    public function upgrade1000600Step2()
    {
        $sm = $this->schemaManager();

        $sm->createTable('xf_mjc_credits_charge_purchase', function(Create $table)
        {
            $table->addColumn('post_id', 'int');
            $table->addColumn('content_hash', 'char', 32);
            $table->addColumn('user_id', 'int');
            $table->addPrimaryKey(['post_id', 'content_hash', 'user_id']);
        });

    }

    public function upgrade1000600Step3()
    {
        $this->query("
		INSERT IGNORE INTO `xf_mjc_event_definition`
				(`definition_id`, `definition_class`, `addon_id`, `display_order`)
			VALUES
				('content', 'MJ\\\\Credits\\\\Event\\\\EventHandler', 'MJ/Credits', 8200)
		");
    }

    public  function upgrade1001000Step1()
    {
        $this->query("
		INSERT IGNORE INTO `xf_mjc_event_definition`
				(`definition_id`, `definition_class`, `addon_id`, `display_order`)
			VALUES
				('reward', 'MJ\\\\Credits\\\\Event\\\\EventHandler', 'MJ/Credits', 2000)
		");
    }
    
    public function upgrade1001000Step2()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_mjc_transaction', function(Alter $table)
        {
            $table->addColumn('post_id', 'int', 10)->setDefault(0);
        });
    }

    public function upgrade1001000Step3()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_thread', function(Alter $table)
        {
            $table->addColumn('reward', 'tinyint', 3)->setDefault(0);
        });
    }

    public function upgrade1010300Step1()
    {
        $sm = $this->schemaManager();

        $sm->createTable('xf_mjc_red_envelope', function (Create $table) {
            $table->addColumn('user_id', 'int');
            $table->addColumn('from_user_id', 'int');
            $table->addColumn('post_id', 'int');
            $table->addColumn('start_date', 'int')->setDefault(0);
            $table->addColumn('end_date', 'int')->setDefault(0);
            $table->addColumn('status', 'enum')->values(['pending', 'claimed'])->setDefault('pending');
            $table->addColumn('message','text');
            $table->addColumn('currency_id', 'int');
            $table->addColumn('amount', 'decimal', '19,8')->unsigned(false)->setDefault('0');
        });
    }

    public function upgrade1010300Step2()
    {
        $this->query("
		INSERT IGNORE INTO `xf_mjc_event_definition`
				(`definition_id`, `definition_class`, `addon_id`, `display_order`)
			VALUES
				('red_envelope', 'MJ\\\\Credits\\\\Event\\\\EventHandler', 'MJ/Credits', 2100)
		");
    }

    public function upgrade1010400Step1()
    {
        $sm = $this->schemaManager();

        $sm->createTable('xf_mjc_bonus', function(Create $table)
        {
            $table->addColumn('bonus_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('currency_id', 'int');
            $table->addColumn('total_point', 'decimal', '19,8')->unsigned(false)->setDefault('0');
            $table->addColumn('total_people', 'int');
            $table->addColumn('thread_id', 'int');
            $table->addColumn('extra_data', 'varbinary',255);
            $table->addColumn('message','text');
            $table->addPrimaryKey('bonus_id');
        });
    }

    public function upgrade1010500Step1()
    {
        $this->query("
		INSERT IGNORE INTO `xf_mjc_event_definition`
				(`definition_id`, `definition_class`, `addon_id`, `display_order`)
			VALUES
				('bonus', 'MJ\\\\Credits\\\\Event\\\\EventHandler', 'MJ/Credits', 1070)
		");
    }

    public function upgrade1010600Step1()
    {
        $this->query("
			UPDATE `xf_mjc_event_definition`
			SET
				`definition_class`='MJ\\\\Credits\\\\Event\\\\EventHandler'
			WHERE
				definition_id = 'content'
		");

        $this->query("
			UPDATE `xf_mjc_event_definition`
			SET
				`definition_class`='MJ\\\\Credits\\\\Event\\\\EventHandler'
			WHERE
				definition_id = 'reward'
		");

        $this->query("
			UPDATE `xf_mjc_event_definition`
			SET
				`definition_class`='MJ\\\\Credits\\\\Event\\\\EventHandler'
			WHERE
				definition_id = 'red_envelope'
		");

        $this->query("
			UPDATE `xf_mjc_event_definition`
			SET
				`definition_class`='MJ\\\\Credits\\\\Event\\\\EventHandler'
			WHERE
				definition_id = 'bonus'
		");
    }

    public function upgrade1010600Step2()
    {
        $sm = $this->schemaManager();

        $sm->createTable('xf_mjc_open_bonus_temp', function(Create $table)
        {
            $table->addColumn('bonus_temp_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('currency_id', 'int');
            $table->addColumn('total_point', 'decimal', '19,8')->unsigned(false)->setDefault('0');
            $table->addColumn('thread_id', 'int');
            $table->addPrimaryKey('bonus_temp_id');
        });
    }

    /**
     * @throws \XF\Db\Exception
     */
    public function upgrade1010700Step1()
    {
        $this->query("
			REPLACE INTO `xf_payment_provider`
				(`provider_id`, `provider_class`, `addon_id`)
			VALUES
				('mjc_credits', 'MJ\\\\Credits:Credits', X'4D4A2F43726564697473')
		");
    }

    public function upgrade1010700Step2()
    {
        $this->query("
		INSERT IGNORE INTO `xf_mjc_event_definition`
				(`definition_id`, `definition_class`, `addon_id`, `display_order`)
			VALUES
				('payment', 'MJ\\\\Credits\\\\Event\\\\EventHandler', X'4D4A2F43726564697473', 2100)
		");
    }

    // upgrade 1020300
    public function upgrade1020300Step1()
    {
        $this->query("
		INSERT IGNORE INTO `xf_mjc_event_definition`
				(`definition_id`, `definition_class`, `addon_id`, `display_order`)
			VALUES
				('offerReward', '', 'MJ/Credits', 4800)
		");

        $sm = $this->schemaManager();

        $sm->alterTable('xf_thread', function(Alter $table)
        {
            $table->addColumn('reward_amount', 'decimal', '19,8')->unsigned(false)->setDefault(0);
            $table->addColumn('reward_currency_id', 'int', 10)->setDefault(0);
            $table->addColumn('offer_reward', 'tinyint', 3)->setDefault(0);
        });
    }

    public function upgrade1020500Step1()
    {
        $defaultValue = [
            'enabled' => 1,
            'right_position' => false,
            'right_text' => true
        ];

        $this->query("
			UPDATE xf_option
			SET default_value = ?
			WHERE option_id = 'mjc_credits_navbar'
		", json_encode($defaultValue));

        $navbarDefaults = json_decode($this->db()->fetchOne("
			SELECT option_value
			FROM xf_option
			WHERE option_id = 'mjc_credits_navbar'
		"), true);

        $update = false;
        foreach (array_keys($defaultValue) AS $key)
        {
            if (!isset($navbarDefaults[$key]))
            {
                $update = true;
                $navbarDefaults[$key] = $defaultValue[$key];
            }
        }

        if ($update)
        {
            $this->query("
				UPDATE xf_option
				SET option_value = ?
				WHERE option_id = 'mjc_credits_navbar'
			", json_encode($navbarDefaults));
        }
    }

    public function upgrade1020500Step2()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_mjc_currency', function(Alter $table)
        {
            $table->addColumn('wallet_popup', 'tinyint', 3)->setDefault(0);
        });
    }

    public function upgrade1020600Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_mjc_credits_charge', function (Alter $table)
        {
            $table->addColumn('content_type', 'varbinary', 25)->after('post_id');
            $table->addColumn('content_id', 'int')->after('content_type');
        });

        $sm->alterTable('xf_mjc_credits_charge_purchase', function (Alter $table)
        {
            $table->addColumn('content_type', 'varbinary', 25)->after('post_id');
            $table->addColumn('content_id', 'int')->after('content_type');
        });
    }
    
    public function upgrade1020600Step2()
    {
        $this->executeUpgradeQuery("
			UPDATE `xf_mjc_credits_charge`
			SET `content_type` = 'post'
		");

        $this->executeUpgradeQuery("
			UPDATE `xf_mjc_credits_charge`
			SET `content_id` = `post_id`
		");

        $this->executeUpgradeQuery("
			UPDATE `xf_mjc_credits_charge_purchase`
			SET `content_type` = 'post'
		");

        $this->executeUpgradeQuery("
			UPDATE `xf_mjc_credits_charge_purchase`
			SET `content_id` = `post_id`
		");
    }
    
    public function upgrade1020600Step3()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_mjc_credits_charge', function (Alter $table)
        {
            $table->dropPrimaryKey();
            $table->dropColumns(['post_id']);
            $table->addPrimaryKey(['content_type', 'content_id', 'content_hash']);
        });

        $sm->alterTable('xf_mjc_credits_charge_purchase', function (Alter $table)
        {
            $table->dropPrimaryKey();
            $table->dropColumns(['post_id']);
            $table->addPrimaryKey(['content_type', 'content_id', 'content_hash', 'user_id']);
        });
    }

    public function upgrade1020920Step1()
    {
        $this->createWidget(
            'mjc_sidenav_clock_in_every_day',
            'mjc_clock_in_every_day',
            [
                'positions' => [
                    'forum_overview_wrapper' => 10
                ]
            ]
        );

        $this->query("
		INSERT IGNORE INTO `xf_mjc_event_definition`
				(`definition_id`, `definition_class`, `addon_id`, `display_order`)
			VALUES
				('daily_clock', '\\\\MJ\\\\Credits\\\\Event\\\\EventHandler', 'MJ/Credits', 1080)
		");
    }

    public function upgrade1030000Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_mjc_currency', function(Alter $table) {
            $table->addColumn('positive', 'tinyint')->setDefault(1)->after('decimal_places');
        });
    }

    public function upgrade1030400Step1()
    {
        $sm = $this->schemaManager();

        $sm->createTable('xf_mjc_reward', function(Create $table) {
            $table->addColumn('reward_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('currency_id', 'int');
            $table->addColumn('amount', 'decimal', '19,8')->unsigned(false)->setDefault('0');
            $table->addColumn('post_id', 'int');
            $table->addColumn('reward_date', 'int')->setDefault(0);
            $table->addPrimaryKey('reward_id');
        });
    }

    public function upgrade1030500Step1()
    {
        $this->schemaManager()->alterTable('xf_mjc_reward', function (Alter $table)
        {
            $table->renameColumn('thread_id', 'post_id');
        });
    }

    public function upgrade1030600Step1()
    {
        $this->query("
		INSERT IGNORE INTO `xf_mjc_event_definition`
				(`definition_id`, `definition_class`, `addon_id`, `display_order`)
			VALUES
				('adjust', '\\\\MJ\\\\Credits\\\\Event\\\\EventHandler', 'MJ/Credits', 1000)
		");
    }

	public function upgrade1040000Step1()
	{
		$this->query("
		INSERT IGNORE INTO `xf_mjc_event_definition`
				(`definition_id`, `definition_class`, `addon_id`, `display_order`)
			VALUES
				('fine', '\\\\MJ\\\\Credits\\\\Event\\\\EventHandler', 'MJ/Credits', 10)
		");
	}

    public function upgrade1040200Step1()
    {
        $this->schemaManager()->alterTable('xf_mjc_red_envelope', function (Alter $table)
        {
            $table->addColumn('red_envelope_id', 'int')->autoIncrement();
        });
    }

    public function upgrade1040700Step1()
    {
        $this->query("
		INSERT IGNORE INTO `xf_mjc_event_definition`
				(`definition_id`, `definition_class`, `addon_id`, `display_order`)
			VALUES
				('soft_post_delete', '\\\\MJ\\\\Credits\\\\Event\\\\EventHandler', 'MJ/Credits', 8020)
		");
    }

    public function upgrade1040800Step1()
    {
        $this->query("
		INSERT IGNORE INTO `xf_mjc_event_definition`
				(`definition_id`, `definition_class`, `addon_id`, `display_order`)
			VALUES
				('soft_thread_delete', '\\\\MJ\\\\Credits\\\\Event\\\\EventHandler', 'MJ/Credits', 6020)
		");
    }

    public function upgrade1050000Step1()
    {
        $this->schemaManager()->alterTable('xf_mjc_currency', function (Alter $table)
        {
            $table->addColumn('max_amount', 'decimal', '19,8')->unsigned(false)->setDefault('0');
        });
    }

    // ############################################ UNINSTALL #########################

    public function uninstallStep1()
    {
        $db = $this->db();
        $currencies = $db->fetchAll('
			SELECT *
			FROM xf_mjc_currency
		');

        if($currencies){
            $cols = [];
            $sm = $this->schemaManager();

            $alter = $sm->newAlter('xf_user');
            foreach($currencies as $currencyId => $currency){
                $cols[] = 'mjc_' . $currency['currency_id'];
            }
            $alter->dropColumns($cols);
            $alter->apply();
        }
    }

    public function uninstallStep2()
    {
        $sm = $this->schemaManager();

        foreach (array_keys($this->getTables()) as $tableName)
        {
            $sm->dropTable($tableName);
        }
    }

    public function uninstallStep3()
    {
        $this->schemaManager()->alterTable('xf_user', function(Alter $table)
        {
            $table->dropColumns('mjc_credits_last_daily');
        });
    }

    public function uninstallStep4()
    {
        $this->schemaManager()->alterTable('xf_thread', function(Alter $table)
        {
            $table->dropColumns('reward');
            $table->dropColumns('reward_amount');
            $table->dropColumns('reward_currency_id');
            $table->dropColumns('offer_reward');
        });
    }

    public function uninstallStep5()
    {
        $this->db()->delete('xf_payment_provider', 'addon_id LIKE ?', $this->addOn->getAddOnId());
    }

    // ############################# TABLE / DATA DEFINITIONS ##############################

    protected function getTables()
    {
        $tables = [];

        $tables['xf_mjc_transaction'] = function(Create $table)
        {
            $table->addColumn('transaction_id', 'int')->autoIncrement();
            $table->addColumn('transaction_hash', 'varbinary', 64);
            $table->addColumn('definition_id', 'varbinary', 50);
            $table->addColumn('event_id', 'int');
            $table->addColumn('currency_id', 'int');
            $table->addColumn('user_id', 'int')->comment('Target User');
            $table->addColumn('post_id', 'int')->setDefault(0)->comment('Target Post');
            $table->addColumn('trigger_user_id', 'int')->comment('User who did the action that caused the transaction');
            $table->addColumn('amount', 'decimal', '19,8')->unsigned(false)->setDefault('0');
            $table->addColumn('message', 'text');
            $table->addColumn('content_type', 'varbinary', 50);
            $table->addColumn('content_id', 'int')->setDefault(0);
            $table->addColumn('update_user_credit', 'tinyint')->setDefault(0);
            $table->addColumn('transaction_status', 'varchar', 25)->setDefault('completed');
            $table->addColumn('last_update', 'int')->setDefault(0);
            $table->addColumn('transaction_date', 'int')->setDefault(0);
            $table->addColumn('is_hidden', 'tinyint')->setDefault(0);
            $table->addColumn('extra_data', 'mediumblob')->comment('Serialized. Stores any extra data relevant to the transaction');
            $table->addKey('user_id');
            $table->addKey(['user_id', 'transaction_date']);
            $table->addKey('currency_id');
            $table->addKey('definition_id');
            $table->addKey('transaction_hash');
        };

        $tables['xf_mjc_event'] = function(Create $table)
        {
            $table->addColumn('event_id', 'int')->autoIncrement();
            $table->addColumn('definition_id', 'varbinary', 50);
            $table->addColumn('currency_id', 'int');
            $table->addColumn('amount', 'decimal', '19,8')->unsigned(false)->setDefault('0');
            $table->addColumn('moderate_transactions', 'tinyint')->setDefault(0);
            $table->addColumn('send_alert', 'tinyint')->setDefault(1);
            $table->addColumn('options', 'mediumblob');
            $table->addColumn('active', 'tinyint')->setDefault(1);
            $table->addColumn('allowed_user_group_ids', 'blob');
            $table->addKey('definition_id');
            $table->addKey('currency_id');
        };

        $tables['xf_mjc_credits_charge'] = function (Create $table)
        {
            $table->addColumn('content_type', 'varbinary', 25);
            $table->addColumn('content_id', 'int');
            $table->addColumn('content_hash', 'char', 32);
            $table->addColumn('cost', 'double')->unsigned(false)->setDefault(0);
            $table->addPrimaryKey(['content_type', 'content_id', 'content_hash']);
        };

        $tables['xf_mjc_credits_charge_purchase'] = function (Create $table)
        {
            $table->addColumn('content_type', 'varbinary', 25);
            $table->addColumn('content_id', 'int');
            $table->addColumn('content_hash', 'char', 32);
            $table->addColumn('user_id', 'int');
            $table->addPrimaryKey(['content_type', 'content_id', 'content_hash', 'user_id']);
        };

        $tables['xf_mjc_event_definition'] = function(Create $table)
        {
            $table->addColumn('definition_id', 'varbinary', 50);
            $table->addColumn('definition_class', 'varchar', 150);
            $table->addColumn('addon_id', 'varbinary', 50)->setDefault('');
            $table->addColumn('display_order', 'int')->setDefault(0);
            $table->addPrimaryKey('definition_id');
        };

        $tables['xf_mjc_currency'] = function(Create $table)
        {
            $table->addColumn('currency_id', 'int')->autoIncrement();
            $table->addColumn('code', 'varchar', 25)->setDefault('');
            $table->addColumn('prefix', 'varchar', 50);
            $table->addColumn('suffix', 'varchar', 50);
            $table->addColumn('positive', 'tinyint')->setDefault(1);
            $table->addColumn('decimal_places', 'tinyint')->setDefault(0);
            $table->addColumn('exchange_rate', 'decimal', '19,8')->setDefault('0');
            $table->addColumn('max_amount', 'decimal', '19,8')->unsigned(false)->setDefault('0');
            $table->addColumn('display_order', 'int')->setDefault(0);
            $table->addColumn('active', 'tinyint')->setDefault(1);
            $table->addColumn('wallet_popup', 'tinyint')->setDefault(0);
            $table->addColumn('allowed_user_group_ids', 'blob');
        };

        $tables['xf_mjc_stats'] = function(Create $table)
        {
            $table->addColumn('stats_date', 'int');
            $table->addColumn('stats_type', 'varbinary', 50);
            $table->addColumn('currency_id', 'int');
            $table->addColumn('earn', 'decimal', '19,8')->unsigned(false);
            $table->addColumn('spend', 'decimal', '19,8')->unsigned(false);
            $table->addPrimaryKey(['stats_date', 'stats_type', 'currency_id']);
        };

        $tables['xf_mjc_red_envelope'] = function(Create $table)
        {
            $table->addColumn('red_envelope_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('from_user_id', 'int');
            $table->addColumn('start_date', 'int')->setDefault(0);
            $table->addColumn('end_date', 'int')->setDefault(0);
            $table->addColumn('post_id', 'int');
            $table->addColumn('status', 'enum')->values(['pending', 'claimed'])->setDefault('pending');
            $table->addColumn('message','text');
            $table->addColumn('currency_id', 'int');
            $table->addColumn('amount', 'decimal', '19,8')->unsigned(false)->setDefault('0');
            $table->addPrimaryKey('red_envelope_id');
        };

        $tables['xf_mjc_bonus'] = function(Create $table)
        {
            $table->addColumn('bonus_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('currency_id', 'int');
            $table->addColumn('total_point', 'decimal', '19,8')->unsigned(false)->setDefault('0');
            $table->addColumn('total_people', 'int');
            $table->addColumn('thread_id', 'int');
            $table->addColumn('extra_data', 'varbinary',255);
            $table->addColumn('message','text');
            $table->addPrimaryKey('bonus_id');
        };

        $tables['xf_mjc_open_bonus_temp']= function(Create $table)
        {
            $table->addColumn('bonus_temp_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('currency_id', 'int');
            $table->addColumn('total_point', 'decimal', '19,8')->unsigned(false)->setDefault('0');
            $table->addColumn('thread_id', 'int');
            $table->addPrimaryKey('bonus_temp_id');
        };

        $tables['xf_mjc_reward'] = function(Create $table)
        {
            $table->addColumn('reward_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('currency_id', 'int');
            $table->addColumn('amount', 'decimal', '19,8')->unsigned(false)->setDefault('0');
            $table->addColumn('post_id', 'int');
            $table->addColumn('reward_date', 'int')->setDefault(0);
            $table->addPrimaryKey('reward_id');
        };
        return $tables;
    }

    protected function getData()
    {
        $data = [];

        $data['xf_mjc_event_definition'] = "
			INSERT IGNORE INTO `xf_mjc_event_definition`
				(`definition_id`, `definition_class`, `addon_id`, `display_order`)
			VALUES
				('daily_login', '', 'MJ/Credits', 100),
				('register', '', 'MJ/Credits', 200),

				('exchange', '\\\\MJ\\\\Credits\\\\Event\\\\Exchange', 'MJ/Credits', 1000),
				('transfer', '\\\\MJ\\\\Credits\\\\Event\\\\Transfer', 'MJ/Credits', 1020),
				('fine', '\\\\MJ\\\\Credits\\\\Event\\\\Fine', 'MJ/Credits', 1030),
				('birthday', '', 'MJ/Credits', 1100),

				('avatar_upload', '', 'MJ/Credits', 2100),
				('avatar_delete', '', 'MJ/Credits', 2110),

				('follow', '', 'MJ/Credits', 4010),
				('unfollow', '', 'MJ/Credits', 4020),
				('follower_receive', '', 'MJ/Credits', 4030),
				('follower_lose', '', 'MJ/Credits', 4040),
				('update_status', '', 'MJ/Credits', 4200),
				('profile_post_new', '', 'MJ/Credits', 4210),
				('profile_post_delete', '', 'MJ/Credits', 4220),
				('profile_post_receive', '', 'MJ/Credits', 4230),
				('profile_post_lose', '', 'MJ/Credits', 4240),
				('profile_post_like', '', 'MJ/Credits', 4300),
				('profile_post_unlike', '', 'MJ/Credits', 4310),
				('profile_post_like_receive', '', 'MJ/Credits', 4320),
				('profile_post_like_lose', '', 'MJ/Credits', 4330),

				('conversation_new', '', 'MJ/Credits', 5000),
				('conversation_receive', '', 'MJ/Credits', 5020),
				('conversation_leave', '', 'MJ/Credits', 5030),
				('conversation_reply_new', '', 'MJ/Credits', 5100),
				('conversation_reply_receive', '', 'MJ/Credits', 5120),

				('thread_new', '', 'MJ/Credits', 6000),
				('thread_delete', '', 'MJ/Credits', 6010),
				('thread_reply_receive', '', 'MJ/Credits', 6100),
				('thread_reply_lose', '', 'MJ/Credits', 6110),
				('thread_view', '', 'MJ/Credits', 6200),
				('thread_view_receive', '', 'MJ/Credits', 6210),
				('thread_sticky', '', 'MJ/Credits', 6220),
				('thread_unsticky', '', 'MJ/Credits', 6230),
				('thread_watch', '', 'MJ/Credits', 6300),
				('thread_unwatch', '', 'MJ/Credits', 6310),
				('thread_watch_receive', '', 'MJ/Credits', 6320),
				('thread_watch_lose', '', 'MJ/Credits', 6330),

				('poll_create', '', 'MJ/Credits', 7000),
				('poll_delete', '', 'MJ/Credits', 7010),
				('poll_vote', '', 'MJ/Credits', 7020),
				('poll_vote_receive', '', 'MJ/Credits', 7030),

				('post_new', '', 'MJ/Credits', 8000),
				('post_delete', '', 'MJ/Credits', 8010),
				('soft_post_delete', '', 'MJ/Credits', 8020),
				('post_like', '', 'MJ/Credits', 8030),
				('post_unlike', '', 'MJ/Credits', 8040),
				('post_like_receive', '', 'MJ/Credits', 8060),
				('post_like_lose', '', 'MJ/Credits', 8070),
				('post_report', '', 'MJ/Credits', 8200),
				('post_report_receive', '', 'MJ/Credits', 8210),
				
			    ('adjust', 'MJ\\\\Credits\\\\Event\\\\EventHandler', 'MJ/Credits', 1000),
				('content', 'MJ\\\\Credits\\\\Event\\\\Content', 'MJ/Credits', 8220),
				('reward', 'MJ\\\\Credits\\\\Event\\\\Reward', 'MJ/Credits', 2000),
				('red_envelope', 'MJ\\\\Credits\\\\Event\\\\RedEnvelope', 'MJ/Credits', 1050),
				('bonus', 'MJ\\\\Credits\\\\Event\\\\bonus', 'MJ/Credits', 1060),
				('payment', 'MJ\\\\Credits\\\\Event\\\\payment', 'MJ/Credits', 1070),
				('offerReward', '', 'MJ/Credits', 4800),
			    ('daily_clock', 'MJ\\\\Credits\\\\Event\\\\EventHandler', 'MJ/Credits', 1080),

				('attachment_upload', '', 'MJ/Credits', 9000),
				('attachment_delete', '', 'MJ/Credits', 9010),
				('attachment_download', 'MJ\\\\Credits\\\\Event\\\\Attachment', 'MJ/Credits', 9020),
				('attachment_download_receive', 'MJ\\\\Credits\\\\Event\\\\Attachment', 'MJ/Credits', 9030);
		";

        $data['xf_mjc_currency'] = "
			INSERT IGNORE INTO `xf_mjc_currency`
				(`currency_id`, `code`, `prefix`, `suffix`, `decimal_places`,
				`exchange_rate`, `display_order`, `active`, `allowed_user_group_ids`)
			VALUES
				(1, 'GOLD', '', 'G', 2, '1.00000000', 0, 1, '-1'),
				(2, 'SLV', '', 'S', 0, '2.00000000', 0, 1, '-1');
		";

        return $data;
    }

    protected function getDefaultWidgetSetup()
    {
        return [
            'mjc_sidenav_balance' => function($key, array $options = [])
            {
                $options = array_replace([], $options);

                $this->createWidget(
                    $key,
                    'mjc_balance',
                    [
                        'positions' => [
                            'mjc_wapper_sidenav' => 100,
                        ],
                        'options' => $options
                    ],
                    'Your Balance'
                );
            },
            'mjc_sidenav_richest' => function($key, array $options = [])
            {
                $options = array_replace([
                    'limit' => 10
                ], $options);

                $this->createWidget(
                    $key,
                    'mjc_richest',
                    [
                        'positions' => [
                            'mjc_wapper_sidenav' => 200,
                        ],
                        'options' => $options
                    ],
                    'Richest members'
                );
            },
            'mjc_sidenav_clock_in_every_day' => function($key, array $options = [])
            {
                $options = array_replace([], $options);

                $this->createWidget(
                    $key,
                    'mjc_clock_in_every_day',
                    [
                        'positions' => [
                            'forum_overview_wrapper' => 10
                        ],
                        'options' => $options
                    ]
                );
            },
        ];
    }


    protected function applyDefaultPermissions($previousVersion = null)
    {
        $applied = false;

        if (!$previousVersion)
        {
            /*$this->applyGlobalPermission('mjcCredits', 'useCredits', 'general', 'viewNode');
            $applied = true;*/
        }

        return $applied;
    }
}
