<?php

namespace MJ\Credits\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Credit extends Repository
{
	public function findPaymentProfiles()
	{
		/** @var \XF\Repository\PaymentRepository $paymentRepo */
		$paymentRepo = $this->repository('XF:PaymentRepository');
		return $paymentRepo->findPaymentProfilesForList()
			->pluckFrom(function ($e) {
				return ($e->display_title ?: $e->title);
			})
			->fetch();
	}
}
