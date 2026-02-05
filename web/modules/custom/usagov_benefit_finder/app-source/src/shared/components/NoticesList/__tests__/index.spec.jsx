import { render } from '@testing-library/react'
import NoticesList from '../index.jsx'
import * as en from '@locales/en/en.json'

describe('NoticesList', () => {
  const t = en
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(
      <NoticesList
        data={t.intro.notices.list}
        iconAlt={t.intro.notices.iconAlt}
      />
    )
    expect(asFragment()).toMatchSnapshot()
  })
})
