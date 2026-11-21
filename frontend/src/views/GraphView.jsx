import { useEffect, useMemo, useState } from 'react'
import { ZoomIn, ZoomOut } from 'lucide-react'
import { api, errorText } from '../api.js'

const NODE_W = 220
const NODE_H = 52
const COL_GAP = 96
const ROW_GAP = 20
const PAD = 28
const HEADER = 38 // room for the "Step n" labels
const ZOOMS = [0.5, 0.65, 0.8, 1, 1.2, 1.5]

/**
 * Layered layout: a movie's column = length of its longest prerequisite chain, so every arrow points right.
 * Inside a column, movies are sorted by the average row of their prerequisites (fewer crossings), then by year.
 */
function layout(movies) {
  const byId = Object.fromEntries(movies.map((m) => [m.id, m]))
  const depth = {}
  const depthOf = (id) => {
    if (depth[id] !== undefined) return depth[id]
    const prereqs = byId[id].prerequisites.filter((p) => byId[p.id])
    depth[id] = prereqs.length ? 1 + Math.max(...prereqs.map((p) => depthOf(p.id))) : 0
    return depth[id]
  }
  movies.forEach((m) => depthOf(m.id))

  const columns = []
  movies.forEach((m) => (columns[depth[m.id]] ||= []).push(m))

  const row = {}
  columns.forEach((column, c) => {
    const key = (m) => {
      const rows = m.prerequisites.map((p) => row[p.id]).filter((r) => r !== undefined)
      return rows.length ? rows.reduce((a, b) => a + b, 0) / rows.length : -1
    }
    column.sort((a, b) => (c === 0 ? 0 : key(a) - key(b)) || a.release_year - b.release_year || a.title.localeCompare(b.title))
    column.forEach((m, r) => { row[m.id] = r })
  })

  const tallest = Math.max(0, ...columns.map((col) => col.length))
  const position = {}
  columns.forEach((column, c) => {
    const offset = ((tallest - column.length) * (NODE_H + ROW_GAP)) / 2 // centre shorter columns
    column.forEach((m, r) => {
      position[m.id] = { x: PAD + c * (NODE_W + COL_GAP), y: PAD + HEADER + offset + r * (NODE_H + ROW_GAP) }
    })
  })

  return {
    position,
    columns: columns.length,
    width: PAD * 2 + columns.length * NODE_W + Math.max(0, columns.length - 1) * COL_GAP,
    height: PAD * 2 + HEADER + tallest * NODE_H + Math.max(0, tallest - 1) * ROW_GAP,
  }
}

/** All ids reachable from `start` following `next` (prerequisites or dependents). */
function reach(start, next) {
  const seen = new Set()
  const stack = [...(next[start] || [])]
  while (stack.length) {
    const id = stack.pop()
    if (seen.has(id)) continue
    seen.add(id)
    stack.push(...(next[id] || []))
  }
  return seen
}

const shorten = (text, max = 28) => (text.length > max ? `${text.slice(0, max - 1)}…` : text)

export default function GraphView() {
  const [movies, setMovies] = useState([])
  const [error, setError] = useState(null)
  const [selectedId, setSelectedId] = useState(null)
  const [zoom, setZoom] = useState(1)

  useEffect(() => { api.movies().then(setMovies).catch((e) => setError(errorText(e))) }, [])

  const graph = useMemo(() => layout(movies), [movies])
  const edges = useMemo(
    () => movies.flatMap((m) => m.prerequisites.map((p) => ({ from: p.id, to: m.id }))),
    [movies],
  )

  // Highlight: what the selected movie needs (ancestors) and what needs it (descendants)
  const highlight = useMemo(() => {
    if (!selectedId) return null
    const prereqsOf = Object.fromEntries(movies.map((m) => [m.id, m.prerequisites.map((p) => p.id)]))
    const dependentsOf = {}
    edges.forEach(({ from, to }) => (dependentsOf[from] ||= []).push(to))
    return { ancestors: reach(selectedId, prereqsOf), descendants: reach(selectedId, dependentsOf) }
  }, [selectedId, movies, edges])

  const nodeState = (id) => {
    if (!highlight) return ''
    if (id === selectedId) return 'selected'
    if (highlight.ancestors.has(id)) return 'ancestor'
    if (highlight.descendants.has(id)) return 'descendant'
    return 'dim'
  }
  const edgeState = ({ from, to }) => {
    if (!highlight) return ''
    const up = (id) => id === selectedId || highlight.ancestors.has(id)
    const down = (id) => id === selectedId || highlight.descendants.has(id)
    if (up(from) && up(to)) return 'ancestor'
    if (down(from) && down(to)) return 'descendant'
    return 'dim'
  }
  // Highlighted edges are drawn last so they sit on top
  const order = { dim: 0, '': 1, descendant: 2, ancestor: 3 }
  const sortedEdges = [...edges].sort((a, b) => order[edgeState(a)] - order[edgeState(b)])

  const selected = movies.find((m) => m.id === selectedId)
  const zoomIndex = ZOOMS.indexOf(zoom)

  return (
    <section>
      <div className="section-head">
        <h2>Graph <span className="muted">{movies.length} titles · {edges.length} links</span></h2>
        <div className="zoom">
          <button className="secondary icon" onClick={() => setZoom(ZOOMS[zoomIndex - 1])} disabled={zoomIndex <= 0} aria-label="Zoom out"><ZoomOut size={16} /></button>
          <span>{Math.round(zoom * 100)}%</span>
          <button className="secondary icon" onClick={() => setZoom(ZOOMS[zoomIndex + 1])} disabled={zoomIndex >= ZOOMS.length - 1} aria-label="Zoom in"><ZoomIn size={16} /></button>
        </div>
      </div>
      {error && <p className="message error">{error}</p>}

      <div className="card graph-info">
        {selected ? (
          <p>
            <strong>{selected.title}</strong> needs <span className="key ancestor">{highlight.ancestors.size}</span>{' '}
            {highlight.ancestors.size === 1 ? 'title' : 'titles'} first and is needed by{' '}
            <span className="key descendant">{highlight.descendants.size}</span>.{' '}
            <button className="link" onClick={() => setSelectedId(null)}>Clear</button>
          </p>
        ) : (
          <p className="muted">
            Arrows run from a prerequisite to the title that needs it, left to right. Click a title to light up
            its whole chain. Scroll or zoom to explore.
          </p>
        )}
        <div className="legend">
          <span><i className="l-essential" />Essential</span>
          <span><i className="l-watched" />Watched</span>
          <span><i className="l-ancestor" />Watch first</span>
          <span><i className="l-descendant" />Comes after</span>
        </div>
      </div>

      {movies.length === 0 ? <p className="muted">No movies yet.</p> : (
        <div className="graph-scroll">
          <svg width={graph.width * zoom} height={graph.height * zoom} className="graph"
            onClick={(e) => { if (e.target === e.currentTarget) setSelectedId(null) }}>
            <defs>
              {['default', 'ancestor', 'descendant', 'dim'].map((kind) => (
                <marker key={kind} id={`arrow-${kind}`} viewBox="0 0 10 10" refX="9" refY="5"
                  markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                  <path d="M 0 0 L 10 5 L 0 10 z" className={`arrow ${kind}`} />
                </marker>
              ))}
            </defs>

            <g transform={`scale(${zoom})`}>
              {Array.from({ length: graph.columns }, (_, c) => {
                const x = PAD + c * (NODE_W + COL_GAP)
                return (
                  <text key={c} className="column-label" x={x + NODE_W / 2} y={PAD + 12} textAnchor="middle">
                    STEP {c + 1}
                  </text>
                )
              })}

              {sortedEdges.map((edge) => {
                const a = graph.position[edge.from]
                const b = graph.position[edge.to]
                if (!a || !b) return null
                const x1 = a.x + NODE_W
                const y1 = a.y + NODE_H / 2
                const x2 = b.x - 2
                const y2 = b.y + NODE_H / 2
                const bend = Math.max(48, (x2 - x1) * 0.45)
                const state = edgeState(edge)
                return (
                  <path key={`${edge.from}-${edge.to}`} className={`edge ${state}`}
                    d={`M ${x1} ${y1} C ${x1 + bend} ${y1}, ${x2 - bend} ${y2}, ${x2} ${y2}`}
                    markerEnd={`url(#arrow-${state || 'default'})`} />
                )
              })}

              {movies.map((m) => {
                const p = graph.position[m.id]
                if (!p) return null
                const meta = [m.release_year, m.type === 'series' ? 'Show' : 'Film', m.essential ? 'Essential' : null]
                  .filter(Boolean).join(' · ')
                return (
                  <g key={m.id} transform={`translate(${p.x}, ${p.y})`}
                    className={`node ${nodeState(m.id)} ${m.watched ? 'watched' : ''} ${m.essential ? 'essential' : ''}`}
                    onClick={() => setSelectedId(m.id === selectedId ? null : m.id)}>
                    <title>{`${m.title} (${m.release_year})${m.prerequisites.length ? `\nWatch first: ${m.prerequisites.map((x) => x.title).join(', ')}` : ''}`}</title>
                    <rect className="node-box" width={NODE_W} height={NODE_H} rx="11" />
                    <rect className="node-bar" x="0" y="8" width="4" height={NODE_H - 16} rx="2" />
                    <text x="14" y="22" className="node-title">{shorten(m.title, m.watched ? 25 : 28)}</text>
                    <text x="14" y="40" className="node-meta">{meta}</text>
                    {m.watched && (
                      <g transform={`translate(${NODE_W - 22}, ${NODE_H / 2})`}>
                        <circle className="node-check" r="8" />
                        <path className="node-check-mark" d="M -3.5 0 L -1 2.6 L 3.8 -2.6" />
                      </g>
                    )}
                  </g>
                )
              })}
            </g>
          </svg>
        </div>
      )}
    </section>
  )
}
