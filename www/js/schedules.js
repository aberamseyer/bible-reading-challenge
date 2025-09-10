const compareRows = (a, b, currentSortColumn) => {
  const column = currentSortColumn
  const tdA = a.querySelector(`td[data-${column}]`)
  const tdB = b.querySelector(`td[data-${column}]`)
  
  if (column === "name") {
    return tdA.textContent.localeCompare(tdB.textContent)
  }
  else if (column === "start") {
    return new Date(tdA.getAttribute('data-start')) > new Date(tdB.getAttribute('data-start')) ? 1 : -1
  }
  else if (column === "end") {
    return new Date(tdA.getAttribute('data-end')) > new Date(tdB.getAttribute('data-end')) ? 1 : -1
  }
}
initTable(compareRows, 'start')

document.querySelectorAll('canvas[data-freq]').forEach(canvas => {
  const freqData = JSON.parse(canvas.dataset.freq)
  initializeHourlyFreqChart(canvas, Object.keys(freqData), Object.values(freqData));
})

document.querySelectorAll('canvas[data-email-stats]').forEach(canvas => {
  const emailData = JSON.parse(canvas.dataset.emailStats)
  initializeEmailStatsChart(canvas, Object.keys(emailData), Object.values(emailData));
})