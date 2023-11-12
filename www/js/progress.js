const compareRows = (a, b, currentSortColumn) => {
  const column = currentSortColumn
  const tdA = a.querySelector(`td[data-${column}]`)
  const tdB = b.querySelector(`td[data-${column}]`)
  
  if (column === "name") {
    return tdA.textContent.localeCompare(tdB.textContent)
  }
  else if (column === "behind") {
    return parseInt(tdA.getAttribute('data-behind')) > parseInt(tdB.getAttribute('data-behind'))
  }
  else if (column === "streak") {
    return parseInt(tdA.getAttribute('data-streak')) > parseInt(tdB.getAttribute('data-streak'))
  }
  else if (column === 'badges') {
    return parseInt(tdA.getAttribute('data-badges')) > parseInt(tdB.getAttribute('data-badges'))
  }
}
initTable(compareRows, 'behind')