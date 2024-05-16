(() => {
  // match[1] will be the book name, match[2] will be the first chapter, match[3] (if it exists) will be the last chapter
  const BOOK_REGEX = new RegExp(`^(${BOOK_CHAPTERS.map(x => `(?:${x.name})`).join('|')}) (\\d+)(?:-(\\d+))?$`)

  const calendarDays = document.querySelectorAll('.reading-day:not(.inactive)');
  const fillBtn = document.getElementById('fill')
  // fill each subsequent day with the next chapter in the bible
  fillBtn.onclick = () => {
    const days = Array.from(
      document.querySelectorAll('[name="days[]"]:checked')
    ).map(x => `days[]=${x.value}`).join('&')

    const allDays = Array.from(document.querySelectorAll('.reading-day'));
    let activeDay, prevDay
    for (let i=0; i < allDays.length; i++) {
      let el = allDays[i]
      if (el.classList.contains('active')) {
        activeDay = el
        prevDay = i === 0 ? el : allDays[ i-1 ]
        break
      }
    }
    if (!activeDay) {
      return
    }

    let currentPassage = activeDay.querySelector('input[data-passage]').value
    if (!currentPassage) {
      currentPassage = `Matthew 1`
    }
    let prevPassage = prevDay.querySelector('input[data-passage]').value
    if (!prevPassage) {
      prevPassage = currentPassage
    }

    let differences = []

    for(let i=0; i < currentPassage.split(';').length; i++) {
      const matchesCurr = currentPassage.split(';')[i].trim().match(BOOK_REGEX)
      const bookCurr = matchesCurr[1]
      const chpCurr = parseInt(matchesCurr[3] || matchesCurr[2])

      const matchesPrev = prevPassage.split(';')[i].trim().match(BOOK_REGEX)
      const bookPrev = matchesPrev[1]
      const chpPrev = parseInt(matchesPrev[3] || matchesPrev[2])
      differences.push('d[]=' + 
        (bookCurr !== bookPrev
          ? chpCurr + (parseInt(BOOK_CHAPTERS.find(b => b.name === bookPrev).chapters) - chpCurr)
          : chpCurr - chpPrev)
      )
    }
    
    const queryStrArr = currentPassage.split(';').map(portion => {
      const matches = portion.trim().match(BOOK_REGEX)
      const book = matches[1]
      const chp = matches[3] || matches[2]
      return `start_book[]=${book}&start_chp[]=${chp}`
    })

    const fillAfter = activeDay.getAttribute('data-date')
    fetch(`?calendar_id=${CALENDAR_ID}&fill_dates=${fillAfter}&${differences.join('&')}&${queryStrArr.join('&')}&${days}`)
    .then(rsp => rsp.json())
    .then(data => {
      calendarDays.forEach(cd => {
        const date = cd.dataset.date
        if (data.hasOwnProperty(date)) {
          cd.querySelector('input[data-passage]').value = data[date].join('; ')
          cd.querySelector('small').textContent = data[date].join('; ')
        }
      })
    })
  }
  // clear all days following the currently activated one
  const clearBtn = document.getElementById('clear')
  clearBtn.onclick = () => {
    let clearing = false
    calendarDays.forEach(cd => {
      if (clearing) {
        cd.querySelector('input[data-passage]').value = ''
        cd.querySelector('small').textContent = ''
      }
      if (cd.classList.contains('active')) {
        clearing = true
      }
    })
  }

  // validates a passage that the user enters
  function validPassagePiece(piece) {
    piece = piece.trim()
    const matches = piece.match(BOOK_REGEX)
    if (matches) {
      for(let j = 0; j < BOOK_CHAPTERS.length; j++) {
        const book = BOOK_CHAPTERS[j]
        if (book.name == matches[1]) {
          if (+matches[3]) {
            return +matches[2] > 0 && +matches[3] > +matches[2] && +matches[3] <= book.chapters
          }
          else {
            return +matches[2] > 0 && +matches[2] <= book.chapters
          }
        }
      }
    }
    else {
      return false
    }
  }
  // event listeners for editing a day
  function validatePassage(passage) {
    if (passage == '') return true

    let valid = true;
    passage = passage.trim()
    let pieces = passage.split(';')
    for (let i = 0; i < pieces.length; i++) {
      valid = valid && validPassagePiece(pieces[i])
    }
    return pieces ? valid : false
  }
  calendarDays.forEach(tableCell => {
    // present and future dates can be activated (for filling and clearing)
    if (!tableCell.classList.contains('past')) {
      tableCell.querySelector('.date').onclick = () => {
        const isActive = tableCell.classList.contains('active')
        calendarDays.forEach(x => x.classList.remove('active'))
        if (isActive) {
          fillBtn.disabled = true
          clearBtn.disabled = true
        }
        else {
          tableCell.classList.add('active')
          fillBtn.disabled = false
          clearBtn.disabled = false
        }
      }
    }
    // future dates are editable
    tableCell.ondblclick = () => {
      if (tableCell.classList.contains('future')) {
        const small = tableCell.querySelector('small')
        small.remove()
        const input = document.createElement('textarea')
        input.value = small.textContent
        input.classList.add('edit-input')
        input.onblur = () => {
          const newVal = input.value
          if (validatePassage(newVal)) {
            small.textContent = newVal
            tableCell.querySelector('input[data-passage]').value = newVal
          }
          input.remove()
          tableCell.appendChild(small)
        }
        tableCell.appendChild(input)
        input.focus()
      }
    }
  })

  // fill calendar on page load
  fetch(`?get_dates=1&calendar_id=${CALENDAR_ID}`).then(rsp => rsp.json())
  .then(data => {
    calendarDays.forEach(tableCell => {
      const date = tableCell.dataset.date
      const matchingDay = data.find(sd => sd.date === date)
      if (matchingDay) {
        tableCell.querySelector('input[data-passage]').value = matchingDay.passage
        tableCell.querySelector('input[data-id]').value = matchingDay.id
        tableCell.querySelector('small').textContent = matchingDay.passage
        if (matchingDay.read) {
          tableCell.classList.add('active')
        }
      }
    })
  })
})()