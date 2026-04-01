<?php
// +----------------------------------------------------------------------
// | [ I CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) https://www.cnxfans.com 2017-2021 rights reserved.
// +----------------------------------------------------------------------
// | Author: 死了算了
// +----------------------------------------------------------------------
// | Date: 2021/11/22 17:44
// +----------------------------------------------------------------------

namespace MJ\Credits\XFRM\Entity;

class ResourceItem extends XFCP_ResourceItem
{
    protected function _postDelete()
    {
        $trigger = $this->app()->service('MJ\Credits:Event\Trigger');
        $trigger->triggerEvent('resource_delete', $this->user_id, [
            'content_type' => 'resource',
            'content_id'   => $this->resource_id
        ]);
        $trigger->fire();
        return parent::_postDelete();
    }
}