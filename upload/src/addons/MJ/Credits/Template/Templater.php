<?php

namespace MJ\Credits\Template;

class Templater extends XFCP_Templater
{
	public static function fnRemoveZero($templater, &$escape, $number)
	{
		return self::removeZeroFomarted($number);
	}

	public static function removeZero($number)
	{
		return strval(floatval($number)) + 0;
	}

	public static function removeZeroFomarted($number)
	{
		$decimalPoint = \XF::language()->offsetGet('decimal_point');
		if(!$decimalPoint){
			$decimalPoint = '.';
		}

		if(strpos($number, $decimalPoint) !== false){
			$number = rtrim(rtrim($number, '0'), $decimalPoint);
		}
		return $number;
	}
	public static function fnFormat($templater, &$escape, $amount, $currencyId, $addPrefix = null)
	{
		return \MJ\Credits\Util\Money::formatAmount($amount, $currencyId, $addPrefix);
	}

	////////////////////// FILTERS //////////////////////////

	public static function filterCurrencies($templater, $value, &$escape, $currencyId, $addPrefix = null)
	{
		return \MJ\Credits\Util\Money::formatAmount($value, $currencyId, $addPrefix);
	}
}
