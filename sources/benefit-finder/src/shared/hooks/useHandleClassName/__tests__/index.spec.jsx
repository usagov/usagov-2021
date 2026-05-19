import { render, screen } from '@testing-library/react'
import IndexHTML from './assets/index.spec.jsx'

const defaultClasses = 'usa-test-class-one usa-test-class-two'
const propClass = 'usa-test-class-prop'
const withPropClass =
  'usa-test-class-one usa-test-class-one usa-test-class-prop'
const shouldNotInclude = 'usa-test-class-not'
const utilityClass = 'usa-test-class-utility'

test('element should have default classes from arrays', async () => {
  render(<IndexHTML />)
  // element is initially not present...
  // wait for appearance and return the element
  const component = await screen.findByTestId('class-name-html')
  expect(component).toBeInTheDocument()
  expect(component).toHaveClass(defaultClasses)
  expect(component).not.toHaveClass(shouldNotInclude)
})

test('element should have default classes and prop class from passed className prop', async () => {
  render(<IndexHTML className={propClass} />)
  // element is initially not present...
  // wait for appearance and return the element
  const component = await screen.findByTestId('class-name-html')
  expect(component).toBeInTheDocument()
  expect(component).toHaveClass(withPropClass)
})

test('element should handle a utility prop and class with logic based on prop', async () => {
  const { rerender } = render(<IndexHTML className={propClass} />)
  // element is initially not present...
  // wait for appearance and return the element
  const component = await screen.findByTestId('class-name-html')
  expect(component).toBeInTheDocument()
  expect(component).toHaveClass(withPropClass)
  // add utility prop
  rerender(<IndexHTML className={propClass} utility />)
  expect(component).toHaveClass(utilityClass)
})
