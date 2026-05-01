import { useEffect, useRef, useState } from 'react'
import NavModal from 'react-modal'
import PropTypes from 'prop-types'
import { Button, ObfuscatedLink, Icon, Heading } from '@components'
import { scrollLock, dataLayerUtils } from '@utils'

import './_index.scss'

const customStyles = {
  overlay: {
    position: 'fixed',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    zIndex: 999,
  },
  content: {
    top: '50%',
    left: '50%',
    right: 'auto',
    bottom: 'auto',
    marginRight: '-50%',
    padding: '1rem',
    transform: 'translate(-50%, -50%)',
    width: '90%',
    borderRadius: '8px',
    borderColor: '#005ea2',
    maxWidth: '41.375rem',
    height: '100%',
    maxHeight: '24.625rem',
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'center',
    alignContent: 'center',
  },
}

/**
 * a functional component that renders a trigger, that when clicked opens a dialog
 * @component
 * @param {string} id - matches to modal control
 * @param {React.ReactNode} children - inherited children
 * @param {string} triggerLabel - passed to button for triggering modal
 * @param {string} modalHeading - heading value
 * @param {string} navItemOneLabel - passed to button for nav item in modal
 * @param {string} navItemTwoLabel - passed to button for nav item in modal
 * @param {function} handleCheckRequiredFields - inherited async function to check validity of fields
 * @return {html} returns html for setting up a usa-modal component
 */
const Modal = ({
  id,
  children,
  triggerLabel,
  modalHeading,
  navItemOneLabel,
  navItemOneFunction,
  navItemTwoLabel,
  navItemTwoFunction,
  handleCheckRequiredFields,
  dataLayerValue,
}) => {
  // state
  const triggerRef = useRef(null)
  const { modal, errors } = dataLayerUtils.dataLayerStructure
  const [modalOpen, setModalOpen] = useState(false)

  /**
   * a function that triggers the modal to an open state
   * @function
   */
  const handleOpenModal = () => {
    handleCheckRequiredFields().then(valid => valid && setModalOpen(valid))
    window.scrollTo(0, 0)
  }

  /**
   * a function that triggers the modal to a closed state
   * @function
   * @param {ref} triggerRef - passed to button for triggering modal
   */
  const handleCloseModal = triggerRef => {
    // focus the trigger if it is still in the DOM
    triggerRef && triggerRef.current.focus()
    window.scrollTo(0, 0)
    scrollLock.disableScroll()
    setModalOpen(false)
    return true
  }

  const handleKeyValidation = e => e.which === 32 || e.which === 13

  useEffect(() => {
    modalOpen && scrollLock.enableScroll()
  }, [modalOpen])

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      NavModal.setAppElement('#benefit-finder')
    }, 0) // run after document loaded
    return () => {
      clearTimeout(timeoutId)
    }
  }, [])

  // handle dataLayer
  useEffect(() => {
    const handleModalData = async () => {
      modalOpen === true &&
        dataLayerUtils.dataLayerPush(window, {
          event: modal.event,
          bfData: {
            pageView: modal.bfData.pageView,
            viewTitle: `${dataLayerValue.viewTitle} modal`,
          },
        })
    }

    // async so we can handle duplicates if needed
    handleModalData().then(() => {
      // handle dataLayer
      modalOpen === true &&
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
    })
  }, [modalOpen])

  /**
   * a functional component that renders a two links as a buttons for navigating out of the dialog
   * @component
   * @param {string} navItemOneLabel - passed to button for nav item in modal
   * @param {func} navItemOneFunction - passed to button for nav item in modal
   * @param {string} navItemOneLabel - passed to button for nav item in modal
   * @param {func} navItemTwoFunction - passed to button for nav item in modal
   * @return {html} returns an obfuscated anchor element
   */
  // similar to ButtonGroup but we need links for uswds to close modal, this item is default and conditional
  const GroupNavigation = ({
    navItemOneLabel,
    navItemOneFunction,
    navItemTwoLabel,
    navItemTwoFunction,
  }) => {
    const handleClick = navFunction => {
      handleCloseModal(triggerRef) && navFunction()
    }
    const handleKeyDown = (e, navFunction) => {
      handleKeyValidation(e) && handleCloseModal(triggerRef) && navFunction()
    }
    return (
      <ul
        className="bf-modal bf-usa-button-group usa-button-group width-full"
        data-testid="modal-button-group"
      >
        <li
          className="bf-usa-button-group__item usa-button-group__item width-full"
          key="bf-nav-item-one"
        >
          <Button
            id="bf-navItemOneBtn"
            className="bf-nav-item-one width-full"
            onClick={() => handleClick(navItemOneFunction)}
            onKeyDown={e => handleKeyDown(e, navItemOneFunction)}
            noCarrot
            tabIndex="0"
            secondary
          >
            {navItemOneLabel}
          </Button>
        </li>
        <li
          className="bf-usa-button-group__item usa-button-group__item width-full"
          key="nav-item-two"
        >
          <Button
            id="bf-navItemTwoBtn"
            className="bf-nav-item-two width-full"
            onClick={() => handleClick(navItemTwoFunction)}
            onKeyDown={e => handleKeyDown(e, navItemTwoFunction)}
            noCarrot
            tabIndex="0"
            secondary
          >
            {navItemTwoLabel}
          </Button>
        </li>
      </ul>
    )
  }

  return (
    <div id={id} className="bf-usa-modal-group">
      <ObfuscatedLink
        onClick={() => handleOpenModal()}
        onKeyDown={e => handleKeyValidation(e) && handleOpenModal()}
        noCarrot
        tabIndex="0"
        triggerRef={triggerRef}
        aria-label="Continue"
        role="button"
      >
        {triggerLabel}
      </ObfuscatedLink>
      <NavModal
        id="benefit-finder-modal"
        isOpen={modalOpen}
        onRequestClose={() => handleCloseModal(triggerRef)}
        style={customStyles}
        aria={{
          label: modalHeading,
        }}
        ariaHideApp={false}
      >
        <div>
          <button
            type="button"
            aria-label="Close"
            className="bf-modal-button"
            data-testid="button"
            onClick={() => handleCloseModal(triggerRef)}
          >
            <Icon type="modal-close" color="black" aria-hidden="true" />
          </button>
          <Heading headingLevel={1} className="bf-modal-heading">
            {modalHeading}
          </Heading>
          {children || (
            <GroupNavigation
              navItemOneLabel={navItemOneLabel}
              navItemOneFunction={navItemOneFunction}
              navItemTwoLabel={navItemTwoLabel}
              navItemTwoFunction={navItemTwoFunction}
            />
          )}
        </div>
      </NavModal>
    </div>
  )
}

Modal.propTypes = {
  id: PropTypes.string,
  children: PropTypes.node,
  triggerLabel: PropTypes.string,
  modalHeading: PropTypes.string,
  navItemOneLabel: PropTypes.string,
  navItemOneFunction: PropTypes.func,
  navItemTwoLabel: PropTypes.string,
  navItemTwoFunction: PropTypes.func,
  handleCheckRequiredFields: PropTypes.func,
}

export default Modal
