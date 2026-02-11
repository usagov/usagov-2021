import { cleanString } from '@utils'

/**
 *
 * UTILS FUNCTIONS
 *
 */

/**
 * a function that breaks down a conditional string and compares to a date epoch
 * @function
 * @param {string} conditional - one of >,>=,<,<=,= and a time value
 * @param {object} selectedValue - date object
 * @return {boolean} true or false based on the conditional
 */
export const DateEligibility = ({ selectedValue, conditional }) => {
  // date values
  // "<01-01-1978"
  // "<2years (the deceased died within the last two years)"
  // "<18years"
  // ">=62years"
  // ">=60years"
  // ">=50years"
  // ">18years"

  // get the conditional
  const text = conditional
  const operators = /['>', '>=', '<', '<=', '=']/g
  const operator = text.match(operators)
  const integer = text.match(/\d+/)[0]
  const regExOperator = new RegExp(`[${operator}]`, 'g')

  // !!we have to replace the dashes ("-") in the date because Safari can only parse ("/") in new Date evaluation!!

  // after we fix safari we remove the operator values to give us only the date
  const trimmedText = text.replace(/-/g, '/').replace(regExOperator, '')

  // calculate back
  // get current date
  // subtract integer
  // if a date comes back in date format

  const pattern = /-/
  const conditionalDate = pattern.test(text)
    ? new window.Date(trimmedText)
    : new window.Date(
        new Date().getFullYear() - integer,
        new Date().getMonth(),
        new Date().getDate()
      )

  // example selected value for date
  // const value = {
  //   year: 2022,
  //   month: 2,(month index)
  //   day: 2,
  // }

  // calculate selected
  const selectedDate = new window.Date(
    Date.UTC(
      selectedValue.year,
      selectedValue.month,
      selectedValue.day,
      0,
      0,
      0
    )
  )

  const isDateEligible = (operator, conditionalDate, selectedDate) => {
    // ['>', '>=', '<', '<=', '=']
    // epoch time measures the number of seconds that have elapsed since the start of the Unix epoch on January 1st, 1970, at midnight UTC/GMT, minus the leap seconds.

    const x = selectedDate
    const y = new window.Date(
      Date.UTC(
        conditionalDate.getUTCFullYear(),
        conditionalDate.getUTCMonth(),
        conditionalDate.getUTCDate(),
        0,
        0,
        0
      )
    )

    if (pattern.test(text) === false) {
      const diff = y.getTime() - x.getTime()

      switch (operator.length && operator.join('')) {
        case '>':
          return diff > 0
        case '>=':
          return diff >= 0
        case '<':
          return diff < 0
        case '<=':
          return diff <= 0
        case '=':
          return +diff === 0
        default:
          return false
      }
    } else {
      switch (operator.length && operator.join('')) {
        case '>':
          return x.getTime() > y.getTime()
        case '>=':
          return x.getTime() >= y.getTime()
        case '<':
          return x.getTime() < y.getTime()
        case '<=':
          return x.getTime() <= y.getTime()
        case '=':
          return +x.getTime() === +y.getTime()
        default:
          return false
      }
    }
  }
  return isDateEligible(operator, conditionalDate, selectedDate)
}

/**
 * a function that searches an array for element or child element match to a key
 * @function
 * @param {array} arr - array of fieldsets
 * @param {string} criteriaKey - unique identifier
 * @return {object} returns the element if found
 */
export async function FindCriteria(arr, criteriaKey) {
  let element

  element = arr.find(element => element.fieldset.criteriaKey === criteriaKey)

  // if the element returns as undefined, then go check for children
  if (element === undefined) {
    arr.forEach(item => {
      item.fieldset.children.forEach(childElement => {
        if (childElement.fieldsets[0].fieldset.criteriaKey === criteriaKey) {
          element = childElement.fieldsets[0]
        }
      })
    })
  }
  return element
}

/**
 * a function that takes the search string and parses them into an array
 * @function
 * @param {string} url - array of fieldsets
 * @return {array} returns array of objects
 */
export function GetQueryParams(url) {
  const paramArray = url.slice(url.indexOf('?') + 1).split('&')
  const selectedArray = paramArray.map(elem => {
    const [key, val] = elem.split('=')

    return {
      criteriaKey: key,
      value: decodeURIComponent(val),
    }
  })
  return selectedArray
}

/**
 *
 * GET FUNCTIONS
 *
 */

/**
 * a function that determines our language state based on  the prefix of our URL
 * based on a string match in the pathname of the window object
 * @function
 */
export const Language = () => {
  const string = /^\/es/
  return string.test(window.location.pathname) ? 'es' : 'en'
}

/**
 * An async fetch to get dynamic route data.
 *
 * @function Routes
 * @param {object} w - The window object.
 * @param {string} language - The language code (e.g. "en" or "es").
 * @param {array} stepDataArray - An array of objects containing form step data.
 * @returns {object} An object containing dynamic route data.
 */
export const Routes = (w, language, stepDataArray) => {
  /**
   * Extract the path components from the current URL.
   */
  const locationArray = w.location.pathname.split('/')

  /**
   * trim empty item from split
   */
  locationArray.slice(1)

  /**
   * Get the last part of the path for the LifeEvent.
   */
  const indexPath = locationArray.pop()

  /**
   * Join the remaining path components to form the base path.
   */
  const basePath = locationArray.join('/')

  /**
   * Map the form step data to an array of path strings.
   */
  const formPaths = stepDataArray?.map(item => {
    /**
     * Clean and transform the section heading to a URL-friendly string.
     */
    const path = cleanString(item.section.heading)
    return path
  })

  /**
   * Determine the path for the verify selections page based on the language.
   */
  const verifySelectionsPath =
    language === 'es' ? 'revisar-selecciónes' : 'verify-selections'

  /**
   * Determine the path for the results page based on the language.
   */
  const resultsPath = language === 'es' ? 'resultados' : 'results'

  /**
   * Determine the path for the not eligible page based on the language.
   */
  const notEligiblePath =
    language === 'es'
      ? `${resultsPath}/no-es-elegible`
      : `${resultsPath}/not-eligible`

  /**
   * Assemble the dynamic route data object.
   * The base path of the current URL.
   * An array of path strings for the form steps.
   * The path for the verify selections page based on the language.
   * An object containing the paths for the results and not eligible pages.
   */
  const ROUTES = {
    basePath,
    indexPath,
    formPaths,
    verifySelectionsPath,
    resultsPaths: { resultsPath, notEligiblePath },
  }

  return ROUTES
}

/**
 * an async fetch to get life-event data.
 * @function
 * @param {string} lifeEvent - The inherited class from
 * @return {JSON} returns JSON data if successful
 */
export async function LifeEvent(lifeEvent) {
  let language, params, mode
  // get life-event from location
  if (lifeEvent === undefined) {
    const string = /^\/es/
    language = string.test(window.location.pathname) ? 'es_' : ''
    const windowQuery = window.location.search
    params = new URLSearchParams(windowQuery)
    mode = params.get('mode') === 'draft' ? `${params.get('mode')}/` : ''
    const location = window.location.pathname
    lifeEvent = location.split('/').pop()
  }

  let fetchPath
  if (process.env.NODE_ENV === 'production') {
    const app = document.getElementById('benefit-finder')

    if (app !== null) {
      const publishedData = app.getAttribute('json-data-file-path')
      const draftData = app.getAttribute('draft-json-data-file-path')
      fetchPath = params.get('mode') === 'draft' ? draftData : publishedData
    }
  } else {
    fetchPath = `/s3/files/benefit-finder/api/${mode}life-event/${language}${lifeEvent}.json`
  }

  if (fetchPath !== undefined) {
    const response = await fetch(fetchPath)
      .then(response => {
        if (response?.ok) {
          return response.json()
        }
        throw new Error(response?.status)
      })
      .then(responseJson => {
        return responseJson?.data ? responseJson : 'Something went wrong.'
      })
      .catch(error => {
        console.error(error)
        return 'Something went wrong.'
      })
    return response
  }
}

/**
 * a function to get the value object of selected fieldset values
 * @function
 * @param {object} item - fieldset object
 * @return {object} returns the object with a selected key
 */
export const SelectedValue = item =>
  item &&
  item.fieldset.inputs[0].inputCriteria.values.find(value => value.selected)

/**
 * a function to get array of children
 * @function
 * @param {object} item - parent fieldset object
 * @return {array} returns an array of children related to the param
 */
export const Children = item =>
  item && item.fieldset?.children.map(childItem => childItem.fieldsets[0])

/**
 * a function to get an array of all selected values
 * @function
 * @param {array} data - array of fieldset data
 * @return {array} returns the selected values
 */
export const SelectedValueAll = data =>
  data &&
  data
    .flatMap(item =>
      item.section.fieldsets.flatMap(item => {
        const selected = GET.SelectedValue(item)
        return (
          selected && [
            {
              criteriaKey: item.fieldset.inputs[0].inputCriteria.id,
              values: selected,
            },
            GET.Children(item) &&
              GET.Children(item)
                .flatMap(
                  childItem =>
                    GET.SelectedValue(childItem) && {
                      criteriaKey: childItem.fieldset.criteriaKey,
                      values: GET.SelectedValue(childItem),
                    }
                )
                .filter(element => element !== undefined),
          ]
        )
      })
    )
    .flat() // we flatten all to have only one array
    .filter(element => element !== undefined) // remove undefined

/**
 * a function that filters determines eligibility of benefits by selected values
 * @function
 * @param {array} selectedCriteria - array of selected fieldset values
 * @param {array} data - array of benefits
 * @return {array} returns the selected values
 */
export const EligibilityByCriteria = (selectedCriteria, data) => {
  // console.log(data)
  // return all eligible items
  const eligibleItems =
    data.length > 0 &&
    data.map(item => {
      // find all eligibility items that are matches to criteria key
      selectedCriteria.forEach(selected => {
        // match item to criteria key
        const criteriaEligibility = item.benefit.eligibility.find(
          criteria => criteria.criteriaKey === selected.criteriaKey
        )

        // determine it's eligibility
        if (criteriaEligibility !== undefined) {
          // look for eligible matches
          const isEligible = () => {
            let eligibility

            if (typeof selected.values.value === 'object') {
              eligibility = criteriaEligibility.acceptableValues.find(value =>
                UTILS.DateEligibility({
                  selectedValue: selected.values.value,
                  conditional: value,
                })
              )
            } else {
              eligibility = criteriaEligibility.acceptableValues.find(
                value => value === selected.values.value
              )
            }
            return eligibility
          }

          // undefined === false
          criteriaEligibility.isEligible = !!isEligible()
        }
      })
      return item
    })
  // merge all arrays and objects into one array
  const mergedEligibleItems = eligibleItems && [].concat(...eligibleItems)
  return mergedEligibleItems
}

/**
 *
 * PUT FUNCTIONS
 *
 */

/**
 * an async fetch to get life-event data.
 * @function
 * @param {string} criteriaKey
 * @param {object} currentData
 * @param {function} setCurrentData
 * @param {string} eventTargetValue
 * @return {JSON || String} returns JSON data if successful
 */
export async function Data(
  criteriaKey,
  currentData,
  setCurrentData,
  eventTargetValue
) {
  // copy current data state
  const newData = { ...currentData }

  UTILS.FindCriteria(newData.section.fieldsets, criteriaKey)
    .then(foundCriteria => {
      if (foundCriteria) {
        const inputValues =
          foundCriteria.fieldset?.inputs[0].inputCriteria.values

        inputValues.forEach(value => {
          if (value.value === eventTargetValue) {
            value.selected = true
          } else {
            delete value.selected
          }
        })
        return setCurrentData(newData)
      }
    })
    .catch(error => {
      console.error(error)
      return 'Something went wrong.'
    })
}

/**
 * an async fetch to get life-event data.
 * @function
 * @param {string} criteriaKey
 * @param {object} currentData
 * @param {function} setCurrentData
 * @param {string} eventTargetValue
 * @param {string} eventTargetID
 * @return {JSON || String} returns JSON data if successful
 */
export async function DataDate(
  criteriaKey,
  currentData,
  setCurrentData,
  eventTargetValue,
  eventTargetID
) {
  // copy current data state
  const newData = { ...currentData }

  UTILS.FindCriteria(newData.section.fieldsets, criteriaKey)
    .then(foundCriteria => {
      if (foundCriteria) {
        const inputValues =
          foundCriteria.fieldset?.inputs[0].inputCriteria.values

        if (eventTargetID) {
          if (eventTargetID.includes('day')) {
            inputValues[0].value.day = eventTargetValue
          }
          if (eventTargetID.includes('month')) {
            inputValues[0].value.month = eventTargetValue
          }
          if (eventTargetID.includes('year')) {
            inputValues[0].value.year = eventTargetValue
          }

          inputValues[0].value = { ...inputValues[0].value }
        } else {
          inputValues[0].value = eventTargetValue
        }

        const dateHasValues = obj => {
          for (const key in obj) {
            if (
              obj[key] !== null &&
              obj[key] !== undefined &&
              obj[key] !== ''
            ) {
              return true
            }
          }
          return false
        }

        if (dateHasValues(inputValues[0].value) === false) {
          inputValues[0].selected = false
        } else {
          inputValues[0].selected = true
        }

        return setCurrentData(newData)
      }
    })
    .catch(error => {
      console.error(error)
      return 'Something went wrong.'
    })
}

/**
 * Updates the application data based on the query parameters in the current URL.
 *
 * @param {string} windowQuery - The query string from the current URL.
 * @param {array} stepDataArray - An array of step data objects.
 * @param {function} setBenefitsArray - A function to update the benefits array.
 * @param {array} benefitsArray - The current benefits array.
 * @param {string} sharedToken - A shared token from shared link.
 */
export const DataFromParams = (
  windowQuery,
  stepDataArray,
  setBenefitsArray,
  benefitsArray,
  sharedToken
) => {
  /**
   * Extracts query parameters from the URL query string.
   */
  const params = UTILS.GetQueryParams(decodeURI(windowQuery))
  params.filter(param => param.criteriaKey !== sharedToken)

  /**
   * Returns the current data for a given step index.
   *
   * @private
   * @param {array} data - The step data array.
   * @param {number} stepIndex - The current step index.
   * @returns {*} The current data for the given step index.
   */
  const setCurrentData = (data, stepIndex) => data[stepIndex]

  /**
   * Updates the step data array based on the query parameters.
   * @async
   */
  async function updateStepDataArray() {
    /**
     * Updates each step data object in the array.
     */
    await Promise.all(
      stepDataArray.map(async arr => {
        arr.completed = true

        /**
         * Updates each parameter in the query parameters array.
         */
        await Promise.all(
          params.map(async param => {
            const v = param.value.includes('{')
              ? JSON.parse(param.value)
              : param.value
            if (v !== undefined && typeof v === 'object') {
              PUT.DataDate(param.criteriaKey, arr, setCurrentData, v)
            } else {
              PUT.Data(param.criteriaKey, arr, setCurrentData, v)
            }
          })
        )
      })
    )

    /**
     * Updates the benefits array based on the updated step data array.
     */
    setBenefitsArray(
      GET.EligibilityByCriteria(
        GET.SelectedValueAll(stepDataArray),
        benefitsArray
      )
    )
  }

  updateStepDataArray()
}

/**
 * Collects benefits eligibility from the provided data and updates
 * the eligibility count state.
 * @returns {Object} An object containing the eligibility counts for eligible, more information needed, and not eligible benefits.
 */
export const BenefitsEligibilityCounts = async (data, eligibleStatusLabels) => {
  const determineEligibilityStatus = data =>
    data.map(item => {
      const eligibleBenefits = item.benefit.eligibility.filter(
        x => x.isEligible === true
      )
      const notEligibleBenefits = item.benefit.eligibility.filter(
        x => x.isEligible === false
      )
      const moreInformationNeeded = item.benefit.eligibility.filter(
        x => x.isEligible === undefined
      )

      const eligibleStatus =
        eligibleBenefits.length === item.benefit.eligibility.length
          ? eligibleStatusLabels[0]
          : notEligibleBenefits.length === 0 && moreInformationNeeded.length > 0
            ? eligibleStatusLabels[1]
            : eligibleStatusLabels[2]

      return eligibleStatus
    })

  try {
    const benefitsEligibility = await determineEligibilityStatus(data)

    /**
     * Handles the length of benefits eligibility and returns an object with the count and string representation.
     * @param {Array} benefitsEligibility - An array of benefits eligibility statuses.
     * @param {string} text - The text to filter the benefits eligibility by.
     * @returns {Object} An object containing the count and string representation of the benefits eligibility.
     */
    const handleEligibilityLength = (benefitsEligibility, text) => {
      const matches = benefitsEligibility.filter(
        eligibility => eligibility === text
      )
      return { number: matches.length, string: `${matches.length}` }
    }
    const elCount = {
      eligibleBenefitCount: handleEligibilityLength(
        benefitsEligibility,
        eligibleStatusLabels[0]
      ),
      moreInfoBenefitCount: handleEligibilityLength(
        benefitsEligibility,
        eligibleStatusLabels[1]
      ),
      notEligibleBenefitCount: handleEligibilityLength(
        benefitsEligibility,
        eligibleStatusLabels[2]
      ),
    }
    return elCount
  } catch (error) {
    console.error(error)
  }
}

export const GET = {
  BenefitsEligibilityCounts,
  Children,
  EligibilityByCriteria,
  LifeEvent,
  Language,
  Routes,
  SelectedValue,
  SelectedValueAll,
}

export const PUT = {
  Data,
  DataDate,
  DataFromParams,
}

export const UTILS = {
  FindCriteria,
  DateEligibility,
  GetQueryParams,
}
