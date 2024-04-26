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
  else if (column === 'badges') {
    return parseInt(tdA.getAttribute('data-badges')) > parseInt(tdB.getAttribute('data-badges')) ? 1 : -1
  }
  else if (column == 'percent') {
    return parseFloat(tdA.getAttribute('data-percent')) > parseFloat(tdB.getAttribute('data-percent')) ? 1 : -1
  }
}
initTable(compareRows, 'behind')





function random(min, max) {
  min = Math.ceil(min);
  max = Math.floor(max);
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

const emojis = document.querySelectorAll('.emoji')
const mountains = document.querySelectorAll('.mountain')

mountains.forEach(mountain => {
  emojis.forEach(em => {
    const progress = parseFloat(em.getAttribute('data-percent')) / 100
    em.style.bottom = (PROGRESS_Y_2*progress + (1-progress)*random(-0.5, 0.5)) + '%'
    em.style.left = (PROGRESS_X_2 + (1-progress)*random(-5, 5)) + '%'
  })
})

const mountainSelect = document.getElementById('mountain-select')
function toggleMountains() {
  document.querySelectorAll('.mountain-wrap').forEach((el, i) => {
    el.classList.toggle('hidden', i !== parseInt(mountainSelect.value))
  })
}
toggleMountains()