// Small fetch wrapper for the Laravel API. Errors come back as { error: { code, message, fields? } }.

export class ApiError extends Error {
  constructor(status, error) {
    super(error?.message || `Request failed (${status})`)
    this.status = status
    this.code = error?.code
    this.fields = error?.fields || {}
  }
}

async function request(method, path, body) {
  const response = await fetch(`/api${path}`, {
    method,
    headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
    body: body ? JSON.stringify(body) : undefined,
  })
  const data = await response.json().catch(() => null)
  if (!response.ok) throw new ApiError(response.status, data?.error)
  return data
}

export const api = {
  movies: () => request('GET', '/movies'),
  createMovie: (movie) => request('POST', '/movies', movie),
  updateMovie: (id, changes) => request('PATCH', `/movies/${id}`, changes),
  rewatchOrder: (id, essentials) => request('GET', `/movies/${id}/rewatch-order${essentials ? '?essentials=1' : ''}`),
  loadMcu: () => request('POST', '/movies/load-mcu'),
  cinemas: () => request('GET', '/cinemas'),
  createCinema: (cinema) => request('POST', '/cinemas', cinema),
  booking: (id, groupSize) => request('GET', `/cinemas/${id}/booking?groupSize=${encodeURIComponent(groupSize)}`),
}

/** First field error, or the general message. */
export function errorText(error) {
  const fieldMessages = Object.values(error?.fields || {}).flat()
  return fieldMessages[0] || error?.message || 'Something went wrong.'
}

/** 150 -> "2 h 30 min" */
export function formatMinutes(minutes) {
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  if (!h) return `${m} min`
  return m ? `${h} h ${m} min` : `${h} h`
}
