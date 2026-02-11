import { render, screen } from '@testing-library/react'
import dateInputValidation from '../index'
import IndexHTML from './assets/index.spec'

let customEvent

test('month value should be determined if valid', async () => {
  render(<IndexHTML />)
  const monthElement = screen.getByTestId('input-month')

  customEvent = {
    target: {
      value: '0',
      id: monthElement.id,
    },
  }

  expect(dateInputValidation(customEvent)).toBe(true)

  customEvent = {
    target: {
      value: '',
      id: monthElement.id,
    },
  }

  expect(dateInputValidation(customEvent)).toBe(true)
})

test('day value should be determined if valid', async () => {
  render(<IndexHTML />)
  const dayElement = screen.getByTestId('input-day')

  customEvent = {
    target: {
      value: '33',
      id: dayElement.id,
    },
  }

  expect(dateInputValidation(customEvent)).toBe(false)

  customEvent = {
    target: {
      value: '2',
      id: dayElement.id,
    },
  }

  expect(dateInputValidation(customEvent)).toBe(true)
})

test('year value should be determined if valid', async () => {
  render(<IndexHTML />)
  const yearElement = screen.getByTestId('input-year')

  customEvent = {
    target: {
      value: '1900',
      id: yearElement.id,
    },
  }

  expect(dateInputValidation(customEvent)).toBe(true)

  customEvent = {
    target: {
      value: '1899',
      id: yearElement.id,
    },
  }

  expect(dateInputValidation(customEvent)).toBe(false)

  customEvent = {
    target: {
      value: '2222',
      id: yearElement.id,
    },
  }

  expect(dateInputValidation(customEvent)).toBe(false)

  const currentYear = new Date().getFullYear()

  customEvent = {
    target: {
      value: `${currentYear}`,
      id: yearElement.id,
    },
  }

  expect(dateInputValidation(customEvent)).toBe(true)
})
