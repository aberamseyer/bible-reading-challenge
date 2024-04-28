const compareRows = (a, b, currentSortColumn) => {
  const column = currentSortColumn
  const tdA = a.querySelector(`td[data-${column}]`)
  const tdB = b.querySelector(`td[data-${column}]`)
  
  if (column === "name") {
    return tdA.textContent.split(/\s/g).slice(1).join(' ').localeCompare(tdB.textContent.split(/\s/g).slice(1).join(' '))
  }
  else if (column === "last-read") {
    return new Date(tdA.getAttribute('data-last-read')) > new Date(tdB.getAttribute('data-last-read')) ? 1 : -1
  }
  else if (column === "email") {
    const a_email = parseInt(tdA.getAttribute('data-email'))
    const b_email = parseInt(tdB.getAttribute('data-email'))
    if (a_email == b_email)
      return a.querySelector('td[data-name]').textContent.localeCompare(b.querySelector('td[data-name]').textContent)
    else
      return a_email > b_email ? 1 : -1
  }
  else if (column === 'trend') {
    const a_arr = JSON.parse(a.querySelector('[data-graph]').getAttribute('data-graph'))
    const b_arr = JSON.parse(b.querySelector('[data-graph]').getAttribute('data-graph'))
    return a_arr.reduce((acc, curr) => acc + curr) > b_arr.reduce((acc, curr) => acc + curr) ? 1 : -1
  }
  else if (column === 'period') {
    return a.querySelectorAll('.active').length > b.querySelectorAll('.active').length ? 1 : -1
  }
}
initTable(compareRows, 'period')

const milestoneCanvas = document.getElementById('milestone-chart')
const msData = JSON.parse(milestoneCanvas.dataset.graph)
new Chart(milestoneCanvas, {
  type: 'line',
  data: {
    labels: Object.values(msData),
    datasets: [{
      label: '% complete',
      data: Object.keys(msData),
      borderWidth: 1,
      fill: true,
    }],
  },
  options: {
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
})

const weeklycountsCanvas = document.getElementById('weekly-counts')
const weeklycountsData = JSON.parse(weeklycountsCanvas.dataset.graph)
new Chart(weeklycountsCanvas, {
  type: 'line',
  data: {
    labels: Object.keys(weeklycountsData),
    datasets: [{
      label: 'chapters read each week',
      data: Object.values(weeklycountsData),
      fill: false,
      tension: 0.1
    }]
  },
  options: {
    scales: {     
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