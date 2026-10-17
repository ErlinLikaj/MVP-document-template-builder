import { useCallback, useEffect, useState } from 'react'
import { deleteTemplate, getTemplate, listTemplates } from './api'
import Builder from './components/Builder'
import TemplateList from './components/TemplateList'

export default function App() {
  const [view, setView] = useState('list')
  const [templates, setTemplates] = useState([])
  const [activeTemplate, setActiveTemplate] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const refresh = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const response = await listTemplates()
      setTemplates(response.data || [])
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    refresh()
  }, [refresh])

  const openTemplate = async (template) => {
    try {
      const response = await getTemplate(template.id)
      setActiveTemplate(response.data)
      setView('builder')
    } catch (err) {
      setError(err.message)
    }
  }

  const createNew = () => {
    setActiveTemplate(null)
    setView('builder')
  }

  const handleDelete = async (id) => {
    if (!window.confirm('Delete this template?')) {
      return
    }

    try {
      await deleteTemplate(id)
      await refresh()
    } catch (err) {
      setError(err.message)
    }
  }

  if (view === 'builder') {
    return (
      <Builder
        template={activeTemplate}
        onBack={() => {
          setView('list')
          refresh()
        }}
        onSaved={(saved) => {
          setActiveTemplate(saved)
          refresh()
        }}
      />
    )
  }

  return (
    <main className="app-shell">
      {error ? (
        <div className="status error" style={{ display: 'flex', gap: '1rem', alignItems: 'center', justifyContent: 'space-between' }}>
          <span>{error}</span>
          <button type="button" className="ghost" onClick={refresh}>
            Retry
          </button>
        </div>
      ) : null}
      <TemplateList
        templates={templates}
        loading={loading}
        onCreate={createNew}
        onOpen={openTemplate}
        onDelete={handleDelete}
      />
    </main>
  )
}
