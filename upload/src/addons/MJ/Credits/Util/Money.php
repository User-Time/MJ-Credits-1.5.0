<?php

namespace MJ\Credits\Util;

class Money
{
	public static function formatAmount($amount, $currencyId, $addPrefix = null, $options = [])
	{
		$amount = strval(floatval($amount)) + 0;

		$operator = '';

		if(is_numeric($amount)){
			if($addPrefix === true && $amount > 0){
				$operator = '+';
			}else if($addPrefix !== false && $amount < 0){
				$operator = '-';
			}
			if($amount < 0){
				$amount *= -1;
			}
		}

		if(is_array($currencyId) && !empty($currencyId['currency_id'])){
			$currencyId = $currencyId['currency_id'];
		}else if(!empty($currencyId->currency_id)){
			$currencyId = $currencyId->currency_id;
		}
		$currencyId = intval($currencyId);
		if(!$currencyId){
			return $amount;
		}

		if(!empty($options['currency'])){
			$currency = $options['currency'];
		}else{
			$currencies = \XF::app()->container('mjc.currencies');
			if(empty($currencies[$currencyId])){
				return $amount;
			}
			$currency = $currencies[$currencyId];
		}


		$money = '';

		$formatOptions = self::formatOptions($currency, $options);

		$amount = \XF::language()->numberFormat($amount, $formatOptions['decimal_places']);

		if (!empty($formatOptions['prefix']) && is_scalar($formatOptions['prefix'])) {
			$money .= html_entity_decode($formatOptions['prefix']);
		}

		$money .= $amount;

		if (!empty($formatOptions['suffix']) && is_scalar($formatOptions['suffix'])) {
			$money .= html_entity_decode($formatOptions['suffix']);
		}

		return $operator . $money;
	}

	protected static function formatOptions($currency, $options)
	{
		$formatOptions = [];
		if(isset($options['decimal_places'])){
			$formatOptions['decimal_places'] = $options['decimal_places'];
		}else if(isset($currency['decimal_places'])){
			$formatOptions['decimal_places'] = $currency['decimal_places'];
		}else{
			$formatOptions['decimal_places'] = 0;
		}

		if(isset($options['prefix'])){
			$formatOptions['prefix'] = $options['prefix'];
		}else if(isset($currency['prefix'])){
			$formatOptions['prefix'] = $currency['prefix'];
		}else{
			$formatOptions['prefix'] = '';
		}

		if(isset($options['suffix'])){
			$formatOptions['suffix'] = $options['suffix'];
		}else if(isset($currency['suffix'])){
			$formatOptions['suffix'] = $currency['suffix'];
		}else{
			$formatOptions['suffix'] = '';
		}
		return $formatOptions;
	}
}
