document.querySelectorAll('canvas[data-freq]').forEach(canvas => {
  const freqData = JSON.parse(canvas.dataset.freq)
  initializeHourlyFreqChart(canvas, Object.keys(freqData), Object.values(freqData));
})

document.querySelectorAll('canvas[data-email-stats]').forEach(canvas => {
  const emailData = JSON.parse(canvas.dataset.emailStats)
  initializeEmailStatsChart(canvas, emailData);
})