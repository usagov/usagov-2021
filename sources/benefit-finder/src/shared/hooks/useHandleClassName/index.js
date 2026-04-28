import { useEffect, useState } from 'react'
/**
 * a function to construct class strings based on composition.
 * @function
 * @param {string} className - The inherited class
 * @param {array} defaultClasses - The default component class
 * @param {array} utilityClasses - The utility classes in components
 * @return {string} returns a string
 */
const useHandleClassName = ({ className, defaultClasses, utilityClasses }) => {
  /**
   * a state function to manage an array of className strings
   * @function
   * @param {string} classes - string of handled classes
   * @return {string} returns an joined array of strings
   */
  const [classes, setClasses] = useState('')

  useEffect(() => {
    const classList = [
      className,
      defaultClasses && defaultClasses.join(' '),
      utilityClasses && utilityClasses.join(' '),
    ]
    setClasses(
      classList.filter(classGroup => classGroup !== undefined).join(' ')
    )
  }, [className, defaultClasses, utilityClasses])

  return classes
}

export default useHandleClassName
