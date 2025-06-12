(() => {
  const calendarDays = document.querySelectorAll(".reading-day:not(.inactive)");
  const fillBtn = document.getElementById("fill");
  const clearBtn = document.getElementById("clear");
  const scheduleSel = document.getElementById("schedule-sel");
  const selectedFile = document.getElementById("selected-file");
  const fillModeSel = document.getElementById("fill-mode");
  const PASSAGE_SELECTOR = "input[data-passage]";
  const LABEL_SELECTOR = ".label";

  // helper functions
  function resizeInput(el) {
    el.size = Math.max(el.value.length - 2, 1);
  }
  function flashClass(el, classToAdd) {
    el.classList.add("flashing");
    const classes = ["active", "danger", "warning"];
    let currClass = "";
    for (let activeClass of classes) {
      if (el.classList.contains(activeClass)) currClass = activeClass;
    }
    currClass && el.classList.remove(currClass);
    el.classList.add(classToAdd);
    setTimeout(() => {
      el.classList.remove(classToAdd);
      currClass && el.classList.add(currClass);
      setTimeout(() => el.classList.remove("flashing"), 150);
    }, 150);
  }
  function validatePassage(passage) {
    if (passage == "") return true;
    else return passage.split(";").reduce((acc, curr) => acc && BOOKS_RE.test(curr.trim()), true);
  }
  function labelElChanged(labelEl) {
    const newVal = labelEl.value.trim();
    const passageEl = labelEl.closest(".reading-day").querySelector(PASSAGE_SELECTOR);
    if (newVal !== passageEl.value) {
      if (validatePassage(newVal)) {
        flashClass(labelEl, "active");
        passageEl.value = labelEl.value;
      } else {
        flashClass(labelEl, "danger");
        labelEl.value = passageEl.value;
      }
      resizeInput(labelEl);
    }
  }

  // editor initialization
  calendarDays.forEach((tableCell) => {
    const labelEl = tableCell.querySelector(LABEL_SELECTOR);

    // event listeners for editing dates
    labelEl.addEventListener("input", (e) => resizeInput(e.target));
    labelEl.addEventListener("blur", () => labelElChanged(labelEl));

    // present and future dates can be activated (for filling and clearing)
    if (!tableCell.classList.contains("past")) {
      tableCell.querySelector(".date").addEventListener("click", () => {
        const isActive = tableCell.classList.contains("active");
        calendarDays.forEach((x) => x.classList.remove("active"));
        if (isActive) {
          fillBtn.disabled = true;
          clearBtn.disabled = true;
        } else {
          tableCell.classList.add("active");
          fillBtn.disabled = false;
          clearBtn.disabled = false;
        }
      });
    }
  });
  function toggleSelects() {
    scheduleSel.classList.toggle("hidden", fillModeSel.value !== "automatic");
    selectedFile.classList.toggle("hidden", fillModeSel.value !== "import");
  }
  fillModeSel.addEventListener("change", () => toggleSelects());
  toggleSelects();

  clearBtn.addEventListener("click", () => {
    let clearing = false;
    calendarDays.forEach((cd) => {
      if (clearing) {
        const labelEl = cd.querySelector(LABEL_SELECTOR);
        labelEl.value = "";
        labelElChanged(labelEl);
      }
      if (cd.classList.contains("active")) {
        clearing = true;
      }
    });
  });

  fillBtn.addEventListener("click", () => {
    // build list of selected 'day of week' checkboxes
    const days = Array.from(document.querySelectorAll('[name="days[]"]:checked'))
      .map((x) => `days[]=${x.value}`)
      .join("&");

    // set the currently active day and the previous day
    const activeDay = document.querySelector(".reading-day.active");
    const allDays = Array.from(calendarDays);
    const i = allDays.findIndex((el) => el === activeDay);
    const prevDay = i <= 0 ? activeDay : allDays[i - 1];

    // what passage we are starting from
    let currentPassage = activeDay.querySelector(PASSAGE_SELECTOR).value;
    if (!currentPassage) {
      currentPassage = `Matthew 1`;
    }
    let prevPassage = prevDay.querySelector(PASSAGE_SELECTOR).value;
    if (!prevPassage) {
      prevPassage = currentPassage;
    }

    let differences = [];

    /*
     * Build an array of differences between corresponding passage segments:
     * activeDay passage: Genesis 4-6; Matthew 2
     * prevDay passage:   Genesis 1-3; Matthew 1
     * result: [3, 1] (3 chapters in the Genesis line and 1 chapter in the Matthew line)
     */
    const activeSplit = currentPassage.split(";");
    const prevSplit = prevPassage.split(";");
    for (let i = 0; i < activeSplit.length && i < prevSplit.length; i++) {
      const matchesCurr = activeSplit[i].trim().match(BOOKS_RE);
      const bookCurr = matchesCurr[1];
      const chpCurr = parseInt(matchesCurr[5] || matchesCurr[3] || matchesCurr[2]);

      const matchesPrev = prevSplit[i].trim().match(BOOKS_RE);
      const bookPrev = matchesPrev[1];
      const chpPrev = parseInt(matchesPrev[5] || matchesPrev[3] || matchesPrev[2]);

      differences.push(
        "d[]=" +
          (bookCurr !== bookPrev
            ? chpCurr +
              (BOOK_CHAPTERS.find(
                (b) =>
                  BOOKS_ABBR_LOOKUP[b.name.toLowerCase()] ===
                  BOOKS_ABBR_LOOKUP[bookPrev.toLowerCase()],
              ).chapters.length -
                chpCurr)
            : chpCurr - chpPrev || 1),
      );
    }

    const queryStrArr = currentPassage
      .split(";")
      .map((portion) => {
        const matches = portion.trim().match(BOOKS_RE);
        const book = matches[1];
        const chp = matches[5] || matches[3] || matches[2];
        return `start_book[]=${BOOKS_ABBR_LOOKUP[book.toLowerCase()]}&start_chp[]=${chp}`;
      })
      .slice(0, differences.length); // slicing to ensure queryStrArr and differences are the same length

    const fillAfter = activeDay.getAttribute("data-date");

    const formData = new FormData();
    formData.append("calendar_id", CALENDAR_ID);
    formData.append("fill_dates", fillAfter);
    formData.append("fill_mode", fillModeSel.value);
    formData.append("fill_with", scheduleSel.value);
    if (selectedFile.files[0]) formData.append("import", selectedFile.files[0]);

    fetch(`?${queryStrArr.join("&")}&${differences.join("&")}&${days}`, {
      method: "POST",
      body: formData,
    })
      .then((rsp) => rsp.json())
      .then((data) => {
        calendarDays.forEach((cd) => {
          const date = cd.dataset.date;
          if (data.hasOwnProperty(date)) {
            const labelEl = cd.querySelector(LABEL_SELECTOR);
            labelEl.value = data[date].join("; ");
            labelElChanged(labelEl);
          }
        });
      });
  });

  // merge and shift buttons on an active day
  document.querySelectorAll(".arrow-container div").forEach((arrowBtn) => {
    arrowBtn.addEventListener("click", () => {
      let left = false;
      if (arrowBtn.closest(".arrow-container").nextElementSibling) {
        left = true;
      }
      const parent = arrowBtn.closest(".reading-day");
      if (arrowBtn.nextElementSibling) {
        // shift everything over
        const daysInOrder = left ? Array.from(calendarDays).toReversed() : Array.from(calendarDays);
        let shifting = false,
          prevPassage = parent.querySelector(PASSAGE_SELECTOR).value;
        for (let i = 0; i < daysInOrder.length; i++) {
          const day = daysInOrder[i];
          if (shifting) {
            if (prevPassage === "") {
              // WAIT
              // if the day we are shifting to is empty, we actually want a merge and then a stop.
              // just think about it. it logically makes sense
              break;
            }
            const labelEl = day.querySelector(LABEL_SELECTOR);
            const temp = labelEl.value;
            labelEl.value = prevPassage;
            prevPassage = temp;
            labelElChanged(labelEl);
          }
          if (day === parent) {
            const labelEl = day.querySelector(LABEL_SELECTOR);
            labelEl.value = "";
            labelElChanged(labelEl);
            shifting = true;
          }
        }
      } else {
        // merge to the (left)
        const mergingPassageEl = parent.querySelector(PASSAGE_SELECTOR),
          mergingLabelEl = parent.querySelector(LABEL_SELECTOR),
          startIndex = left ? 1 : 0,
          stopIndex = calendarDays.length - (left ? 0 : 1);
        for (let i = startIndex; i < stopIndex; i++) {
          if (calendarDays[i] === parent) {
            const passageEl = calendarDays[i + (left ? -1 : 1)].querySelector(PASSAGE_SELECTOR),
              labelEl = calendarDays[i + (left ? -1 : 1)].querySelector(LABEL_SELECTOR);

            labelEl.value = (passageEl.value + ";" + mergingPassageEl.value)
              .split(";")
              .map((x) => x.trim())
              .filter((x) => x)
              .join("; ");
            labelElChanged(labelEl);

            mergingLabelEl.value = "";
            labelElChanged(mergingLabelEl);
            break;
          }
        }
      }
    });
  });

  // fill calendar on page load
  fetch(`?get_dates=1&calendar_id=${CALENDAR_ID}`)
    .then((rsp) => rsp.json())
    .then((data) => {
      calendarDays.forEach((tableCell) => {
        const date = tableCell.dataset.date;
        const matchingDay = data.find((sd) => sd.date === date);
        if (matchingDay) {
          tableCell.querySelector(PASSAGE_SELECTOR).value = matchingDay.passage;
          tableCell.querySelector("input[data-id]").value = matchingDay.id;
          const inputLabelEl = tableCell.querySelector(LABEL_SELECTOR);
          inputLabelEl.value = matchingDay.passage;
          resizeInput(inputLabelEl);
          if (matchingDay.read) {
            tableCell.classList.add("active");
          }
        }
      });
    });
})();
