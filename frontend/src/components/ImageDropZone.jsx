import { useRef, useState } from 'react'
import { uploadImage } from '../api'

export default function ImageDropZone({
  src,
  alt = 'Image',
  caption = '',
  height = 160,
  selected = false,
  onUploaded,
  onError,
  onSelect,
  onHeightResize,
  compact = false,
}) {
  const inputRef = useRef(null)
  const [dragging, setDragging] = useState(false)
  const [uploading, setUploading] = useState(false)
  const heightDrag = useRef(null)

  const handleFiles = async (files) => {
    const file = files?.[0]
    if (!file) {
      return
    }

    if (!file.type.startsWith('image/')) {
      onError?.('Please drop an image file (JPG, PNG, GIF, WebP).')
      return
    }

    setUploading(true)
    try {
      const response = await uploadImage(file)
      onUploaded?.(response.data.url, file.name)
    } catch (error) {
      onError?.(error.message)
    } finally {
      setUploading(false)
      setDragging(false)
    }
  }

  const beginHeightResize = (event) => {
    event.preventDefault()
    event.stopPropagation()
    onSelect?.()

    heightDrag.current = {
      startY: event.clientY,
      startHeight: Number(height) || 160,
    }

    const onMove = (moveEvent) => {
      if (!heightDrag.current) {
        return
      }
      const delta = moveEvent.clientY - heightDrag.current.startY
      const next = Math.max(80, Math.min(600, Math.round(heightDrag.current.startHeight + delta)))
      onHeightResize?.(next)
    }

    const onUp = () => {
      heightDrag.current = null
      window.removeEventListener('pointermove', onMove)
      window.removeEventListener('pointerup', onUp)
    }

    window.addEventListener('pointermove', onMove)
    window.addEventListener('pointerup', onUp)
  }

  return (
    <div
      className={`image-drop ${dragging ? 'dragging' : ''} ${src ? 'has-image' : ''} ${compact ? 'compact' : ''} ${selected ? 'selected' : ''}`}
      style={{ height: `${height}px` }}
      onDragEnter={(event) => {
        event.preventDefault()
        event.stopPropagation()
        setDragging(true)
      }}
      onDragOver={(event) => {
        event.preventDefault()
        event.stopPropagation()
        event.dataTransfer.dropEffect = 'copy'
      }}
      onDragLeave={(event) => {
        event.preventDefault()
        if (!event.currentTarget.contains(event.relatedTarget)) {
          setDragging(false)
        }
      }}
      onDrop={(event) => {
        event.preventDefault()
        event.stopPropagation()
        handleFiles(event.dataTransfer.files)
      }}
      onClick={(event) => {
        event.stopPropagation()
        onSelect?.()
      }}
      role="button"
      tabIndex={0}
      onKeyDown={(event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault()
          onSelect?.()
        }
      }}
    >
      <input
        ref={inputRef}
        type="file"
        accept="image/png,image/jpeg,image/jpg,image/gif,image/webp"
        hidden
        onChange={(event) => handleFiles(event.target.files)}
      />

      {src ? (
        <>
          <img src={src} alt={alt || 'Uploaded image'} style={{ height: '100%', width: '100%', objectFit: 'cover' }} />
          <div className="image-drop-overlay">
            <button
              type="button"
              className="image-replace-btn"
              onClick={(event) => {
                event.stopPropagation()
                inputRef.current?.click()
              }}
            >
              {uploading ? 'Uploading…' : 'Replace photo'}
            </button>
          </div>
        </>
      ) : (
        <div
          className="image-drop-empty"
          onClick={(event) => {
            event.stopPropagation()
            onSelect?.()
            inputRef.current?.click()
          }}
        >
          <strong>{uploading ? 'Uploading…' : 'Drop image here'}</strong>
          <span>or click to browse · JPG, PNG, WebP</span>
        </div>
      )}

      {caption ? <small className="image-caption">{caption}</small> : null}

      {selected && onHeightResize ? (
        <>
          <div className="image-height-badge">{height}px</div>
          <button
            type="button"
            className="resize-handle resize-bottom"
            aria-label="Resize image height"
            onPointerDown={beginHeightResize}
          />
        </>
      ) : null}
    </div>
  )
}
