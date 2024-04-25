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
    const a_arr = Object.values(JSON.parse(a.querySelector('[data-graph]').dataset.graph))
    const b_arr = Object.values(JSON.parse(b.querySelector('[data-graph]').dataset.graph))
    return a_arr.reduce((acc, curr) => acc + curr) > b_arr.reduce((acc, curr) => acc + curr) ? 1 : -1
  }
  else if (column === 'period') {
    return a.querySelectorAll('.active').length > b.querySelectorAll('.active').length ? 1 : -1
  }
}
initTable(compareRows, 'period')



document.querySelectorAll('canvas[data-graph]').forEach(canvas => {
  const fourWeekData = JSON.parse(canvas.dataset.graph)
  initFourWeekTrendChart(canvas, Object.keys(fourWeekData), Object.values(fourWeekData))
})

