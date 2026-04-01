((window, document) =>
{
	"use strict";

	XF.MJCSendCredit = XF.Element.newHandler({

		options: {
		},

		init: function()
		{
			const target = this.target

			const sendAmount = target.querySelector('.sendAmount');
			const currency = target.querySelector('select[name="currency_id"]');
			const feeType = target.querySelector('select[name="fee_type"]');

			XF.on(sendAmount, 'change', this.getSummary.bind(this));
			XF.on(currency, 'change', this.getSummary.bind(this));
			XF.on(feeType, 'change', this.getSummary.bind(this));
		},

		getSummary (e)
		{
			e.preventDefault();

			const self = this;
			XF.ajax(
				'post', XF.canonicalizeUrl('index.php?mjc-credits/send-credit-amount'),
				new FormData(this.target),

				this.handleAjax.bind(this),
				{skipDefaultSuccessError: true}
			);
		},

		handleAjax (data)
		{
			if (data.errors || data.exception)
			{
				return;
			}

			var self = this;
			XF.setupHtmlInsert(data.html, function(html, container, onComplete)
			{
				const captchaContainer = self.target.querySelector('.mjc-summary dd');

				captchaContainer.replaceWith(html)
				onComplete();
			});
		}
	});

	// ################################## --- ###########################################

	XF.Element.register('mjc-send-credit-form', 'XF.MJCSendCredit');
})(window, document);