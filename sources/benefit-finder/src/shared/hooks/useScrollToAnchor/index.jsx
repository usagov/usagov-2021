import { useEffect, useRef } from 'react'

function useScrollToAnchor({ location, offset }) {
  const lastHash = useRef('')

  // listen to location change using useEffect with location as dependency
  useEffect(() => {
    if (location?.hash) {
      lastHash.current = location.hash.slice(1) // safe hash for further use after navigation
    }

    let timeoutId
    if (lastHash.current && document.getElementById(lastHash.current)) {
      timeoutId = setTimeout(() => {
        document.getElementById(lastHash.current)?.scrollIntoView({
          block: 'start',
          inline: 'nearest',
          top: offset || 0,
        })
        lastHash.current = ''
      }, 100)
    }
    return () => {
      clearTimeout(timeoutId)
    }
  }, [location])

  return null
}

export default useScrollToAnchor
