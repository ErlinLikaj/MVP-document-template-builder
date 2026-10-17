export default function TemplateList({ templates, loading, onCreate, onOpen, onDelete }) {
  return (
    <div className="template-list">
      <header className="hero-bar">
        <div>
          <p className="eyebrow">Template Studio</p>
          <h1>Build documents visually</h1>
          <p className="lede">
            Compose blocks on a 12-column grid, drop in photos, then export polished PDF or Word files.
          </p>
        </div>
        <button type="button" className="primary-lg" onClick={onCreate}>
          New template
        </button>
      </header>

      {loading ? <p className="muted">Loading templates…</p> : null}

      {!loading && templates.length === 0 ? (
        <div className="empty-state">
          <strong>No templates yet</strong>
          <p>Create your first layout and start dropping blocks and photos.</p>
          <button type="button" onClick={onCreate}>
            Create first template
          </button>
        </div>
      ) : (
        <ul className="template-cards">
          {templates.map((template) => (
            <li key={template.id}>
              <button type="button" className="template-card" onClick={() => onOpen(template)}>
                <span className="card-kicker">
                  {(template.structure?.sections || []).length} blocks
                </span>
                <strong>{template.name}</strong>
                <span>{template.description || 'No description'}</span>
              </button>
              <button
                type="button"
                className="danger ghost"
                onClick={() => onDelete(template.id)}
              >
                Delete
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
