import { useRef, useEffect } from 'react'

const useResetElement = () => {
  /**
   * a hook to set and get the reset element to focus
   * @function
   * @return {React.ref} returns ref element
   */

  // example use
  // assign hook to const

  // const resetElement = useResetElement()

  // focus element as needed in an effect or synthetic event

  // useEffect(() => {
  //   resetElement.current?.focus()
  // }, [resetElement])

  // create a sr-only element to set focus on that is not in the default tab order
  useEffect(() => {
    if (document.getElementById('index-reset') === null) {
      document.body.insertAdjacentHTML(
        'afterBegin',
        '<span tabIndex="-1" id="index-reset" data-testid="index-reset" class="usa-sr-only"/>'
      )
    }
  }, [])

  const resetElement = useRef(document.getElementById('index-reset'))
  return resetElement
}

export default useResetElement
