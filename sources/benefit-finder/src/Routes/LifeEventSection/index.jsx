import { useState, useEffect, useRef, useContext, Fragment } from 'react'
import { RouteContext } from '@/App'
import { useNavigate, useLocation } from 'react-router'
import PropTypes from 'prop-types'
import {
  dateInputValidation,
  cleanString,
  createMarkup,
  dataLayerUtils,
  errorHandling,
  handleSurvey,
  a11yTitles,
} from '@utils'
import { useHandleUnload, useResetElement, useScrollToAnchor } from '@hooks'
import * as apiCalls from '@api/apiCalls'
import {
  Alert,
  Button,
  Date,
  Fieldset,
  Heading,
  RadioGroup,
  Select,
  StepIndicator,
  Modal,
} from '@components'
import './_index.scss'

/**
 * a compound component that renders the main conditional view of the form
 * @component
 * @param {object} data - inherited life event step data
 * @param {func} handleData - inherited state manager
 * @param {object} ui - inherited ui translations
 * @return {html} returns a semantic html component that displays a form step
 */
const LifeEventSection = ({ data, handleData, ui }) => {
  // state
  const [formStep, setFormStep] = useState(0)
  const [modalStep, setModalStep] = useState(false)
  const [currentData, setCurrentData] = useState(() => data && data[formStep])
  const [requiredFieldsets, setRequiredFieldsets] = useState([])
  const [hasError, setHasError] = useState([])
  const [hasData, setHasData] = useState(
    () => apiCalls.GET.SelectedValueAll(data).length > 0
  )
  const [submissionCount, setSubmissionCount] = useState(0)
  const { lifeEventSection } = dataLayerUtils.dataLayerStructure
  const { buttonGroup, reviewSelectionModal, requiredLabel, sectionHeadings } =
    ui // destructure data
  useHandleUnload(hasData) // alert the user if they try to go back in browser
  const resetElement = useResetElement()
  const ROUTES = useContext(RouteContext)
  const navigate = useNavigate()
  const locale = apiCalls.GET.Language()

  let location = useLocation() // ignore prefer-const
  useScrollToAnchor(location)

  /**
   * Finds the index of the current form step in the data array.
   *
   * @param {Object[]} data - The array of form step objects.
   * @param {Object} location - The current location object.
   * @param {string} location.pathname - The current URL path.
   * @param {string} ROUTES.indexPath - The base path for the form steps.
   *
   * @returns {number} The index of the current form step, or -1 if not found.
   */
  const getFormStepIndex = () =>
    data.findIndex(obj => {
      const title = cleanString(obj.section.heading)
      return location.pathname.match(`${ROUTES.indexPath}/${title}`)
    })

  useEffect(() => {
    resetElement.current?.focus()
  }, [resetElement])

  /**
   * a function that updates our current data state
   * @function
   * @return {object} object as state
   */
  const handleUpdateData = () => {
    data[formStep] = { ...currentData }
    handleData([...data])
  }

  /**
   *
   * start alert
   *
   */
  // establish refs
  const alertFieldRef = useRef(null)

  /**
   * a function that handles class state on our collected required fields
   * @function
   * @return {html}
   */
  const handleAlert = () => {
    // remove the display class from the alert
    alertFieldRef.current.classList.remove('display-none')
    alertFieldRef.current.focus()
    setSubmissionCount(submissionCount + 1)
    currentData.completed = false
    window.scrollTo(0, 0)
    return false
  }

  /**
   * a function that triggers the modal to a closed state
   * @function
   */
  const handleSuccess = () => {
    // hide alert by adding the display class
    alertFieldRef.current.classList.add('display-none')
    currentData.completed = true
    handleUpdateData()
    setRequiredFieldsets([])
    return true
  }

  /**
   * a function that checks if all our required fields have values
   * @function
   * @return {func} either success or alert handler
   */
  const handleCheckRequiredFields = () => {
    // collect all the required fields in the current step
    return errorHandling
      .handleCheckForRequiredValues(requiredFieldsets, setHasError)
      .then(valid => {
        return valid === true ? handleSuccess() : handleAlert()
      })
  }
  /**
   *
   * end alert
   *
   */

  /**
   * a function that updates our step count and set our data index
   * @function
   * @param {number} updateIndex - value which to update step count
   * @return {null} only executes inherited functions
   */
  const handleForwardUpdate = updateIndex => {
    handleCheckRequiredFields().then(valid => {
      if (valid === true) {
        // handle dataLayer
        const { errors } = dataLayerUtils.dataLayerStructure
        dataLayerUtils.dataLayerPush(window, {
          event: errors.event,
          bfData: {
            errors: '',
            errorCount: {
              number: 0,
              string: `0`,
            },
            formSuccess: true,
          },
        })

        const stepIndex = formStep + updateIndex
        if (formStep <= data.length) {
          navigate(`/${ROUTES.indexPath}/${ROUTES.formPaths[stepIndex]}`)
          setCurrentData(data[stepIndex])
        }
        resetElement && resetElement.current.focus()
      }
    })
  }

  /**
   * a function that updates our step count and set our data index
   * @function
   * @param {number} updateIndex - value which to update step count
   * @return {null} only executes inherited functions
   */
  const handleBackUpdate = updateIndex => {
    // allow uses to navigate back, even if there are errors
    formStep === 0 ? navigate(`/${ROUTES.indexPath}`) : navigate(updateIndex)
    resetElement.current.focus()
    setHasError([])
  }

  /**
   * a function that handles the current selected value of our radio
   * and clears validation error if resolved
   * @function
   * @return {object} object as state
   */
  const handleChanged = (event, criteriaKey) => {
    window.history.replaceState({}, '', window.location.pathname)
    apiCalls.PUT.Data(
      criteriaKey,
      currentData,
      setCurrentData,
      event.target.value
    )
    errorHandling.getRequiredFieldsets(document, setRequiredFieldsets)
    hasError.length > 0 &&
      errorHandling.handleCheckForRequiredValues(requiredFieldsets, setHasError)
    setHasData(apiCalls.GET.SelectedValueAll(data).length > 0)
  }

  /**
   * a function that handles the current selected value of our radio
   * and clears validation error if resolved
   * @function
   * @return {object} object as state
   */
  const handleDateChanged = (event, criteriaKey) => {
    // if event target is empty check if all values in date are empty
    window.history.replaceState({}, '', window.location.pathname)
    errorHandling.getRequiredFieldsets(document, setRequiredFieldsets)

    async function validUpdate() {
      if (dateInputValidation(event) === true) {
        apiCalls.PUT.DataDate(
          criteriaKey,
          currentData,
          setCurrentData,
          event.target.value,
          event.target.id
        )
        hasError.length > 0 &&
          errorHandling.handleCheckForRequiredValues(
            requiredFieldsets,
            setHasError
          )
      }
    }

    validUpdate().then(() => {
      errorHandling.getNonRequiredFieldsets(
        criteriaKey,
        requiredFieldsets,
        setRequiredFieldsets,
        setHasError,
        hasError,
        apiCalls.GET.SelectedValueAll(data)
      )
      setHasData(apiCalls.GET.SelectedValueAll(data).length > 0)
    })
  }

  // manage the display of our modal initializer
  useEffect(() => {
    location.pathname.includes(ROUTES.formPaths[ROUTES.formPaths.length - 1])
      ? setModalStep(true)
      : setModalStep(false)
  }, [location])

  // handle dataLayer, based on location change
  useEffect(() => {
    // use location change to manage data layer values
    const index = getFormStepIndex()
    setFormStep(index)
    setCurrentData(data[index])
    dataLayerUtils.dataLayerPush(window, {
      event: lifeEventSection.event,
      bfData: {
        pageView: `${lifeEventSection.bfData.pageView}-${index + 1}`,
        viewTitle: data[index]?.section.heading,
      },
    })
    a11yTitles(location.pathname, locale)
    !location.hash && resetElement.current?.focus()
    !location.hash && window.scrollTo(0, 0)
    !location.hash && setHasError([]) // reset error state
  }, [location])

  useEffect(() => {
    errorHandling.getRequiredFieldsets(document, setRequiredFieldsets)
    !location.hash && resetElement.current?.focus()
    !location.hash && window.scrollTo(0, 0)
  }, [formStep])

  useEffect(() => {
    errorHandling.getRequiredFieldsets(document, setRequiredFieldsets)
  }, [])

  useEffect(() => {
    // hide the survey
    handleSurvey({ hide: true })
  })

  return (
    data && (
      <>
        <Heading className="bf-section-heading" headingLevel={1}>
          {formStep === data.length - 1
            ? `${sectionHeadings.final}`
            : formStep === 0
              ? `${sectionHeadings.start}`
              : `${sectionHeadings.continue}`}
        </Heading>
        <div className="bf-section-wrapper">
          <div className="bf-section-info">
            <StepIndicator
              current={formStep}
              setCurrent={setFormStep}
              data={data}
              key={`step-indicator-${sectionHeadings}`}
            />
            {currentData && (
              <div id="bf-section" data-testid="bf-section">
                <Alert
                  alertFieldRef={alertFieldRef}
                  heading={ui.alertBanner.heading}
                  description={ui.alertBanner.description}
                  type="error"
                  hasError={hasError.length > 0}
                  errorCount={hasError.length}
                  errorList={hasError}
                  submissionCount={submissionCount}
                ></Alert>
                <div className="bf-form-heading-group">
                  <Heading
                    className="bf-form-heading bf-usa-form-heading"
                    headingLevel={2}
                  >
                    {currentData.section.heading}
                  </Heading>
                  <div
                    className="bf-section-sub-heading"
                    dangerouslySetInnerHTML={createMarkup(
                      currentData.section.description
                    )}
                  ></div>
                </div>
                {currentData.section.fieldsets.map((item, i) => {
                  const Input = ({ item, children, index, hidden }) =>
                    item.fieldset.inputs[0].inputCriteria.type === 'Select' ? (
                      //
                      //
                      // case select
                      //
                      //
                      <Fragment
                        key={`select-${item.fieldset.criteriaKey}+${index}`}
                      >
                        <Fieldset
                          key={`select-${item.fieldset.criteriaKey}-${index}`}
                          legend={item.fieldset.legend}
                          errorMessage={item.fieldset.errorMessage}
                          hint={item.fieldset.hint}
                          required={item.fieldset.required}
                          requiredLabel={requiredLabel}
                          hidden={hidden && hidden}
                          id={item.fieldset.criteriaKey}
                          invalid={errorHandling.handleInvalid({
                            hasError,
                            criteriaKey: item.fieldset?.criteriaKey,
                          })}
                          ui={ui.errorText}
                        >
                          {item.fieldset.inputs.map((input, index) => {
                            const fieldSetId = `${item.fieldset.criteriaKey}_${index}`
                            const inputValues = input.inputCriteria.values
                            const defaultSelected = inputValues.find(
                              value => value.selected !== undefined
                            )

                            const { select, errorText } = ui

                            return (
                              <div key={fieldSetId}>
                                <Select
                                  ui={{ select, errorText }}
                                  htmlFor={fieldSetId}
                                  key={fieldSetId}
                                  options={inputValues}
                                  selected={defaultSelected?.value}
                                  onChange={event =>
                                    handleChanged(
                                      event,
                                      item.fieldset.criteriaKey
                                    )
                                  }
                                  invalid={errorHandling.handleInvalid({
                                    hasError,
                                    criteriaKey: item.fieldset?.criteriaKey,
                                    fieldSetId,
                                  })}
                                  legend={item.fieldset.legend}
                                  errorMessage={item.fieldset.errorMessage}
                                />
                              </div>
                            )
                          })}
                        </Fieldset>
                        {children || null}
                      </Fragment>
                    ) : item.fieldset.inputs[0].inputCriteria.type ===
                      'Radio' ? (
                      //
                      //
                      // case radio
                      //
                      //
                      <Fragment
                        key={`radio-${item.fieldset.criteriaKey}+${index}`}
                      >
                        {item.fieldset.inputs.map((input, index) => {
                          const fieldSetId = `${item.fieldset.criteriaKey}_${index}`

                          return (
                            <Fieldset
                              key={`radio-${item.fieldset.criteriaKey}-${index}`}
                              id={item.fieldset.criteriaKey}
                              legend={item.fieldset.legend}
                              errorMessage={item.fieldset.errorMessage}
                              hint={item.fieldset.hint}
                              required={item.fieldset.required}
                              requiredLabel={requiredLabel}
                              hidden={hidden && hidden}
                              ui={ui.errorText}
                              invalid={errorHandling.handleInvalid({
                                hasError,
                                criteriaKey: item.fieldset?.criteriaKey,
                              })}
                            >
                              <RadioGroup
                                invalid={errorHandling.handleInvalid({
                                  hasError,
                                  criteriaKey: item.fieldset?.criteriaKey,
                                })}
                                key={fieldSetId}
                                fieldSetId={fieldSetId}
                                handleChanged={handleChanged}
                                values={input.inputCriteria.values}
                                criteriaKey={item.fieldset.criteriaKey}
                                errorMessage={item.fieldset.errorMessage}
                                legend={item.fieldset.legend}
                                ui={ui.errorText}
                              />
                            </Fieldset>
                          )
                        })}
                        {children || null}
                      </Fragment>
                    ) : item.fieldset.inputs[0].inputCriteria.type ===
                      'Date' ? (
                      //
                      //
                      // case date
                      //
                      //
                      <Fragment
                        key={`date-${item.fieldset.criteriaKey}+${index}`}
                      >
                        <Fieldset
                          key={`date-${item.fieldset.criteriaKey}-${index}`}
                          legend={item.fieldset.legend}
                          errorMessage={item.fieldset.errorMessage}
                          hint={item.fieldset.hint}
                          required={item.fieldset.required}
                          requiredLabel={requiredLabel}
                          hidden={hidden && hidden}
                          id={item.fieldset.criteriaKey}
                          invalid={errorHandling.handleInvalid({
                            hasError,
                            criteriaKey: item.fieldset?.criteriaKey,
                          })}
                          ui={ui.errorText}
                        >
                          {item.fieldset.inputs.map((input, index) => {
                            const fieldSetId = `${item.fieldset.criteriaKey}_${index}`

                            return (
                              <div key={fieldSetId}>
                                <Date
                                  value={input.inputCriteria.values[0]?.value}
                                  onChange={event =>
                                    handleDateChanged(
                                      event,
                                      item.fieldset.criteriaKey
                                    )
                                  }
                                  ui={ui}
                                  errorMessage={item.fieldset.errorMessage}
                                  parentLegend={item.fieldset.legend}
                                  id={fieldSetId}
                                  invalid={errorHandling.handleInvalid({
                                    hasError,
                                    criteriaKey: item.fieldset?.criteriaKey,
                                    fieldSetId,
                                    useFilter: true,
                                  })}
                                />
                              </div>
                            )
                          })}
                        </Fieldset>
                        {children || null}
                      </Fragment>
                    ) : null

                  const parentElement = ({ item, i }) =>
                    Input({ item, index: i })

                  const checkParentValue = item => {
                    const selectedParentValue = apiCalls.GET.SelectedValue(item)

                    /**
                     * a value that checks the selected parent validator to expose child inputs in the form
                     * @return {boolean} returns true or false
                     */

                    const checkDateDependency = value => {
                      const dateObj = { month: 0, day: 0, year: 0 }
                      const dateValues = value?.value

                      if (dateValues === undefined) {
                        return true
                      }

                      const dateKeys = Object.keys(dateObj)
                      const valuesKeys = Object.keys(dateValues)

                      // check to ensure all keys and values for date obj are present
                      if (dateKeys.length !== valuesKeys.length) {
                        return true
                      } else {
                        for (const key of dateKeys) {
                          const value = dateValues[key]

                          if (value === undefined || value < 0) {
                            return true
                          }

                          if (
                            dateValues.year !== undefined &&
                            dateValues.year.length < 4
                          ) {
                            return true
                          }
                        }

                        return !apiCalls.UTILS.DateEligibility({
                          selectedValue: selectedParentValue.value,
                          conditional:
                            item.fieldset.inputs[0].inputCriteria
                              .childDependencyOption,
                        })
                      }
                    }

                    const checkFieldDependencies = value => {
                      return (
                        value?.value !==
                        item.fieldset.inputs[0].inputCriteria
                          .childDependencyOption
                      )
                    }

                    const hidden =
                      item.fieldset.inputs[0].inputCriteria.type === 'Date'
                        ? checkDateDependency(selectedParentValue)
                        : checkFieldDependencies(selectedParentValue)

                    return hidden
                  }

                  const parentWithChildElement = ({ item, i }) => {
                    return Input({
                      item,
                      index: i,
                      children: item.fieldset.children.map(
                        child =>
                          child.fieldsets.length &&
                          child.fieldsets.map((childItem, childItemIndex) => {
                            // if a parent determines the children inputs are hidden, we expect that the selected values in child items are de-selected
                            const selectedValue =
                              childItem && apiCalls.GET.SelectedValue(childItem)

                            const isHidden = checkParentValue(item)

                            if (
                              isHidden === true &&
                              selectedValue !== undefined
                            ) {
                              delete selectedValue.selected
                            }

                            return Input({
                              item: childItem,
                              index: childItemIndex,
                              hidden: isHidden,
                            })
                          })
                      ),
                    })
                  }

                  // children check
                  const fieldSet = () =>
                    item.fieldset.children.length > 0
                      ? parentWithChildElement({ item, i })
                      : parentElement({ item, i })

                  return fieldSet()
                })}
              </div>
            )}
            <div className="bf-section-nav-btn-group">
              <Button
                outline
                onClick={() => handleBackUpdate(-1)}
                data-test="button"
              >
                {buttonGroup[0].value}
              </Button>
              {modalStep === false ? (
                <Button
                  secondary
                  onClick={() => handleForwardUpdate(1)}
                  data-test="button"
                >
                  {buttonGroup[1].value}
                </Button>
              ) : (
                <Modal
                  id="nav-modal"
                  dataLayerValue={{ viewTitle: currentData.section.heading }}
                  modalHeading={reviewSelectionModal.heading}
                  navItemOneLabel={reviewSelectionModal.buttonGroup[0].value}
                  navItemOneFunction={() =>
                    navigate(
                      `/${ROUTES.indexPath}/${ROUTES.verifySelectionsPath}`
                    )
                  }
                  navItemTwoLabel={reviewSelectionModal.buttonGroup[1].value}
                  navItemTwoFunction={() =>
                    navigate(
                      `/${ROUTES.indexPath}/${ROUTES.resultsPaths.resultsPath}`
                    )
                  }
                  triggerLabel={buttonGroup[1].value}
                  handleCheckRequiredFields={handleCheckRequiredFields}
                  completed={currentData.completed}
                />
              )}
            </div>
          </div>
        </div>
      </>
    )
  )
}

LifeEventSection.propTypes = {
  props: PropTypes.any,
  formStep: PropTypes.number,
  data: PropTypes.array,
  ui: PropTypes.object,
}

export default LifeEventSection
