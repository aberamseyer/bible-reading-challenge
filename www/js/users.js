const table = document.querySelector("table")
const headers = table.querySelectorAll("th[data-sort]")
const rows = Array.from(table.querySelectorAll("tbody tr"))
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
  else if (column === "last-read") {
    return new Date(tdA.getAttribute('data-last-read')) < new Date(tdB.getAttribute('data-last-read'))
  }
  else if (column === "email") {
    const a_email = parseInt(tdA.getAttribute('data-email'))
    const b_email = parseInt(tdB.getAttribute('data-email'))
    if (a_email == b_email)
      return a.querySelector('td[data-name]').textContent.localeCompare(b.querySelector('td[data-name]').textContent)
    else
      return a_email > b_email
  }
  else if (column === 'trend') {
    const a_arr = JSON.parse(a.querySelector('[data-graph]').getAttribute('data-graph'))
    const b_arr = JSON.parse(b.querySelector('[data-graph]').getAttribute('data-graph'))
    return a_arr.reduce((acc, curr) => acc + curr) > b_arr.reduce((acc, curr) => acc + curr)
  }
  else if (column === 'period') {
    return a.querySelectorAll('.active').length > b.querySelectorAll('.active').length
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

  rows.sort(compareRows)

  if (!isAscending) {
    rows.reverse()
  }

  rows.forEach(row =>
    table.querySelector("tbody").appendChild(row))
}

// Add event listeners to the table headers
headers.forEach(header =>
  header.addEventListener("click", handleHeaderClick))
