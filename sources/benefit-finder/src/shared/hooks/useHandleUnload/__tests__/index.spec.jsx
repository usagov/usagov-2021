import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { vi } from 'vitest'
import { handleBeforeUnload } from '../index' // Import your hook
import IndexHTML from './assets/index.spec.jsx'

// Mock the window event listeners
let addEventListenerSpy
let removeEventListenerSpy

// mock event
const eventMock = { preventDefault: vi.fn(), returnValue: '' }

beforeEach(() => {
  addEventListenerSpy = vi.spyOn(window, 'addEventListener')
  removeEventListenerSpy = vi.spyOn(window, 'removeEventListener')
})

afterEach(() => {
  addEventListenerSpy.mockRestore()
  removeEventListenerSpy.mockRestore()
})

test('Adds and removes event listener based on hasData prop', async () => {
  // Initial render with hasData set to true
  const { rerender } = render(<IndexHTML hasData={true} />)
  // element is initially not present...
  // wait for appearance and return the element
  const component = await screen.findByTestId('unload-html')
  expect(component).toBeInTheDocument()

  // Check that window.addEventListener is called with the 'beforeunload' event
  expect(addEventListenerSpy).toHaveBeenCalledWith(
    'beforeunload',
    handleBeforeUnload
  )

  // Re-render with hasData set to false
  rerender(<IndexHTML hasData={false} />)

  // Check that window.removeEventListener is called with the 'beforeunload' event
  expect(removeEventListenerSpy).toHaveBeenCalledWith(
    'beforeunload',
    handleBeforeUnload
  )
})

test('Handles beforeunload event when hasData is true', () => {
  // Render the component with hasData set to true
  render(<IndexHTML hasData={true} />)

  // Trigger the beforeunload event
  window.dispatchEvent(new Event('beforeunload', handleBeforeUnload(eventMock)))

  // Check that preventDefault was called
  expect(eventMock.preventDefault).toHaveBeenCalled()
})

test('Handles beforeunload event when hasData is changed with state', async () => {
  render(<IndexHTML />)

  const input = await screen.findByTestId('unload-input-html')
  expect(input).toBeInTheDocument()

  await userEvent.type(input, 'test')
  expect(input).toHaveValue('test')

  const returnValue = 'this value should get reverted to an empty string'

  eventMock.returnValue = returnValue

  expect(eventMock.returnValue).toBe(returnValue)

  // Trigger the beforeunload event
  window.dispatchEvent(new Event('beforeunload', handleBeforeUnload(eventMock)))

  // Check that preventDefault was called
  expect(eventMock.preventDefault).toHaveBeenCalled()
  // once the mock event is called our handler function reverts the value to an empty string
  expect(eventMock.returnValue).toBe('')
})
