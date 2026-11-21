import { useState } from 'react'
import { Film, ListChecks, Network, Ticket } from 'lucide-react'
import MoviesView from './views/MoviesView.jsx'
import RewatchView from './views/RewatchView.jsx'
import BookingView from './views/BookingView.jsx'
import GraphView from './views/GraphView.jsx'

const VIEWS = [
  { id: 'movies', label: 'Movies', Icon: Film, Component: MoviesView },
  { id: 'graph', label: 'Graph', Icon: Network, Component: GraphView },
  { id: 'rewatch', label: 'Rewatch Plan', Icon: ListChecks, Component: RewatchView },
  { id: 'booking', label: 'Booking', Icon: Ticket, Component: BookingView },
]

export default function App() {
  const [view, setView] = useState('movies')
  const { Component } = VIEWS.find((v) => v.id === view)

  return (
    <>
      <header className="topbar">
        <div className="topbar-inner">
          <div className="brand">
            <span className="brand-mark"><Film size={18} strokeWidth={2.4} /></span>
            <div>
              <h1>Doomsday Ready</h1>
              <p>Rewatch planner and watch-party booking</p>
            </div>
          </div>
          <nav className="tabs">
            {VIEWS.map((v) => {
              const Icon = v.Icon
              return (
                <button key={v.id} className={v.id === view ? 'tab active' : 'tab'} onClick={() => setView(v.id)}>
                  <Icon size={16} /> {v.label}
                </button>
              )
            })}
          </nav>
        </div>
      </header>

      <main className="app">
        <Component />
      </main>

      <footer className="footer">
        <div className="footer-inner">Fan project, not affiliated with Marvel or Disney.</div>
      </footer>
    </>
  )
}
