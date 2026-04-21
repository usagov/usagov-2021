import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'

// a version of usagov uswds will already be on production
if (process.env.NODE_ENV !== 'production') {
  import('../themes/custom/usagov/scripts/uswds.min.js') // usagov uswds
  import('../themes/custom/usagov/css/styles.css') // usagov uswds
}

const root = ReactDOM.createRoot(document.getElementById('benefit-finder'))
root.render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
)
