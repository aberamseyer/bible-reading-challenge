const compareRows = (a, b, currentSortColumn) => {
  const column = currentSortColumn
  const tdA = a.querySelector(`td[data-${column}]`)
  const tdB = b.querySelector(`td[data-${column}]`)
  
  if (column === "name") {
    return tdA.textContent.localeCompare(tdB.textContent)
  }
  else if (column === "enabled") {
    const a_enabled = parseInt(tdA.getAttribute('data-enabled'))
    const b_enabled = parseInt(tdB.getAttribute('data-enabled'))
    if (a_enabled == b_enabled)
      return compareRows(a, b, 'name')
    else
      return a_enabled > b_enabled ? 1 : -1
  }
  else if (column === "domain") {
    return tdA.textContent.localeCompare(tdB.textContent)
  }
  else if (column === "test-domain") {
    return tdA.textContent.localeCompare(tdB.textContent)
  }
  else if (column === "contact") {
    return tdA.textContent.localeCompare(tdB.textContent)
  }
}
initTable(compareRows, 'name')