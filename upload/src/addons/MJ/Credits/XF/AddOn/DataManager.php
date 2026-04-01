<?php

namespace MJ\Credits\XF\AddOn;

class DataManager extends XFCP_DataManager
{
	public function getDataTypeClasses()
	{
		$classes = parent::getDataTypeClasses();
		$classes[] = 'MJ\Credits:EventDefinition';
		return $classes;
	}
}
