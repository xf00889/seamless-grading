import ApexCharts from 'apexcharts/core';
import 'apexcharts/bar';
import 'apexcharts/donut';
import 'apexcharts/features/legend';

const chartInstances = new Map();
const chartFontFamily = 'Plus Jakarta Sans, ui-sans-serif, system-ui, sans-serif';

function numberFormatter(value) {
    return new Intl.NumberFormat().format(Number(value ?? 0));
}

function parseChartConfig(element) {
    const rawConfig = element.dataset.chartConfig;

    if (!rawConfig) {
        return null;
    }

    try {
        return JSON.parse(rawConfig);
    } catch (error) {
        console.warn('Unable to parse ApexCharts config for dashboard chart.', error);

        return null;
    }
}

function baseChartOptions(config) {
    return {
        chart: {
            height: Number(config?.height ?? 360),
            toolbar: {
                show: false,
            },
            fontFamily: chartFontFamily,
        },
        noData: {
            text: config?.emptyText ?? 'No chart data available',
            align: 'center',
            verticalAlign: 'middle',
            style: {
                color: '#8492a6',
                fontSize: '14px',
                fontFamily: chartFontFamily,
            },
        },
        states: {
            hover: {
                filter: {
                    type: 'none',
                },
            },
            active: {
                filter: {
                    type: 'none',
                },
            },
        },
        tooltip: {
            theme: 'light',
        },
    };
}

function buildBarChartOptions(config) {
    const baseOptions = baseChartOptions(config);
    const categories = Array.isArray(config?.categories) ? config.categories : [];
    const values = Array.isArray(config?.values)
        ? config.values.map((value) => Number(value ?? 0))
        : [];
    const colors = Array.isArray(config?.colors) && config.colors.length === values.length
        ? config.colors
        : values.map(() => '#b6bfdc');
    const maxValue = Number(config?.max ?? 0);

    return {
        ...baseOptions,
        chart: {
            ...baseOptions.chart,
            type: 'bar',
        },
        series: [
            {
                name: config?.label ?? 'Dashboard chart',
                data: values,
            },
        ],
        colors,
        plotOptions: {
            bar: {
                horizontal: false,
                borderRadius: 6,
                borderRadiusApplication: 'end',
                columnWidth: values.length > 5 ? '52%' : '60%',
                distributed: true,
                dataLabels: {
                    position: 'top',
                },
            },
        },
        dataLabels: {
            enabled: true,
            formatter: (value) => numberFormatter(value),
            offsetY: -20,
            style: {
                fontSize: '12px',
                fontWeight: 600,
                colors: ['#5f6c7b'],
            },
        },
        grid: {
            borderColor: 'rgba(217, 226, 236, 0.82)',
            strokeDashArray: 4,
            padding: {
                left: 8,
                right: 12,
                top: 8,
                bottom: 0,
            },
        },
        legend: {
            show: false,
        },
        tooltip: {
            ...baseOptions.tooltip,
            y: {
                formatter: (value) => numberFormatter(value),
            },
        },
        xaxis: {
            categories,
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
            labels: {
                style: {
                    colors: categories.map(() => '#8492a6'),
                    fontSize: '11px',
                    fontWeight: 600,
                },
            },
        },
        yaxis: {
            show: false,
            max: maxValue > 0 ? Math.ceil(maxValue * 1.15) : 1,
        },
    };
}

function buildDonutChartOptions(config) {
    const baseOptions = baseChartOptions(config);
    const labels = Array.isArray(config?.labels) ? config.labels : [];
    const series = Array.isArray(config?.series)
        ? config.series.map((value) => Number(value ?? 0))
        : [];
    const total = series.reduce((sum, value) => sum + value, 0);
    const hasData = series.some((value) => value > 0);
    const colors = Array.isArray(config?.colors) && config.colors.length === series.length
        ? config.colors
        : series.map(() => '#b6bfdc');

    return {
        ...baseOptions,
        chart: {
            ...baseOptions.chart,
            type: 'donut',
        },
        series: hasData ? series : [],
        labels,
        colors,
        dataLabels: {
            enabled: false,
        },
        stroke: {
            width: 0,
        },
        legend: {
            show: true,
            position: 'right',
            fontSize: '14px',
            fontWeight: 600,
            labels: {
                colors: '#334155',
            },
            markers: {
                width: 10,
                height: 10,
                radius: 10,
            },
            itemMargin: {
                vertical: 8,
            },
            formatter(seriesName, options) {
                const value = Number(options.w.globals.series[options.seriesIndex] ?? 0);
                const percent = total > 0 ? Math.round((value / total) * 100) : 0;

                return `${seriesName}: ${numberFormatter(value)} - ${percent}%`;
            },
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            offsetY: 22,
                            color: '#73819a',
                            fontSize: '12px',
                            fontWeight: 600,
                        },
                        value: {
                            show: true,
                            offsetY: -18,
                            color: '#22304a',
                            fontSize: '22px',
                            fontWeight: 700,
                            formatter: (value) => numberFormatter(value),
                        },
                        total: {
                            show: true,
                            showAlways: true,
                            label: config?.totalName ?? 'Total',
                            color: '#73819a',
                            fontSize: '12px',
                            fontWeight: 600,
                            formatter: () => config?.totalLabel ?? numberFormatter(total),
                        },
                    },
                },
            },
        },
        tooltip: {
            ...baseOptions.tooltip,
            y: {
                formatter: (value) => numberFormatter(value),
            },
        },
        responsive: [
            {
                breakpoint: 900,
                options: {
                    legend: {
                        position: 'bottom',
                    },
                },
            },
        ],
    };
}

function buildChartOptions(config) {
    switch (config?.type) {
    case 'donut':
        return buildDonutChartOptions(config);
    case 'bar':
    default:
        return buildBarChartOptions(config);
    }
}

function initializeDashboardCharts(root = document) {
    root.querySelectorAll('[data-dashboard-chart]').forEach((element) => {
        const config = parseChartConfig(element);

        if (!config) {
            return;
        }

        const existingChart = chartInstances.get(element);

        if (existingChart) {
            existingChart.destroy();
        }

        const chart = new ApexCharts(element, buildChartOptions(config));

        chart.render();
        chartInstances.set(element, chart);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initializeDashboardCharts());
} else {
    initializeDashboardCharts();
}
