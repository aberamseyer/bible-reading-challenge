function convertToTransparent(rgbString, alpha) {
  // Regular expression to extract RGB values
  const rgbRegex = /\d+/g;
  const rgbValues = rgbString.match(rgbRegex).map(Number);

  // Construct the RGBA string
  const [r, g, b] = rgbValues;
  const rgbaString = `rgba(${r}, ${g}, ${b}, ${alpha})`;

  return rgbaString;
}

function initProgressChart(element, labels, values, small) {
  const obj ={
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: '% complete',
        data: values,
        borderWidth: 1,
        fill: true,
        borderColor: COLORS.primary,
        backgroundColor: convertToTransparent(COLORS.primary, 0.6),
        tension: 0.2
      }],
    },
    options: {
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {     
        x: {
          type: 'time',
          time: {
            unit: 'day',
            tooltipFormat: 'MMM d'
          }
        },
        y: {
          min: 0,
          max: 100,
          ticks: {
            callback: value => `${value}%`
          }
        }
      }
    }
  }
  if (small) {
    obj.options.scales.x.ticks = { display: false }
    obj.options.scales.x.grid = { display: false }
    obj.options.scales.y.grid = { display: false }
    obj.options.animation = { duration: 0 }
    
    // minimize rendering cost, drop 80% of points
    obj.data.labels = labels.filter((x, i) => i % 5 === 0)
    obj.data.datasets[0].data = values.filter((x, i) => i % 5 === 0)
  }
  new Chart(element, obj)
}

function initWeeklyCountsChart(element, labels, values) {
  new Chart(element, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Days read each week',
        data: values,
        tension: 0.4,
        borderColor: COLORS.secondary
      }]
    },
    options: {
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {     
        y: {
          min: 0,
          ticks: {
            stepSize: 1
          },
        },
        x: {
          type: 'time',
          time: {
            unit: 'day',
            tooltipFormat: 'MMM d'
          }
        }
      }
    }
  })
}

function initFourWeekTrendChart(element, labels, values) {
  const gradient = element.getContext('2d').createLinearGradient(0, 0, 200, 0)
  gradient.addColorStop(0, COLORS.primary)
  gradient.addColorStop(1, COLORS.secondary)

  new Chart(element, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'days read each week',
        data: values,
        fill: false,
        tension: 0.5,
        borderColor: gradient,
        pointStyle: false
      }]
    },
    options: {
      responsive: false,
      animation: {
        duration: 0
      },
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {     
        y: {
          ticks: {
            display: false
          },
          grid: {
            display: false,
          },
          min: 0,
          max: 8
        },
        x: {
          ticks: {
            display: false,
          },
          grid: {
            display: false,
          },
          type: 'time',
          time: {
            unit: 'week',
            tooltipFormat: 'MMM d'
          }
        }
      }
    }
  })
}

function initializeHourlyFreqChart(element, labels, values) {
  const gradient = element.getContext('2d').createLinearGradient(0, 0, 200, 0)
  gradient.addColorStop(0, COLORS.secondary)
  gradient.addColorStop(1, COLORS.primary)
  new Chart(element, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        data: values,
        fill: true,
        tension: 0.6,
        borderColor: COLORS.secondary,
        backgroundColor: convertToTransparent(COLORS.secondary, 0.8),
        pointStyle: false
      }]
    },
    options: {
      responsive: false,
      animation: false,
      plugins: {
        legend: false,
        tooltip: false,
      },
      scales: {     
        r: {
          animate: false,
          pointLabels: {
            callback: (label, index) => index % 3 === 0 ? label : ''
          },
          ticks: {
            display: false,
            count: 5
          },
          angleLines: {
            display: false
          },
          grid: {
            color: '#c9c9c9',
            circular: true,
            lineWidth: 1
          }
        }
      }
    }
  });
}

function initializeEmailStatsChart(element, labels, values) {
  new Chart(element, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        data: values,
        fill: true,
        tension: 0.6,
        borderColor: COLORS.primary,
        backgroundColor: convertToTransparent(COLORS.primary, 0.8),
        pointStyle: false
      }]
    },
  });
}