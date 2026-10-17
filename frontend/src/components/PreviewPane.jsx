export default function PreviewPane({ html, loading, full = false }) {
  return (
    <section className={`preview-pane ${full ? 'preview-full' : ''}`}>
      <div className="preview-header">
        <div>
          <p className="panel-kicker">Output</p>
          <h2>Document preview</h2>
        </div>
        {loading ? (
          <span className="pulse">Refreshing…</span>
        ) : (
          <span className="muted">Each section renders with spacing</span>
        )}
      </div>
      <div className="preview-stage">
        <iframe
          title="Template preview"
          srcDoc={
            html ||
            '<p style="font-family:Georgia,serif;color:#667;padding:32px;">Preview appears after you add blocks.</p>'
          }
        />
      </div>
    </section>
  )
}
