const progressCanvas = document.querySelector('.progress-canvas')
const progressData = JSON.parse(progressCanvas.dataset.graph)

initProgressChart(progressCanvas, Object.values(progressData), Object.keys(progressData))


const weeklycountsCanvas = document.querySelector('.weekly-counts-canvas')
const weeklycountsData = JSON.parse(weeklycountsCanvas.dataset.graph)

initWeeklyCountsChart(weeklycountsCanvas, Object.keys(weeklycountsData), Object.values(weeklycountsData))