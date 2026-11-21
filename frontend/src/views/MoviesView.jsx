import { useEffect, useState } from 'react'
import { Download, Pencil, Plus, Save, Star, Tv, X } from 'lucide-react'
import { api, errorText, formatMinutes } from '../api.js'
import PrerequisitePicker from './PrerequisitePicker.jsx'

const EMPTY_FORM = { title: '', release_year: '', runtime: '', type: 'film', essential: false, prerequisite_ids: [] }

export default function MoviesView() {
  const [movies, setMovies] = useState([])
  const [loading, setLoading] = useState(true)
  const [message, setMessage] = useState(null) // { kind: 'error' | 'ok', text }
  const [form, setForm] = useState(EMPTY_FORM)
  const [editingId, setEditingId] = useState(null)
  const [editSelection, setEditSelection] = useState([])
  const [loadingMcu, setLoadingMcu] = useState(false)

  const reload = () =>
    api.movies()
      .then(setMovies)
      .catch((e) => setMessage({ kind: 'error', text: errorText(e) }))
      .finally(() => setLoading(false))

  useEffect(() => { reload() }, [])

  const titleOf = Object.fromEntries(movies.map((m) => [m.id, m.title]))

  async function addMovie(e) {
    e.preventDefault()
    try {
      const movie = await api.createMovie({
        ...form,
        release_year: Number(form.release_year),
        runtime: Number(form.runtime),
      })
      setForm(EMPTY_FORM)
      setMessage({ kind: 'ok', text: `Added "${movie.title}".` })
      reload()
    } catch (err) {
      setMessage({ kind: 'error', text: errorText(err) })
    }
  }

  async function toggleWatched(movie) {
    // Update the tick straight away, then confirm with the server
    setMovies((list) => list.map((m) => (m.id === movie.id ? { ...m, watched: !m.watched } : m)))
    try {
      await api.updateMovie(movie.id, { watched: !movie.watched })
    } catch (err) {
      setMessage({ kind: 'error', text: errorText(err) })
      reload()
    }
  }

  function startEditing(movie) {
    setEditingId(movie.id)
    setEditSelection(movie.prerequisites.map((p) => p.id))
    setMessage(null)
  }

  async function savePrerequisites() {
    try {
      await api.updateMovie(editingId, { prerequisite_ids: editSelection })
      setMessage({ kind: 'ok', text: `Prerequisites of "${titleOf[editingId]}" saved.` })
      setEditingId(null)
      reload()
    } catch (err) {
      // 409 CIRCULAR_DEPENDENCY lands here with the server's message
      setMessage({ kind: 'error', text: err.code === 'CIRCULAR_DEPENDENCY'
        ? `Not saved: ${err.message} (409 CIRCULAR_DEPENDENCY)`
        : errorText(err) })
    }
  }

  async function loadMcu() {
    if (!window.confirm('Load the full MCU data set (83 films and show seasons)? Your watched ticks are kept.')) return
    setLoadingMcu(true)
    try {
      const result = await api.loadMcu()
      setMessage({ kind: 'ok', text: `Full MCU data loaded: ${result.movies_count} titles.` })
      reload()
    } catch (err) {
      setMessage({ kind: 'error', text: errorText(err) })
    } finally {
      setLoadingMcu(false)
    }
  }

  const watchedCount = movies.filter((m) => m.watched).length

  return (
    <section>
      <div className="section-head">
        <h2>Movies <span className="muted">({watchedCount} of {movies.length} watched)</span></h2>
        <button className="secondary" onClick={loadMcu} disabled={loadingMcu}>
          <Download size={16} /> {loadingMcu ? 'Loading…' : 'Load full MCU data'}
        </button>
      </div>

      {message && <p className={`message ${message.kind}`}>{message.text}</p>}

      <form className="card form" onSubmit={addMovie}>
        <h3>Add a movie</h3>
        <div className="form-row">
          <label>Title
            <input required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
          </label>
          <label>Release year
            <input required type="number" min="1900" max="2100" value={form.release_year}
              onChange={(e) => setForm({ ...form, release_year: e.target.value })} />
          </label>
          <label>Runtime (min)
            <input required type="number" min="1" max={form.type === 'series' ? 6000 : 300} value={form.runtime}
              onChange={(e) => setForm({ ...form, runtime: e.target.value })} />
          </label>
          <label>Type
            <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
              <option value="film">Film</option>
              <option value="series">Show season</option>
            </select>
          </label>
          <label className="inline">
            <input type="checkbox" checked={form.essential}
              onChange={(e) => setForm({ ...form, essential: e.target.checked })} />
            Essential
          </label>
        </div>
        <details>
          <summary>Prerequisites ({form.prerequisite_ids.length})</summary>
          <PrerequisitePicker movies={movies} selected={form.prerequisite_ids}
            onChange={(ids) => setForm({ ...form, prerequisite_ids: ids })} />
        </details>
        <button type="submit"><Plus size={16} /> Add movie</button>
      </form>

      {loading ? <p className="muted">Loading…</p> : movies.length === 0 ? (
        <p className="muted">No movies yet. Add one above or load the full MCU data.</p>
      ) : (
        <table className="table">
          <thead>
            <tr><th>Watched</th><th>Title</th><th>Year</th><th>Runtime</th><th>Watch these first</th><th></th></tr>
          </thead>
          <tbody>
            {movies.map((movie) => (
              <tr key={movie.id} className={movie.watched ? 'done' : ''}>
                <td><input type="checkbox" checked={movie.watched} onChange={() => toggleWatched(movie)}
                  aria-label={`Watched ${movie.title}`} /></td>
                <td>
                  {movie.title}
                  {movie.type === 'series' && <span className="badge"><Tv size={12} /> Show</span>}
                  {movie.essential && <span className="badge essential"><Star size={12} /> Essential</span>}
                </td>
                <td>{movie.release_year}</td>
                <td>{formatMinutes(movie.runtime)}</td>
                <td>
                  {editingId === movie.id ? (
                    <div>
                      <PrerequisitePicker movies={movies} selected={editSelection}
                        onChange={setEditSelection} excludeId={movie.id} />
                      <button onClick={savePrerequisites}><Save size={15} /> Save</button>{' '}
                      <button className="secondary" onClick={() => setEditingId(null)}><X size={15} /> Cancel</button>
                    </div>
                  ) : movie.prerequisites.length ? (
                    movie.prerequisites.map((p) => p.title).join(', ')
                  ) : <span className="muted">None</span>}
                </td>
                <td>
                  {editingId !== movie.id && (
                    <button className="link" onClick={() => startEditing(movie)}><Pencil size={14} /> Edit</button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </section>
  )
}
