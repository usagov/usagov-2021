// import * as apiCalls from '../../api/apiCalls'

/**
 * a function that collect all the required fields in the current step
 * @function
 */
export const getRequiredFieldsets = (document, setHandler) => {
  setTimeout(() => {
    const collectedNodeList = document.querySelectorAll('fieldset')
    const requiredNodeList = Array.from(collectedNodeList).filter(
      node => node.attributes.required && node.hidden === false
    )
    setHandler(Array.from(requiredNodeList))
  }, 0)
}

/**
 * a function that collect all the non required fields in the current step which still need to be validated if there are requirements
 * @function
 */

// if a date fieldset has values, add it to required fieldsets array even if not attributed as required
export const getNonRequiredFieldsets = (
  criteriaKey,
  requiredFieldsets,
  setHandler,
  setHasError,
  hasError,
  data
) => {
  const node = document.getElementById(`${criteriaKey}`)

  const notRequired = !node.attributes.required

  if (notRequired) {
    const dateDataObj = data.filter(item => item.criteriaKey === criteriaKey)

    const dateValues = dateDataObj[0].values.value

    const isEmpty = obj => {
      for (const key in obj) {
        if (obj[key] !== '') {
          return false // returns false if object has a non-empty string value
        }
      }
      return true // returns true if all values are empty strings
    }

    const addToRequiredFields = [...requiredFieldsets, node]

    const makeUniq = [...new Set(addToRequiredFields)]

    const removeFromRequiredFields = makeUniq.filter(
      item => !item.id === criteriaKey
    )

    const removeFromErrorArray = hasError.filter(
      item => !item.id.includes(criteriaKey)
    )

    isEmpty(dateValues) && setHasError(removeFromErrorArray)

    isEmpty(dateValues)
      ? setHandler(removeFromRequiredFields)
      : setHandler(makeUniq)
  }
}

export const handleCheckForRequiredValues = async (
  requiredFieldsets,
  setHasError
) => {
  // handle most elements
  const invalidElements = requiredFieldsets
    .map(fieldset => {
      return (
        Array.from(fieldset.elements)
          // check all the required inputs, if there is no value, there the input is invalid
          .filter(el => {
            // we need to custom handle our dates verification to ensure a 4 digit year
            if (el.attributes['data-datetype']?.value === 'year') {
              return !el.value || (el.value && el.value.length !== 4)
            }
            // we allow for 02 but not 0 day value
            if (el.attributes['data-datetype']?.value === 'day') {
              return !el.value || (el.value && el.value === '0')
            }

            return !el.value
          })
      )
    })
    .flat()

  // handle radios/checks separately
  const invalidRadioFieldSets = requiredFieldsets
    .map(fieldset => {
      if (
        Array.from(fieldset.elements).every(
          el => !el.attributes.type?.value === 'radio'
        )
      ) {
        return []
      }

      const radios = Array.from(fieldset.elements).filter(
        el => el.attributes.type?.value === 'radio'
      )

      if (radios.length > 0 && radios.every(el => !el.checked)) {
        return fieldset
      }
      return []
    })
    .flat()

  const mergeInvalidElements = [invalidElements, invalidRadioFieldSets].flat()
  setHasError(mergeInvalidElements)
  return mergeInvalidElements.length === 0
}

export const handleInvalid = ({
  // required,
  hasError,
  criteriaKey,
  fieldSetId,
  useFilter = false,
}) => {
  const handleMap = hasError
    .map(errorItem => {
      return (
        (errorItem.id !== undefined &&
          fieldSetId &&
          fieldSetId.includes(errorItem.id)) ||
        (errorItem.id !== undefined && errorItem.id.includes(criteriaKey))
      )
    })
    .includes(true)

  const handleFilter = hasError.filter(errorItem => {
    return errorItem.id !== undefined && errorItem.id.includes(fieldSetId)
  })

  return useFilter === true ? handleFilter : handleMap
}

export default {
  getRequiredFieldsets,
  getNonRequiredFieldsets,
  handleCheckForRequiredValues,
  handleInvalid,
}
