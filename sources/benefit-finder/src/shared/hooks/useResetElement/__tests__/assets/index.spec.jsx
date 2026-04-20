import { useResetElement } from '../../../index'

const IndexHTML = () => {
  // create our reset element
  useResetElement()
  // assign our reset element to a variable
  const resetElement = useResetElement()

  const handleClick = () => {
    // set focus when click handler is called
    resetElement.current.focus()
  }
  return (
    <div data-testid="index-html">
      <a
        href="#skip-to-h1"
        className="usa-skipnav usa-sr-only focusable"
        data-testid="skip-link"
      >
        Skip to main content
      </a>
      <button data-testid="button" />
      <input data-testid="input" />
      <select data-testid="select" />
      <textarea data-testid="textarea" />
      <button
        onClick={() => handleClick()}
        tabIndex={-1}
        data-testid="negative-tab-index"
      />
    </div>
  )
}

export default IndexHTML
