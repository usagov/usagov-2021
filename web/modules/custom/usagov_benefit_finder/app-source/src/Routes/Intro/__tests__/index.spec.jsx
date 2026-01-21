import { render } from '@testing-library/react'
import { BrowserRouter } from 'react-router'
import Intro from '../index.jsx'
import * as en from '@locales/en/en.json'
import content from '@api/mock-data/current.js'

const t = en
const { data } = JSON.parse(content)
const { lifeEventForm } = data

describe('Intro', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(
      <Intro content={lifeEventForm} ui={t.intro} />,
      {
        wrapper: BrowserRouter,
      }
    )
    expect(asFragment()).toMatchSnapshot()
  })
})
