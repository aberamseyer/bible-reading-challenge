(() => {
  function countAllDays(startDateStr, endDateStr) {
    let start = new Date(`${startDateStr}T00:00:00Z`);
    let end = new Date(`${endDateStr}T00:00:00Z`);
    
    if (start > end) {
      [start, end] = [end, start]
    }
    const differenceMs = Math.abs(end - start)

    const result = Math.floor(differenceMs / (1000 * 60 * 60 * 24)) + 1
    return result ? result : 0
  }

  function countWeekdays(startDateStr, endDateStr) {
    let start = new Date(`${startDateStr}T00:00:00Z`);
    let end = new Date(`${endDateStr}T00:00:00Z`);
    
    if (start > end) {
      [start, end] = [end, start]
    }

    let count = 0;
    let currentDate = new Date(start);

    while (currentDate <= end) {
      const dayOfWeek = currentDate.getUTCDay();
      // Count weekdays (Monday to Friday)
      if (dayOfWeek !== 0 && dayOfWeek !== 6) {
        count++;
      }
      // Move to the next day (in UTC)
      currentDate.setUTCDate(currentDate.getUTCDate() + 1);
    }

    return count;
}

  const startDate = document.querySelector('[name=start_date]')
  const endDate = document.querySelector('[name=end_date]')

  const dateChanged = function() {
    document.querySelector('[data-weekdays]').textContent = countWeekdays(
      startDate.value,
      endDate.value
    )
    document.querySelector('[data-alldays]').textContent = countAllDays(
      startDate.value,
      endDate.value
    )
  }

  startDate.addEventListener('change', dateChanged)
  endDate.addEventListener('change', dateChanged)
  dateChanged()
})()