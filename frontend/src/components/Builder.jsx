import { useEffect, useMemo, useState } from 'react'
import {
  createTemplate,
  downloadRender,
  listComponents,
  renderPreview,
  updateTemplate,
  uploadImage,
} from '../api'
import { createBlock, emptyStructure, moveItem } from '../utils/blocks'
import Canvas from './Canvas'
import ComponentPalette from './ComponentPalette'
import PreviewPane from './PreviewPane'
import PropertyPanel from './PropertyPanel'

export default function Builder({ template, onSaved, onBack }) {
  const [components, setComponents] = useState([])
  const [name, setName] = useState(template?.name || 'Untitled template')
  const [description, setDescription] = useState(template?.description || '')
  const [structure, setStructure] = useState(template?.structure || emptyStructure())
  const [selectedId, setSelectedId] = useState(null)
  const [previewHtml, setPreviewHtml] = useState('')
  const [previewLoading, setPreviewLoading] = useState(false)
  const [status, setStatus] = useState('')
  const [saving, setSaving] = useState(false)
  const [templateId, setTemplateId] = useState(template?.id || null)
  const [mode, setMode] = useState('edit')

  useEffect(() => {
    listComponents()
      .then((response) => setComponents(response.data || []))
      .catch((error) => setStatus(error.message))
  }, [])

  const selectedBlock = useMemo(
    () => structure.sections.find((section) => section.id === selectedId) || null,
    [structure.sections, selectedId],
  )

  useEffect(() => {
    if (structure.sections.length === 0) {
      setPreviewHtml('')
      return undefined
    }

    const timer = setTimeout(async () => {
      setPreviewLoading(true)
      try {
        const html = await renderPreview({
          template_id: templateId || undefined,
          name,
          structure,
        })
        setPreviewHtml(html)
      } catch (error) {
        setStatus(error.message)
      } finally {
        setPreviewLoading(false)
      }
    }, 400)

    return () => clearTimeout(timer)
  }, [structure, templateId, name])

  const addComponent = (component) => {
    const block = createBlock(component)
    setStructure((current) => ({
      ...current,
      sections: [...current.sections, block],
    }))
    setSelectedId(block.id)
    setMode('edit')
    return block
  }

  const dropComponent = (type) => {
    const component = components.find((item) => item.type === type)
    if (component) {
      addComponent(component)
    }
  }

  const applyImageToBlock = (blockId, url, fileName) => {
    setStructure((current) => ({
      ...current,
      sections: current.sections.map((section) => {
        if (section.id !== blockId) {
          return section
        }
        return {
          ...section,
          props: {
            ...section.props,
            src: url,
            alt: section.props.alt || fileName || 'Image',
          },
        }
      }),
    }))
    setSelectedId(blockId)
    setStatus('Photo added.')
  }

  const dropImageFile = async (file, targetBlockId) => {
    try {
      setStatus('Uploading photo…')
      const response = await uploadImage(file)
      const url = response.data.url

      if (targetBlockId) {
        const target = structure.sections.find((section) => section.id === targetBlockId)
        if (target?.type === 'image') {
          applyImageToBlock(targetBlockId, url, file.name)
          return
        }
      }

      const imageComponent = components.find((item) => item.type === 'image')
      if (!imageComponent) {
        setStatus('Image component is not available.')
        return
      }

      const block = createBlock(imageComponent)
      block.props = {
        ...block.props,
        src: url,
        alt: file.name,
      }

      setStructure((current) => ({
        ...current,
        sections: [...current.sections, block],
      }))
      setSelectedId(block.id)
      setStatus('Photo block created.')
    } catch (error) {
      setStatus(error.message)
    }
  }

  const reorder = (fromId, toId) => {
    setStructure((current) => {
      const fromIndex = current.sections.findIndex((section) => section.id === fromId)
      const toIndex = current.sections.findIndex((section) => section.id === toId)
      return {
        ...current,
        sections: moveItem(current.sections, fromIndex, toIndex),
      }
    })
  }

  const updateBlock = (nextBlock) => {
    setStructure((current) => ({
      ...current,
      sections: current.sections.map((section) =>
        section.id === nextBlock.id ? nextBlock : section,
      ),
    }))
  }

  const resizeGrid = (blockId, grid) => {
    const colStart = Math.max(1, Math.min(12, Number(grid.colStart) || 1))
    const colSpan = Math.max(1, Math.min(12 - colStart + 1, Number(grid.colSpan) || 1))

    setStructure((current) => ({
      ...current,
      sections: current.sections.map((section) =>
        section.id === blockId
          ? {
              ...section,
              grid: { colStart, colSpan },
            }
          : section,
      ),
    }))
  }

  const resizeImageHeight = (blockId, height) => {
    const nextHeight = Math.max(80, Math.min(600, Number(height) || 180))

    setStructure((current) => ({
      ...current,
      sections: current.sections.map((section) =>
        section.id === blockId
          ? {
              ...section,
              props: {
                ...section.props,
                height: nextHeight,
              },
            }
          : section,
      ),
    }))
  }

  const deleteBlock = (id) => {
    setStructure((current) => ({
      ...current,
      sections: current.sections.filter((section) => section.id !== id),
    }))
    setSelectedId(null)
  }

  const save = async () => {
    if (structure.sections.length === 0) {
      setStatus('Add at least one component before saving.')
      return
    }

    setSaving(true)
    setStatus('')
    const payload = {
      name,
      description,
      structure: {
        version: structure.version || 1,
        sections: structure.sections,
      },
      settings: { pageSize: 'A4' },
    }

    try {
      const response = templateId
        ? await updateTemplate(templateId, payload)
        : await createTemplate(payload)

      const saved = response.data
      setTemplateId(saved.id)
      setStatus('Template saved.')
      onSaved?.(saved)
    } catch (error) {
      setStatus(error.message)
    } finally {
      setSaving(false)
    }
  }

  const exportDoc = async (format) => {
    if (!templateId) {
      setStatus('Save the template before exporting.')
      return
    }

    try {
      await updateTemplate(templateId, {
        name,
        description,
        structure,
      })
      await downloadRender(format, {
        template_id: templateId,
        structure,
      })
      setStatus(`${format.toUpperCase()} downloaded.`)
    } catch (error) {
      setStatus(error.message)
    }
  }

  return (
    <div className="builder">
      <header className="builder-toolbar">
        <button type="button" className="ghost" onClick={onBack}>
          ← Library
        </button>
        <div className="meta-fields">
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Template name"
          />
          <input
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Short description"
          />
        </div>
        <div className="actions">
          <button type="button" onClick={save} disabled={saving}>
            {saving ? 'Saving…' : 'Save'}
          </button>
          <button type="button" className="secondary" onClick={() => exportDoc('pdf')}>
            Export PDF
          </button>
          <button type="button" className="secondary" onClick={() => exportDoc('docx')}>
            Export Word
          </button>
        </div>
      </header>

      <div className="mode-tabs" role="tablist">
        <button
          type="button"
          role="tab"
          className={mode === 'edit' ? 'tab active' : 'tab'}
          aria-selected={mode === 'edit'}
          onClick={() => setMode('edit')}
        >
          Edit sections
        </button>
        <button
          type="button"
          role="tab"
          className={mode === 'preview' ? 'tab active' : 'tab'}
          aria-selected={mode === 'preview'}
          onClick={() => setMode('preview')}
        >
          Preview document
        </button>
      </div>

      {status ? (
        <p
          className={`status ${
            status.toLowerCase().includes('fail') || status.toLowerCase().includes('error')
              ? 'error'
              : ''
          }`}
        >
          {status}
        </p>
      ) : null}

      {mode === 'edit' ? (
        <div className="builder-layout">
          <ComponentPalette components={components} onAdd={addComponent} />
          <Canvas
            sections={structure.sections}
            selectedId={selectedId}
            onSelect={setSelectedId}
            onDropComponent={dropComponent}
            onDropImageFile={dropImageFile}
            onReorder={reorder}
            onImageUploaded={applyImageToBlock}
            onError={setStatus}
            onResizeGrid={resizeGrid}
            onImageHeight={resizeImageHeight}
          />
          <PropertyPanel
            block={selectedBlock}
            onChange={updateBlock}
            onDelete={deleteBlock}
            onError={setStatus}
          />
        </div>
      ) : (
        <PreviewPane html={previewHtml} loading={previewLoading} full />
      )}
    </div>
  )
}
