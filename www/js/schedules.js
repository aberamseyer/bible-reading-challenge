const compareRows = (a, b, currentSortColumn) => {
  const column = currentSortColumn
  const tdA = a.querySelector(`td[data-${column}]`)
  const tdB = b.querySelector(`td[data-${column}]`)
  
  if (column === "name") {
    return tdA.textContent.localeCompare(tdB.textContent)
  }
  else if (column === "start") {
    return new Date(tdA.getAttribute('data-start')) > new Date(tdB.getAttribute('data-start'))
  }
  else if (column === "end") {
    return new Date(tdA.getAttribute('data-end')) > new Date(tdB.getAttribute('data-end'))
  }
}
initTable(compareRows)