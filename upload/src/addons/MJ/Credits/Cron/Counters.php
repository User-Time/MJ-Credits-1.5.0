<?php

namespace MJ\Credits\Cron;

/**
 * Cron entry for timed counter updates.
 */
class Counters
{
	/**
	 * Log daily statistics
	 */
	public static function recordDailyStats()
	{
		/** @var \MJ\Credits\Repository\Stats $statsRepo */
		$statsRepo = \XF::app()->repository('MJ\Credits:Stats');

		// get the the timestamp of 00:00 UTC for today
		$time = \XF::$time - \XF::$time % 86400 + 86400;
		$statsRepo->build($time - 86400, $time);
	}
}