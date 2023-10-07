const table = document.querySelector("table")
const headers = table.querySelectorAll("th[data-sort]")
const tableRows = Array.from(table.querySelectorAll("tbody tr"))
let currentSortColumn = 'name'
let isAscending = true

// Function to toggle sorting icons
function toggleSortIcon(icon) {
  headers.forEach(header => {
    const icon = header.querySelector(".sort-icon")
    if (icon)
      icon.classList.remove("asc", "desc")
  })
  icon.classList.toggle("asc", isAscending)
  icon.classList.toggle("desc", !isAscending)
}

// Function to compare row data based on the sorting criteria
function compareRows(a, b) {
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

// Function to handle header click
function handleHeaderClick(event) {
  const clickedHeader = event.target.closest("th[data-sort]")
  if (!clickedHeader)
    return

  const column = clickedHeader.getAttribute("data-sort")

  if (currentSortColumn === column)
    isAscending = !isAscending
  else
    isAscending = true

  currentSortColumn = column
  toggleSortIcon(clickedHeader.querySelector('.sort-icon'))

  tableRows.sort(compareRows)

  if (!isAscending) {
    tableRows.reverse()
  }

  tableRows.forEach(row =>
    table.querySelector("tbody").appendChild(row))
}

// Add event listeners to the table headers
headers.forEach(header =>
  header.addEventListener("click", handleHeaderClick))

