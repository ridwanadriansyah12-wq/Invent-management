import './bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

// Indonesian number formatter utility for Chart.js tooltips & labels
window.formatNumberId = (val, maxDecimals = 3) => {
    if (val === null || val === undefined || isNaN(val)) return '0';
    const num = Number(val);
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: maxDecimals,
    }).format(num);
};

Alpine.start();
