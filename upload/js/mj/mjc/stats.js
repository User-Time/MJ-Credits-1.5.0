((window, document) =>
{
	"use strict";

	XF.MJCStats = XF.Element.newHandler({
		options: {
			data: '| .js-statsData',
			seriesLabels: '| .js-statsSeriesLabels',
			legend: '| .js-statsLegend',
			chart: '| .js-statsChart',
			maxTicks: 9
		},

		$chart: null,
		chart: null,

		init: function()
		{
			this.$chart = XF.findRelativeIf(this.options.chart, this.target);

			var data = {},
				$data = XF.findRelativeIf(this.options.data, this.target),
				seriesLabels = {},
				$seriesLabels = XF.findRelativeIf(this.options.seriesLabels, this.target);

			try
			{
				data = JSON.parse($data.innerHTML) || {}
			}
			catch (e)
			{
				console.error("Stats data not valid: ", e);
				return;
			}

			try
			{
				seriesLabels = JSON.parse($seriesLabels.innerHTML) || {}
			}
			catch (e)
			{
				console.error("Series labels not valid: ", e);
			}

			this.seriesLabels = seriesLabels;

			var options = {
				labelInterpolationFnc: function(value) {
					return value[0]
				},
				showLabel: true
			};

			var responsiveOptions = [
				['screen and (min-width: 640px)', {
					labelDirection: 'explode',
					labelInterpolationFnc: function(value) {
						return value;
					}
				}]
			];
			var chartData = this.setupChartData(data)

			new Chartist.Pie(this.$chart, chartData, options, responsiveOptions);
		},

		setupChartData: function(data)
		{
			var labels = [],
				series = [],
				point = 0,
				self = this;
			for (var seriesType in data)
			{
				labels.push(data[seriesType]['label']);
				series.push(data[seriesType]['total']);
			}
			return {
				labels: labels,
				series: series
			};
		}
	});

	XF.Element.register('mjc-stats', 'XF.MJCStats');
})(window, document);