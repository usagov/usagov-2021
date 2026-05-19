// pull in usagov uswds dependencies
import React from 'react'
import '../themes/custom/usagov/scripts/uswds.min.js'
import '../themes/custom/usagov/css/styles.css'
import '../build/assets/benefit-finder.min.css'

export default {
  decorators: [
    Story => (
      <main>
        <div id="benefit-finder">
          <Story />
        </div>
      </main>
    ),
  ],
  parameters: {
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/,
      },
    },
    viewport: {
      viewports: {
        mobile: {
          name: 'mobile',
          styles: {
            width: '360px',
            height: '963px',
          },
        },
        tablet: {
          name: 'tablet',
          styles: {
            width: '1039px',
            height: '963px',
          },
        },
      },
    },
  },
}
