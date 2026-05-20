// import react-testing methods
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import IndexHTML from './assets/index.spec.jsx'

test('reset element should appear in the DOM', async () => {
  render(<IndexHTML />)
  // element is initially not present...
  // wait for appearance and return the element
  const component = await screen.findByTestId('index-html')
  expect(component).toBeInTheDocument()
  const resetComponent = await screen.findByTestId('index-reset')
  expect(resetComponent).toBeInTheDocument() // it renders in the DOM
})

test('it should not be in the tab index', async () => {
  const { baseElement } = render(<IndexHTML />)
  const component = await screen.findByTestId('index-html')
  expect(component).toBeInTheDocument()
  const resetComponent = await screen.findByTestId('index-reset')
  expect(resetComponent).toBeInTheDocument()

  const bodyElement = baseElement
  const skipLink = screen.getByTestId('skip-link')
  const button = screen.getByTestId('button')
  const input = screen.getByTestId('input')
  const select = screen.getByTestId('select')
  const textarea = screen.getByTestId('textarea')

  // Simulate tabbing through the elements
  await userEvent.tab()
  expect(skipLink).toHaveFocus()

  await userEvent.tab()
  expect(button).toHaveFocus()

  await userEvent.tab()
  expect(input).toHaveFocus()

  await userEvent.tab()
  expect(select).toHaveFocus()

  await userEvent.tab()
  expect(textarea).toHaveFocus()

  await userEvent.tab()
  expect(bodyElement).toHaveFocus()

  await userEvent.tab()
  expect(skipLink).toHaveFocus()
})

test('it focus on a click event', async () => {
  render(<IndexHTML />)
  const component = await screen.findByTestId('index-html')
  expect(component).toBeInTheDocument()
  const resetComponent = await screen.findByTestId('index-reset')
  expect(resetComponent).toBeInTheDocument()
  const onClickButton = screen.getByTestId('negative-tab-index')

  // Simulate click event through the elements
  await userEvent.click(onClickButton)
  expect(resetComponent).toHaveFocus()
})

test('it should focus on skip element after reset element', async () => {
  render(<IndexHTML />)
  const component = await screen.findByTestId('index-html')
  expect(component).toBeInTheDocument()
  const resetComponent = await screen.findByTestId('index-reset')
  expect(resetComponent).toBeInTheDocument()
  const onClickButton = screen.getByTestId('negative-tab-index')
  const skipLink = screen.getByTestId('skip-link')

  // Simulate click event through the elements
  await userEvent.click(onClickButton)
  expect(resetComponent).toHaveFocus()

  await userEvent.tab()
  expect(skipLink).toHaveFocus()
})
