import { useEffect, useState } from 'react'
import { CircleCheck, CircleX, Plus, Ticket } from 'lucide-react'
import { api, errorText } from '../api.js'

export default function BookingView() {
  const [cinemas, setCinemas] = useState([])
  const [cinemaId, setCinemaId] = useState('')
  const [groupSize, setGroupSize] = useState('')
  const [quote, setQuote] = useState(null)
  const [error, setError] = useState(null)
  const [newCinema, setNewCinema] = useState({ name: '', sizes: '' })
  const [cinemaMessage, setCinemaMessage] = useState(null)

  const reload = () => api.cinemas().then(setCinemas).catch((e) => setError(errorText(e)))
  useEffect(() => { reload() }, [])

  async function getQuote(e) {
    e.preventDefault()
    setQuote(null)
    try {
      setQuote(await api.booking(cinemaId, groupSize))
      setError(null)
    } catch (err) {
      setError(errorText(err))
    }
  }

  async function addCinema(e) {
    e.preventDefault()
    const sizes = newCinema.sizes.split(/[\s,]+/).filter(Boolean).map(Number)
    try {
      const cinema = await api.createCinema({ name: newCinema.name, bundle_sizes: sizes })
      setNewCinema({ name: '', sizes: '' })
      setCinemaMessage({ kind: 'ok', text: `Added "${cinema.name}".` })
      reload()
    } catch (err) {
      setCinemaMessage({ kind: 'error', text: errorText(err) })
    }
  }

  const selected = cinemas.find((c) => String(c.id) === cinemaId)

  return (
    <section>
      <h2>Booking</h2>

      <form className="card controls" onSubmit={getQuote}>
        <label>Cinema
          <select required value={cinemaId} onChange={(e) => { setCinemaId(e.target.value); setQuote(null); setError(null) }}>
            <option value="">Choose a cinema…</option>
            {cinemas.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name} (bundles of {c.bundles.map((b) => b.size).join(', ')})
              </option>
            ))}
          </select>
        </label>
        <label>Group size
          <input required type="number" min="1" max="10000" value={groupSize}
            onChange={(e) => { setGroupSize(e.target.value); setQuote(null); setError(null) }} />
        </label>
        <button type="submit"><Ticket size={16} /> Get quote</button>
      </form>

      {error && <p className="message error">{error}</p>}

      {quote && (
        <div className={`card quote ${quote.possible ? '' : 'impossible'}`}>
          {quote.possible ? (
            <>
              <p className="quote-total"><CircleCheck size={20} color="var(--accent-text)" /> {quote.total_bundles} {quote.total_bundles === 1 ? 'bundle' : 'bundles'} for {quote.group_size} seats</p>
              <ul>
                {quote.bundles.map((b) => (
                  <li key={b.size}><strong>{b.count} ×</strong> bundle of {b.size} seats</li>
                ))}
              </ul>
              <p className="muted small">
                {quote.bundles.map((b) => `${b.size} × ${b.count}`).join(', ')}
              </p>
            </>
          ) : (
            <p className="quote-total"><CircleX size={20} /> Not possible: no combination of {selected?.name}'s bundles adds up to exactly {quote.group_size} seats.</p>
          )}
        </div>
      )}

      <form className="card form" onSubmit={addCinema}>
        <h3>Add a cinema</h3>
        {cinemaMessage && <p className={`message ${cinemaMessage.kind}`}>{cinemaMessage.text}</p>}
        <div className="form-row">
          <label>Name
            <input required value={newCinema.name} onChange={(e) => setNewCinema({ ...newCinema, name: e.target.value })} />
          </label>
          <label>Bundle sizes (1–10 sizes, 1–500 seats each)
            <input required placeholder="e.g. 2, 5" value={newCinema.sizes}
              onChange={(e) => setNewCinema({ ...newCinema, sizes: e.target.value })} />
          </label>
        </div>
        <button type="submit"><Plus size={16} /> Add cinema</button>
      </form>
    </section>
  )
}
