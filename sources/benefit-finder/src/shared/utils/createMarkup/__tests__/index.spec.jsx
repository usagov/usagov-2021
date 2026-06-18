import createMarkup from '../index'

const mockMarkUpString =
  '<p><strong>Losing a loved one is hard.</strong> Finding help shouldn’t be.</p><p>Screen the federal benefits you may be eligible for.</p>'

const mockResult = { __html: mockMarkUpString }

describe('test createMarkup utility', () => {
  it('returns an object with _html key and string value', () => {
    expect(createMarkup(mockMarkUpString)).toMatchObject(mockResult)
  })
})
