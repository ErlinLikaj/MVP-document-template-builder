import { useCallback, useRef } from 'react'
import ImageDropZone from './ImageDropZone'
import ResizeHandles from './ResizeHandles'

function BlockPreview({ block, selected, onSelect, onImageUploaded, onError, onImageHeight }) {
  const { type, props } = block

  if (type === 'header') {
    return (
      <div className={`block-preview block-header align-${props.align || 'left'}`}>
        <h3>{props.title || 'Header'}</h3>
        {props.subtitle ? <p>{props.subtitle}</p> : null}
      </div>
    )
  }

  if (type === 'image') {
    return (
      <ImageDropZone
        src={props.src}
        alt={props.alt}
        caption={props.caption}
        height={props.height || 200}
        selected={selected}
        onSelect={onSelect}
        onUploaded={(url, name) => onImageUploaded(block.id, url, name)}
        onError={onError}
        onHeightResize={(height) => onImageHeight?.(block.id, height)}
      />
    )
  }

  if (type === 'two_column') {
    return (
      <div className="two-col block-preview">
        <div className="col-pane">
          <span className="col-label">Left</span>
          <p>{props.left}</p>
        </div>
        <div className="col-pane">
          <span className="col-label">Right</span>
          <p>{props.right}</p>
        </div>
      </div>
    )
  }

  if (type === 'footer') {
    return (
      <div className="footer-slot block-preview">
        <span>{props.text}</span>
        {props.showPageNumber ? <span>Page ·</span> : null}
      </div>
    )
  }

  return (
    <p className={`align-${props.align || 'left'} block-preview preview-text`}>
      {props.content}
    </p>
  )
}

function SectionSheet({
  block,
  index,
  selected,
  onSelect,
  onImageUploaded,
  onError,
  onResizeGrid,
  onImageHeight,
}) {
  const gridRef = useRef(null)

  const handleResize = useCallback(
    (grid) => {
      onResizeGrid?.(block.id, grid)
    },
    [block.id, onResizeGrid],
  )

  return (
    <article
      data-block-id={block.id}
      className={`page-sheet type-${block.type} ${selected ? 'selected' : ''}`}
      draggable={block.type !== 'image'}
      onDragStart={(event) => {
        if (block.type === 'image' || event.target.closest('.resize-handle')) {
          event.preventDefault()
          return
        }
        event.dataTransfer.setData('application/x-block', block.id)
        event.dataTransfer.effectAllowed = 'move'
      }}
      onClick={() => onSelect(block.id)}
    >
      <div className="sheet-toolbar">
        <span className="sheet-index">Section {index + 1}</span>
        <span className="sheet-type">{block.type.replace('_', ' ')}</span>
        <span className="sheet-span">
          col {block.grid.colStart} · span {block.grid.colSpan}
          {block.type === 'image' ? ` · ${block.props.height || 200}px` : ''}
        </span>
      </div>

      <div className="sheet-columns" ref={gridRef}>
        <div
          className={`sheet-block ${selected ? 'resizing-ready' : ''}`}
          style={{
            gridColumn: `${block.grid.colStart} / span ${block.grid.colSpan}`,
          }}
        >
          <BlockPreview
            block={block}
            selected={selected}
            onSelect={() => onSelect(block.id)}
            onImageUploaded={onImageUploaded}
            onError={onError}
            onImageHeight={onImageHeight}
          />
          <ResizeHandles
            colStart={block.grid.colStart}
            colSpan={block.grid.colSpan}
            gridRef={gridRef}
            onResize={handleResize}
            disabled={!selected}
          />
        </div>
      </div>
    </article>
  )
}

export default function Canvas({
  sections,
  selectedId,
  onSelect,
  onDropComponent,
  onDropImageFile,
  onReorder,
  onImageUploaded,
  onError,
  onResizeGrid,
  onImageHeight,
}) {
  const handleDragOver = (event) => {
    event.preventDefault()
    if ([...event.dataTransfer.types].includes('Files')) {
      event.dataTransfer.dropEffect = 'copy'
      return
    }
    event.dataTransfer.dropEffect = event.dataTransfer.types.includes('application/x-component')
      ? 'copy'
      : 'move'
  }

  const handleDrop = (event) => {
    event.preventDefault()

    const files = event.dataTransfer.files
    if (files?.length) {
      const imageFile = [...files].find((file) => file.type.startsWith('image/'))
      if (imageFile) {
        const targetId = event.target.closest('[data-block-id]')?.dataset.blockId
        onDropImageFile(imageFile, targetId)
        return
      }
    }

    const componentType = event.dataTransfer.getData('application/x-component')
    if (componentType) {
      onDropComponent(componentType)
      return
    }

    const fromId = event.dataTransfer.getData('application/x-block')
    const toId = event.target.closest('[data-block-id]')?.dataset.blockId
    if (fromId && toId && fromId !== toId) {
      onReorder(fromId, toId)
    }
  }

  return (
    <section className="canvas-wrap" onDragOver={handleDragOver} onDrop={handleDrop}>
      <div className="canvas-label">
        <span>Document sections</span>
        <span>Select a block · drag edges to resize (12-col)</span>
      </div>

      {sections.length === 0 ? (
        <div className="empty-canvas">
          <strong>Start composing</strong>
          <span>Drag a component from the left, or drop a photo to create an image block.</span>
        </div>
      ) : (
        <div className="page-stack">
          {sections.map((block, index) => (
            <SectionSheet
              key={block.id}
              block={block}
              index={index}
              selected={selectedId === block.id}
              onSelect={onSelect}
              onReorder={onReorder}
              onImageUploaded={onImageUploaded}
              onError={onError}
              onResizeGrid={onResizeGrid}
              onImageHeight={onImageHeight}
            />
          ))}
          <div className="page-drop-hint">Drop another block or photo here</div>
        </div>
      )}
    </section>
  )
}
