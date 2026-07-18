const dashboard = window.DropaivDashboard || {};
const palette = { quotes: '#10b981', customers: '#3b82f6', warehouse: '#8b5cf6' };

function drawChart(canvas, values, labels, color) {
    const stage = canvas.closest('.chart-stage');
    const data = (values || []).map(Number);
    if (!data.length || data.every((value) => value === 0)) {
        stage.classList.add('is-empty');
        return;
    }

    stage.classList.remove('is-empty');
    const ratio = window.devicePixelRatio || 1;
    const width = stage.clientWidth;
    const height = stage.clientHeight;
    canvas.width = width * ratio;
    canvas.height = height * ratio;
    const context = canvas.getContext('2d');
    context.scale(ratio, ratio);
    const padding = { top: 14, right: 12, bottom: 26, left: 28 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;
    const max = Math.max(...data, 1);

    context.font = '9px Nunito, sans-serif';
    context.lineWidth = 1;
    for (let line = 0; line <= 3; line += 1) {
        const y = padding.top + (chartHeight / 3) * line;
        context.strokeStyle = '#e9eef3';
        context.beginPath(); context.moveTo(padding.left, y); context.lineTo(width - padding.right, y); context.stroke();
        context.fillStyle = '#9aa8b5';
        context.fillText(Math.round(max - (max / 3) * line), 2, y + 3);
    }

    const step = chartWidth / Math.max(data.length - 1, 1);
    const points = data.map((value, index) => ({ x: padding.left + step * index, y: padding.top + chartHeight - (value / max) * chartHeight }));
    const gradient = context.createLinearGradient(0, padding.top, 0, height - padding.bottom);
    gradient.addColorStop(0, `${color}35`); gradient.addColorStop(1, `${color}00`);
    context.beginPath(); context.moveTo(points[0].x, height - padding.bottom); points.forEach((point) => context.lineTo(point.x, point.y)); context.lineTo(points[points.length - 1].x, height - padding.bottom); context.closePath(); context.fillStyle = gradient; context.fill();
    context.beginPath(); points.forEach((point, index) => index ? context.lineTo(point.x, point.y) : context.moveTo(point.x, point.y)); context.strokeStyle = color; context.lineWidth = 2.5; context.lineJoin = 'round'; context.stroke();
    points.forEach((point) => { context.beginPath(); context.arc(point.x, point.y, 3, 0, Math.PI * 2); context.fillStyle = '#fff'; context.fill(); context.strokeStyle = color; context.lineWidth = 2; context.stroke(); });
    context.fillStyle = '#8494a5'; context.textAlign = 'center';
    (labels || []).forEach((label, index) => { if (index % Math.max(1, Math.ceil(data.length / 6)) === 0) context.fillText(String(label).slice(0, 3), padding.left + step * index, height - 7); });
}

function renderDashboardCharts() {
    document.querySelectorAll('[data-dashboard-chart]').forEach((canvas) => {
        const key = canvas.dataset.dashboardChart;
        drawChart(canvas, dashboard.charts?.[key], dashboard.months, palette[key] || palette.quotes);
    });
}

let resizeTimer;
window.addEventListener('resize', () => { window.clearTimeout(resizeTimer); resizeTimer = window.setTimeout(renderDashboardCharts, 120); });
document.addEventListener('DOMContentLoaded', renderDashboardCharts);
