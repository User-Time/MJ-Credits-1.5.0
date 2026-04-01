<?php
namespace  MJ\Credits\Repository;

class Post extends XFCP_Post
{
    public function findBonusForThread($threadId)
    {
        return $this->finder('MJ\Credits:OpenBonusTemp')
            ->where('thread_id', $threadId);
    }

    public function findRewardForThread($postId)
    {
        return $this->finder('MJ\Credits:Reward')
            ->where('post_id', $postId);
    }

    public function findPurchaserForPost($postId)
    {
        return $this->finder('MJ\Credits:ChargePurchase')
            ->where('content_id', $postId);
    }
}