window.MJCredits = window.MJCredits || {}

;((window, document) =>
{
	'use strict'

	MJCredits.editorButton = {
		container: null,

		init ()
		{
			MJCredits.editorButton.initializeDialog()
			XF.EditorHelpers.dialogs.fees = new MJCredits.EditorDialogFees('mjcCreditsCharge')

			if (FroalaEditor.COMMANDS.xfCustom_fees)
			{
				FroalaEditor.COMMANDS.xfCustom_fees.callback = MJCredits.editorButton.callback
			}
		},

		initializeDialog ()
		{
			MJCredits.EditorDialogFees = XF.extend(XF.EditorDialog, {
				cache: false,
				container: null,

				_beforeShow (overlay)
				{
					document.querySelector('#editor_mjc_credits_charge_title').value = ''
				},

				_init (overlay)
				{


					XF.on(document.querySelector('#editor_mjc_credits_charge_form'), 'submit', XF.proxy(this, 'submit'))
				},

				submit (e)
				{
					e.preventDefault();

					var ed = this.ed,
						overlay = this.overlay;

					ed.selection.restore();
					MJCredits.EditorHelpers.insertCharge(ed, document.querySelector('#editor_mjc_credits_charge_title').value);

					overlay.hide();
				}
			})

			MJCredits.EditorHelpers = {
				insertCharge (ed, amount)
				{
					var open;
					if (amount)
					{
						open = '[FEES=' + amount + ']';
					}
					else
					{
						open = '[FEES]';
					}

					XF.EditorHelpers.wrapSelectionText(ed, open, '[/FEES]', true);
				}
			};
		},

		callback ()
		{
			XF.EditorHelpers.loadDialog(this, 'fees')
		},

	}

	XF.on(document, 'editor:first-start', MJCredits.editorButton.init)
})(window, document)