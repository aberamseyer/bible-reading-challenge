document.querySelectorAll('.draggable').forEach(el => {
  const draggableList = el
  let draggedItem = null
 
  const dragStart = e => {
    draggedItem = e.target
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData('text/html', draggedItem.outerHTML)
    draggedItem.classList.add('dragging')
  }
  
  const dragOver = e => {
    e.preventDefault()
    const targetItem = e.target.closest('label')
    if (targetItem && targetItem !== draggedItem) {
      const nextItem = targetItem.nextElementSibling || null
      draggableList.insertBefore(draggedItem, nextItem)
    }
  }
  
  const drop = e => {
    e.preventDefault()
    draggedItem.classList.remove('dragging')
    draggedItem = null
  } 

  // Event handlers for drag and drop
  draggableList.addEventListener('dragstart', dragStart)
  draggableList.addEventListener('dragover', dragOver)
  draggableList.addEventListener('drop', drop)
  
})