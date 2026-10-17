import ImageDropZone from './ImageDropZone'

const LABELS = {
  title: 'Title',
  subtitle: 'Subtitle',
  align: 'Alignment',
  content: 'Content',
  left: 'Left column',
  right: 'Right column',
  text: 'Footer text',
  showPageNumber: 'Show page number',
  alt: 'Alt text',
  caption: 'Caption',
  height: 'Height (px)',
  src: 'Image URL',
  colStart: 'Column start',
  colSpan: 'Column span',
}

export default function PropertyPanel({ block, onChange, onDelete, onError }) {
  if (!block) {
    return (
      <aside className="panel properties">
        <p className="panel-kicker">Inspector</p>
        <h2>Properties</h2>
        <p className="hint">Select a block on the canvas to edit its content and grid placement.</p>
      </aside>
    )
  }

  const updateProp = (key, value) => {
    onChange({
      ...block,
      props: {
        ...block.props,
        [key]: value,
      },
    })
  }

  const updateGrid = (key, value) => {
    onChange({
      ...block,
      grid: {
        ...block.grid,
        [key]: Number(value),
      },
    })
  }

  return (
    <aside className="panel properties">
      <div className="properties-header">
        <div>
          <p className="panel-kicker">Inspector</p>
          <h2>{block.type.replace('_', ' ')}</h2>
        </div>
        <button type="button" className="danger ghost" onClick={() => onDelete(block.id)}>
          Remove
        </button>
      </div>

      {block.type === 'image' ? (
        <div className="prop-group">
          <span className="prop-label">Photo</span>
          <ImageDropZone
            src={block.props.src}
            alt={block.props.alt}
            height={140}
            compact
            onUploaded={(url, name) => {
              onChange({
                ...block,
                props: {
                  ...block.props,
                  src: url,
                  alt: block.props.alt || name,
                },
              })
            }}
            onError={onError}
          />
        </div>
      ) : null}

      <div className="prop-group">
        <span className="prop-label">Grid</span>
        <label>
          {LABELS.colStart}
          <input
            type="number"
            min="1"
            max="12"
            value={block.grid.colStart}
            onChange={(e) => updateGrid('colStart', e.target.value)}
          />
        </label>
        <label>
          {LABELS.colSpan}
          <input
            type="number"
            min="1"
            max="12"
            value={block.grid.colSpan}
            onChange={(e) => updateGrid('colSpan', e.target.value)}
          />
        </label>
      </div>

      <div className="prop-group">
        <span className="prop-label">Content</span>
        {Object.entries(block.props || {}).map(([key, value]) => {
          if (block.type === 'image' && key === 'src') {
            return null
          }

          if (typeof value === 'boolean') {
            return (
              <label key={key} className="checkbox">
                <input
                  type="checkbox"
                  checked={value}
                  onChange={(e) => updateProp(key, e.target.checked)}
                />
                {LABELS[key] || key}
              </label>
            )
          }

          if (key === 'align') {
            return (
              <label key={key}>
                {LABELS.align}
                <select value={value || 'left'} onChange={(e) => updateProp(key, e.target.value)}>
                  <option value="left">Left</option>
                  <option value="center">Center</option>
                  <option value="right">Right</option>
                </select>
              </label>
            )
          }

          if (typeof value === 'number') {
            return (
              <label key={key}>
                {LABELS[key] || key}
                <input
                  type="number"
                  value={value}
                  onChange={(e) => updateProp(key, Number(e.target.value))}
                />
              </label>
            )
          }

          const isLong =
            String(value ?? '').length > 60 ||
            key === 'content' ||
            key === 'left' ||
            key === 'right'

          return (
            <label key={key}>
              {LABELS[key] || key}
              {isLong ? (
                <textarea
                  rows={4}
                  value={value ?? ''}
                  onChange={(e) => updateProp(key, e.target.value)}
                />
              ) : (
                <input
                  type="text"
                  value={value ?? ''}
                  onChange={(e) => updateProp(key, e.target.value)}
                />
              )}
            </label>
          )
        })}
      </div>
    </aside>
  )
}
