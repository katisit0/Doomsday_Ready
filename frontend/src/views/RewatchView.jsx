import { useEffect, useState } from 'react'
import { Clock, Flag, Star, Tv } from 'lucide-react'
import { api, errorText, formatMinutes } from '../api.js'

export default function RewatchView() {
  const [movies, setMovies] = useState([])
  const [targetId, setTargetId] = useState('')
  const [essentials, setEssentials] = useState(false)
  const [plan, setPlan] = useState(null)
  const [error, setError] = useState(null)

  // Load movies once; default target = Avengers: Doomsday if it exists
  useEffect(() => {
    api.movies().then((list) => {
      setMovies(list)
      const doomsday = list.find((m) => m.title === 'Avengers: Doomsday')
      if (doomsday) setTargetId(String(doomsday.id))
    }).catch((e) => setError(errorText(e)))
  }, [])

  const loadPlan = () => {
    if (!targetId) {
      setPlan(null)
      return
    }
    let current = true // ignore a late answer for a target that is no longer selected
    api.rewatchOrder(targetId, essentials)
      .then((p) => { if (current) { setPlan(p); setError(null) } })
      .catch((e) => { if (current) setError(errorText(e)) })
    return () => { current = false }
  }

  useEffect(loadPlan, [targetId, essentials])

  async function toggleWatched(movie) {
    try {
      await api.updateMovie(movie.id, { watched: !movie.watched })
      loadPlan() // counts and remaining time come from the server
    } catch (e) {
      setError(errorText(e))
    }
  }

  const hasEssentials = movies.some((m) => m.essential)
  const percent = plan && plan.total_count ? Math.round((plan.watched_count / plan.total_count) * 100) : 0

  return (
    <section>
      <h2>Rewatch Plan</h2>
      {error && <p className="message error">{error}</p>}

      <div className="card controls">
        <label>Target movie
          <select value={targetId} onChange={(e) => setTargetId(e.target.value)}>
            <option value="">Choose a movie…</option>
            {movies.map((m) => <option key={m.id} value={m.id}>{m.title}</option>)}
          </select>
        </label>
        <div className="toggle" role="group" aria-label="View">
          <button className={!essentials ? 'tab active' : 'tab'} onClick={() => setEssentials(false)}>Full</button>
          <button className={essentials ? 'tab active' : 'tab'} onClick={() => setEssentials(true)}
            disabled={!hasEssentials} title={hasEssentials ? '' : 'Load the full MCU data to get essentials'}>
            Essentials only
          </button>
        </div>
      </div>

      {plan && (
        <>
          <div className="card summary">
            <div className="progress-label">
              <strong>{plan.watched_count} of {plan.total_count} watched</strong>
              <span><Clock size={15} /> {formatMinutes(plan.remaining_runtime)} left to watch</span>
            </div>
            <div className="progress" role="progressbar" aria-valuenow={percent} aria-valuemin="0" aria-valuemax="100">
              <div className="progress-fill" style={{ width: `${percent}%` }} />
            </div>
          </div>

          {plan.order.length === 0 ? (
            <p className="muted">
              {essentials
                ? `No essential titles to watch before "${plan.movie.title}". Switch to Full to see everything.`
                : `Nothing to watch first: "${plan.movie.title}" has no prerequisites.`}
            </p>
          ) : (
            <ol className="plan">
              {plan.order.map((movie) => (
                <li key={movie.id} className={movie.watched ? 'done' : ''}>
                  <label>
                    <input type="checkbox" checked={movie.watched} onChange={() => toggleWatched(movie)} />
                    <span className="plan-title">{movie.title}</span>
                    {movie.type === 'series' && <span className="badge"><Tv size={12} /> Show</span>}
                    {movie.essential && !essentials && <span className="badge essential"><Star size={12} /> Essential</span>}
                  </label>
                  <span className="meta">{movie.release_year} · {formatMinutes(movie.runtime)}</span>
                </li>
              ))}
              <li className="target"><Flag size={15} /> Then watch <strong>{plan.movie.title}</strong></li>
            </ol>
          )}
        </>
      )}
    </section>
  )
}
