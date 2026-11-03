// Places a popover next to its trigger using fixed coordinates, so it is never clipped by a
// scrolling or overflow-hidden ancestor (modals, tables). Flips above the trigger when needed.
export function place(anchor, { height = 280, width = null, gap = 4 } = {}) {
  const r = anchor.getBoundingClientRect()
  const below = window.innerHeight - r.bottom
  const up = below < Math.min(height, 220) && r.top > below
  const w = width ?? r.width
  const left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8))
  return {
    position: 'fixed',
    left: `${left}px`,
    minWidth: `${r.width}px`,
    ...(up ? { bottom: `${window.innerHeight - r.top + gap}px` } : { top: `${r.bottom + gap}px` }),
    maxHeight: `${Math.max(160, (up ? r.top : below) - 16)}px`,
  }
}
