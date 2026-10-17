import { useRef, useState } from 'react'

/**
 * Drag left/right edges to change 12-column grid placement.
 */
export default function ResizeHandles({ colStart, colSpan, gridRef, onResize, disabled = false }) {
  const [active, setActive] = useState(null)
  const dragRef = useRef(null)

  if (disabled) {
    return null
  }

  const begin = (edge) => (event) => {
    event.preventDefault()
    event.stopPropagation()

    const gridEl = gridRef?.current
    if (!gridEl) {
      return
    }

    dragRef.current = {
      edge,
      colStart,
      colSpan,
    }
    setActive(edge)

    const onMove = (moveEvent) => {
      const state = dragRef.current
      const grid = gridRef?.current
      if (!state || !grid) {
        return
      }

      const rect = grid.getBoundingClientRect()
      const x = Math.min(Math.max(moveEvent.clientX - rect.left, 0), rect.width)
      const colWidth = rect.width / 12
      const col = Math.min(12, Math.max(1, Math.ceil(x / colWidth) || 1))

      if (state.edge === 'right') {
        const nextSpan = Math.max(1, Math.min(12 - state.colStart + 1, col - state.colStart + 1))
        onResize({
          colStart: state.colStart,
          colSpan: nextSpan,
        })
        return
      }

      const rightEdge = state.colStart + state.colSpan - 1
      const nextStart = Math.max(1, Math.min(rightEdge, col))
      onResize({
        colStart: nextStart,
        colSpan: rightEdge - nextStart + 1,
      })
    }

    const onUp = () => {
      dragRef.current = null
      setActive(null)
      window.removeEventListener('pointermove', onMove)
      window.removeEventListener('pointerup', onUp)
    }

    window.addEventListener('pointermove', onMove)
    window.addEventListener('pointerup', onUp)
  }

  return (
    <>
      <button
        type="button"
        className={`resize-handle resize-left ${active === 'left' ? 'active' : ''}`}
        aria-label="Resize from left"
        onPointerDown={begin('left')}
      />
      <button
        type="button"
        className={`resize-handle resize-right ${active === 'right' ? 'active' : ''}`}
        aria-label="Resize from right"
        onPointerDown={begin('right')}
      />
      <div className="resize-badge">{colSpan}/12</div>
    </>
  )
}
