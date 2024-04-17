function random(min, max) {
  min = Math.ceil(min);
  max = Math.floor(max);
  return Math.random() * (max - min + 1) + min;
}


setTimeout(() => {
  const yRange = [0, Math.floor(mountain.clientHeight / 250 * 100) - 20]
  // const xRange = [-10, 10]
  const emojis = document.querySelectorAll('.emoji')
  emojis.forEach(em => {
    const delay = random(0, 1500)
    const progress = parseFloat(em.getAttribute('data-percent')) / 100
    const time = progress * Math.log(progress * 5) + 1.5
    setTimeout(() => {
      em.classList.add('animated')
      em.style.transition = `${Math.round(time*10)/10}s all`
      em.style.bottom = (yRange[1]*progress + (1-progress)*random(-2, 2)) + '%'
      em.style.left = (50 + (1-progress)*random(-15, 15)) + '%'
      em.children[0].style.animation = `yAxis 0.2s ${Math.round(time / 0.2)} cubic-bezier(0.02, 0.01, 0.21, 1)`
    }, delay)
  })
}, 500)