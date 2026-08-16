// assets/js/charts.js
// Chart.js configurations for reports

let salesChart = null;
let projectChart = null;

function initSalesChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if (!ctx) return;
    
    if (salesChart) salesChart.destroy();
    
    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Sales Amount (₱)',
                data: data,
                borderColor: '#FF8C00',
                backgroundColor: 'rgba(255, 140, 0, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '₱' + context.raw.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function initProjectChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if (!ctx) return;
    
    if (projectChart) projectChart.destroy();
    
    projectChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: ['#FF8C00', '#3498DB', '#27AE60', '#E74C3C', '#9B59B6'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

function initBarChart(canvasId, labels, datasets) {
    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if (!ctx) return;
    
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Export functions
window.initSalesChart = initSalesChart;
window.initProjectChart = initProjectChart;
window.initBarChart = initBarChart;