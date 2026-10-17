const META = {
  header: { icon: 'H', tone: 'tone-header' },
  text: { icon: 'T', tone: 'tone-text' },
  image: { icon: 'IMG', tone: 'tone-image' },
  two_column: { icon: 'Ⅱ', tone: 'tone-columns' },
  footer: { icon: 'F', tone: 'tone-footer' },
}

export default function ComponentPalette({ components, onAdd }) {
  return (
    <aside className="panel palette">
      <p className="panel-kicker">Blocks</p>
      <h2>Components</h2>
      <p className="hint">Each add creates a new section page on the canvas.</p>
      <ul>
        {components.map((component) => {
          const meta = META[component.type] || { icon: '+', tone: '' }
          return (
            <li
              key={component.type}
              draggable
              onDragStart={(event) => {
                event.dataTransfer.setData('application/x-component', component.type)
                event.dataTransfer.effectAllowed = 'copy'
              }}
            >
              <button
                type="button"
                className={`palette-item ${meta.tone}`}
                onClick={() => onAdd(component)}
              >
                <span className="palette-icon">{meta.icon}</span>
                <span className="palette-copy">
                  <strong>{component.label}</strong>
                  <span>{component.description}</span>
                </span>
              </button>
            </li>
          )
        })}
      </ul>
    </aside>
  )
}
