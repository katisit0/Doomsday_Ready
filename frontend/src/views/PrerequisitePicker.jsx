import { useState } from 'react'

/** Searchable checkbox list of movies to pick prerequisites from. */
export default function PrerequisitePicker({ movies, selected, onChange, excludeId }) {
  const [filter, setFilter] = useState('')
  const options = movies
    .filter((m) => m.id !== excludeId)
    .filter((m) => m.title.toLowerCase().includes(filter.trim().toLowerCase()))

  const toggle = (id) =>
    onChange(selected.includes(id) ? selected.filter((x) => x !== id) : [...selected, id])

  return (
    <div className="picker">
      <input
        type="search"
        placeholder={`Search ${movies.length} titles…`}
        value={filter}
        onChange={(e) => setFilter(e.target.value)}
      />
      <div className="picker-list">
        {options.length === 0 && <p className="muted">No matching titles.</p>}
        {options.map((m) => (
          <label key={m.id} className="picker-item">
            <input type="checkbox" checked={selected.includes(m.id)} onChange={() => toggle(m.id)} />
            {m.title} <span className="muted">({m.release_year})</span>
          </label>
        ))}
      </div>
      <p className="muted small">{selected.length} selected</p>
    </div>
  )
}
