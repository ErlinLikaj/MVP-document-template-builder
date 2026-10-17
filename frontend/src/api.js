const API_URL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api'
const DEFAULT_TIMEOUT_MS = 10000

async function request(path, options = {}) {
  const { timeoutMs = DEFAULT_TIMEOUT_MS, headers: customHeaders, ...rest } = options
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), timeoutMs)

  try {
    const response = await fetch(`${API_URL}${path}`, {
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(customHeaders || {}),
      },
      signal: controller.signal,
      ...rest,
    })

    if (!response.ok) {
      let message = `Request failed (${response.status})`
      try {
        const body = await response.json()
        message = body.message || JSON.stringify(body.errors || body)
      } catch {
        // keep default message
      }
      throw new Error(message)
    }

    if (response.status === 204) {
      return null
    }

    const contentType = response.headers.get('Content-Type') || ''
    if (contentType.includes('application/json')) {
      return response.json()
    }

    return response
  } catch (error) {
    if (error?.name === 'AbortError') {
      throw new Error('API did not respond. Is Laravel running on http://127.0.0.1:8000 ?')
    }
    throw error
  } finally {
    clearTimeout(timer)
  }
}

export function listComponents() {
  return request('/components')
}

export function listTemplates() {
  return request('/templates')
}

export function getTemplate(id) {
  return request(`/templates/${id}`)
}

export function createTemplate(payload) {
  return request('/templates', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export function updateTemplate(id, payload) {
  return request(`/templates/${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  })
}

export function deleteTemplate(id) {
  return request(`/templates/${id}`, {
    method: 'DELETE',
  })
}

export async function renderPreview(payload) {
  const response = await request('/render/preview', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
  return response.text()
}

export async function uploadImage(file) {
  const formData = new FormData()
  formData.append('image', file)

  const response = await fetch(`${API_URL}/uploads`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
    },
    body: formData,
  })

  if (!response.ok) {
    let message = 'Image upload failed'
    try {
      const body = await response.json()
      message = body.message || JSON.stringify(body.errors || body)
    } catch {
      // keep default
    }
    throw new Error(message)
  }

  return response.json()
}

export async function downloadRender(format, payload) {
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), 60000)

  try {
    const response = await fetch(`${API_URL}/render/${format}`, {
      method: 'POST',
      headers: {
        Accept: '*/*',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
      signal: controller.signal,
    })

    if (!response.ok) {
      throw new Error(`Failed to generate ${format.toUpperCase()}`)
    }

    const blob = await response.blob()
    const disposition = response.headers.get('Content-Disposition') || ''
    const match = disposition.match(/filename="?([^"]+)"?/i)
    const filename = match?.[1] || `document.${format}`

    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch (error) {
    if (error?.name === 'AbortError') {
      throw new Error(`Generating ${format.toUpperCase()} took too long. Try again.`)
    }
    throw error
  } finally {
    clearTimeout(timer)
  }
}
