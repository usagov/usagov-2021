import { render, screen } from '@testing-library/react'
import Alert from '../index.jsx'

const alertAriaStatus = {
  default: {
    role: 'alert',
    tabindex: 0,
    ariaLive: 'polite',
    ariaHidden: 'false',
    class: 'usa-alert--info',
  },
  error: {
    role: 'alert',
    tabindex: 0,
    ariaLive: 'assertive',
    ariaHidden: 'false',
    class: 'display-none',
  },
  noError: {
    role: 'alert',
    tabindex: 0,
    ariaLive: 'polite',
    ariaHidden: 'true',
    class: 'display-none',
  },
}

describe('Alert', () => {
  it('renders a match to the previous snapshot, default', () => {
    const { asFragment } = render(<Alert />)
    expect(asFragment()).toMatchSnapshot()
  })

  it('default alert has the correct DOM structure', () => {
    render(<Alert />)
    const alert = screen.getByRole('alert', { hidden: true })
    expect(alert.role).toBe(alertAriaStatus.default.role)
    expect(alert.tabIndex).toBe(alertAriaStatus.default.tabindex)
    expect(alert.ariaLive).toBe(alertAriaStatus.default.ariaLive)
    expect(alert.ariaHidden).toBe(alertAriaStatus.default.ariaHidden)
    expect(Array.from(alert.classList)).includes(alertAriaStatus.default.class)
  })

  it('renders a match to the previous snapshot, error type with no error', () => {
    const { asFragment } = render(<Alert type="error" hasError={false} />)
    expect(asFragment()).toMatchSnapshot()
  })

  it('error type with no error has the correct DOM structure', () => {
    render(<Alert type="error" hasError={false} />)
    const alert = screen.getByRole('alert', { hidden: true })
    expect(alert.role).toBe(alertAriaStatus.noError.role)
    expect(alert.tabIndex).toBe(alertAriaStatus.noError.tabindex)
    expect(alert.ariaLive).toBe(alertAriaStatus.noError.ariaLive)
    expect(alert.ariaHidden).toBe(alertAriaStatus.noError.ariaHidden)
    expect(Array.from(alert.classList)).includes(alertAriaStatus.noError.class)
  })

  it('renders a match to the previous snapshot when it has an error', () => {
    const { asFragment } = render(<Alert type="error" hasError={true} />)
    expect(asFragment()).toMatchSnapshot()
  })

  it('error type with error has the correct DOM structure', () => {
    render(<Alert type="error" hasError={true} />)
    const alert = screen.getByRole('alert', { hidden: true })
    expect(alert.role).toBe(alertAriaStatus.error.role)
    expect(alert.tabIndex).toBe(alertAriaStatus.error.tabindex)
    expect(alert.ariaLive).toBe(alertAriaStatus.error.ariaLive)
    expect(alert.ariaHidden).toBe(alertAriaStatus.error.ariaHidden)
    expect(Array.from(alert.classList)).not.includes(
      alertAriaStatus.error.class
    )
  })
})
