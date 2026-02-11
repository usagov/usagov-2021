/**
 * a parse our date object
 * @function
 * @param {object} value - { day, month, year }
 * @return {Date} returns a standard format Date ie 1995-12-17T03:24:00
 */
const parseDate = value => {
  const d =
    value && new window.Date(Date.UTC(value.year, value.month, value.day))
  const adjustTimeOffset = new Date(
    d.getTime() + Math.abs(d.getTimezoneOffset() * 60000)
  )
  return adjustTimeOffset
}

export default parseDate
