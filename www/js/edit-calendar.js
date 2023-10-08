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
    const activeDay = document.querySelector('.reading-day.active')
    const currentPassage = activeDay.querySelector('input[data-passage]').value
    let book, chp
    if (!currentPassage) {
      book = 'Matthew'
      chp = 1
    }
    else {
      const arr = currentPassage.split(';')
      const end = arr.pop().trim()
      const matches = end.match(BOOK_REGEX)
      book = matches[1]
      chp = matches[3] || matches[2]
    }
    const fillAfter = activeDay.getAttribute('data-date')
    const chpsPerDay = +document.getElementById('chps-per-day').value || 1
    fetch(`?calendar_id=${CALENDAR_ID}&fill_dates=${fillAfter}&rate=${chpsPerDay}&start_book=${book}&start_chp=${chp}&${days}`)
    .then(rsp => rsp.json())
    .then(data => {
      calendarDays.forEach(cd => {
        const date = cd.getAttribute('data-date')
        if (data.hasOwnProperty(date)) {
          cd.querySelector('input[data-passage]').value = data[date]
          cd.querySelector('small').textContent = data[date]
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
      const date = tableCell.getAttribute('data-date')
      const matchingDay = data.find(sd => sd.date === date)
      if (matchingDay) {
        tableCell.querySelector('input[data-passage]').value = matchingDay.passage
        tableCell.querySelector('input[data-id]').value = matchingDay.id
        tableCell.querySelector('small').textContent = matchingDay.passage
      }
    })
  })
})()