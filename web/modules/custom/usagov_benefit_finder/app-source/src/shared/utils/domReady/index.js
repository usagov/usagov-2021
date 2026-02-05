/**
 * domReady
 * =================
 * Returns a DOM-ready indicator for testing env.
 *
 * @param {boolean} loading - loading state
 * @param {string} parentElementID - where we append our element to
 * @returns {JSX.Element|null} A DOM-ready indicator element
 * @description used to indicate when the DOM is ready for rendering.
 * It returns a `div` element with a `data-testid` attribute set to * "dom-ready" when the component is not in a loading state and the * environment is not production.
 * @example
 * const domReadyIndicator = useDomReady({ loading: false })
 */

const domReady = ({ loading, parentElementID }) => {
  const parentElement = document.querySelector(
    `[data-testid="${parentElementID}"]`
  )
  const domReadyIndicator = document.createElement('div')
  domReadyIndicator.dataset.testid = 'dom-ready'

  if (process.env.NODE_ENV !== 'production' && loading === false) {
    parentElement.appendChild(domReadyIndicator)
  }
}

export default domReady
