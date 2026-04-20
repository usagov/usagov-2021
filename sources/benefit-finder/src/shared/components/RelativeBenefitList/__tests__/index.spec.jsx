import { render, waitFor, screen } from '@testing-library/react'
import RelativeBenefitList from '../index.jsx'
import content from '@api/mock-data/current.js'

const { data } = JSON.parse(content)
const relativeBenefits = data.lifeEventForm.relevantBenefits
const args = {
  data: relativeBenefits,
  carrotType: 'carrot',
}

describe('RelativeBenefitList', () => {
  it('renders a match to the previous snapshot', () => {
    const { asFragment } = render(
      <RelativeBenefitList data={args.data} carrotType={args.carrotType} />
    )
    expect(asFragment()).toMatchSnapshot()
  })

  it('renders icons based on its lifeEvent id', async () => {
    render(
      <RelativeBenefitList data={args.data} carrotType={args.carrotType} />
    )

    // wait until the last relative benefit renders
    await waitFor(() => {
      screen.getByTestId(relativeBenefits[1].lifeEvent.lifeEventId)
    })
    const links = screen.getAllByRole('link')

    // ensure the link contains values from the lifeEventId
    expect(links[0].href).toContain(relativeBenefits[0].lifeEvent.lifeEventId)
    expect(links[1].href).toContain(relativeBenefits[1].lifeEvent.lifeEventId)
    // ensure the icons that contain values from the lifeEventId are in the dom
    expect(
      screen.getByTestId(
        `benefit-finder-icon--${relativeBenefits[0].lifeEvent.lifeEventId}`
      )
    ).toBeInTheDocument()
    expect(
      screen.getByTestId(
        `benefit-finder-icon--${relativeBenefits[1].lifeEvent.lifeEventId}`
      )
    ).toBeInTheDocument()
  })
})
