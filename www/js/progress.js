// table
const compareRows = (a, b, currentSortColumn) => {
  const column = currentSortColumn
  const tdA = a.querySelector(`td[data-${column}]`)
  const tdB = b.querySelector(`td[data-${column}]`)
  
  if (column === "name") {
    return tdA.textContent.localeCompare(tdB.textContent)
  }
  else if (column === "behind") {
    return parseInt(tdA.getAttribute('data-behind')) < parseInt(tdB.getAttribute('data-behind')) ? 1 : -1
  }
  else if (column === "streak") {
    return parseInt(tdA.getAttribute('data-streak')) > parseInt(tdB.getAttribute('data-streak')) ? 1 : -1
  }
  else if (column === 'progress') {
    return parseFloat(tdA.getAttribute('data-progress')) > parseFloat(tdB.getAttribute('data-progress')) ? 1 : -1
  }
  else if (column == 'percent') {
    return parseFloat(tdA.getAttribute('data-percent')) > parseFloat(tdB.getAttribute('data-percent')) ? 1 : -1
  }
}
initTable(compareRows, 'behind')

// mountains
function random(min, max) {
  min = Math.ceil(min);
  max = Math.floor(max);
  return Math.floor(Math.random() * (max - min + 1)) + min;
}
document.querySelectorAll('.emoji').forEach(emoji => {
  const progress = parseFloat(emoji.getAttribute('data-percent')) / 100
  emoji.style.bottom = ((PROGRESS_Y_2 - PROGRESS_Y_1)*progress+PROGRESS_Y_1 + random(-1, 1)) + '%'
  emoji.style.left = ((PROGRESS_X_2 - PROGRESS_X_1)*progress+PROGRESS_X_1 + random(-1, 1)) + '%'
})
function toggleMountains(index) {
  document.querySelectorAll('.historical-mountain').forEach((el, i) => {
    el.classList.toggle('hidden', i !== parseInt(index))
  })
}

// initialize progress charts
document.querySelectorAll('.progress-canvas').forEach(canvas => {
  const progressData = JSON.parse(canvas.dataset.graph)
  initProgressChart(canvas, Object.values(progressData), Object.keys(progressData), true)
})