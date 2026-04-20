import parseDate from '../index'

let mockLocale
const dateFormatOptions = {
  year: 'numeric',
  month: 'long',
  day: 'numeric',
}

const selectedValue = {
  month: '0',
  day: '22',
  year: '2022',
}

const expectedEnValue = 'January 22, 2022'

const expectedEsValue = '22 de enero de 2022'

describe('test parseDate utility', () => {
  it('returns a localized string from a date value based on locale', () => {
    mockLocale = 'en'

    expect(
      parseDate(selectedValue).toLocaleDateString(mockLocale, dateFormatOptions)
    ).toBe(expectedEnValue)

    mockLocale = 'es'

    expect(
      parseDate(selectedValue).toLocaleDateString(mockLocale, dateFormatOptions)
    ).toBe(expectedEsValue)
  })
})
