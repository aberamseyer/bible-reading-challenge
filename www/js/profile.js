function random(min, max) {
  min = Math.ceil(min);
  max = Math.floor(max);
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

// mountain
(() => {
  const emojis = document.querySelectorAll('.emoji')
  const mountain = document.querySelector('.mountain')
  
  setTimeout(() => {
    emojis.forEach(em => {
      const delay = random(0, 1500)
      const progress = parseFloat(em.getAttribute('data-percent')) / 100
      const time = progress * Math.log(progress * 5) + 1.5
      setTimeout(() => {
        em.classList.add('animated')
        em.style.transition = `${Math.round(time*10)/10}s all`
        em.style.bottom = (PROGRESS_Y_2*progress + (1-progress)*random(-0.5, 0.5)) + '%'
        em.style.left = (PROGRESS_X_2 + (1-progress)*random(-5, 5)) + '%'
        em.children[0].style.animation = `yAxis 0.2s ${Math.round(time / 0.2)} cubic-bezier(0.02, 0.01, 0.21, 1)`
        setTimeout(() => {
          em.classList.remove('animated')
          em.style.transition = `.2s all`
          em.children[0].style.animation = ``
        }, time * 1000)
      }, delay)
    })
  }, 500)
  
  // emoji mouse repulsion
  mountain.addEventListener('mousemove', e => {
    const mouseX = e.clientX
    const mouseY = e.clientY
  
    emojis.forEach(em => {
      const rect = em.getBoundingClientRect();
      const elementX = rect.left + rect.width / 2
      const elementY = rect.top + rect.height / 2
  
      const deltaX = mouseX - elementX
      const deltaY = mouseY - elementY
  
      const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY)
      const repelStrength = 10
  
      if (distance < 25) {
        const forceX = -repelStrength * (deltaX / distance)
        const forceY = -repelStrength * (deltaY / distance)
  
        em.children[0].style.transform = `translate(${forceX}px, ${forceY}px)`
      }
      else {
        em.children[0].style.transform = 'none'
      }
    })
  })
})();

// charts
(() => {
  const progressCanvas = document.querySelector('.progress-canvas')
  const progressData = JSON.parse(progressCanvas.dataset.graph)
  
  initProgressChart(progressCanvas, Object.values(progressData), Object.keys(progressData))
  
  const canvas = document.querySelector('.weekly-counts-canvas')
  const weeklyCountsData = JSON.parse(canvas.dataset.graph)

  initWeeklyCountsChart(canvas, Object.keys(weeklyCountsData), Object.values(weeklyCountsData))
})();