function initTable(compareRows) {
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

    const compareRowsWrap = (a, b) =>
      compareRows(a, b, currentSortColumn)

    tableRows.sort(compareRowsWrap)

    if (!isAscending) {
      tableRows.reverse()
    }

    tableRows.forEach(row =>
      table.querySelector("tbody").appendChild(row))
  }

  // Add event listeners and sort icons to the table headers
  headers.forEach((header, i) => {
    const sortIcon = document.createElement('span')
    sortIcon.classList.add('sort-icon')
    if (i === 0) {
      sortIcon.classList.add('asc')
    }
    
    // this is to get the icon to appear before the text
    const text = header.textContent
    header.textContent = ''
    header.insertBefore(sortIcon, null)
    header.textContent = text
    header.addEventListener("click", handleHeaderClick)
  })
}