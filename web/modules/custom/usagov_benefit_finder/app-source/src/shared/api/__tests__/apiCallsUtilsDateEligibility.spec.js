import * as apiCalls from '../apiCalls'

// The Function DateEligibility handles the date calculations.

// Our comparison string (content) values for acceptable criteria should be limited to the following formats
// compared to a specific date
// "<operator><MM><hyphen><DD><hyphen><YYYY>"

// ie. // "<01-01-1978"

// or

// compared to a length of time
// "<operator><number><years>"

// ie. // "<18years"

// Our conditionals handle the following operators
// // const operators = /['>', '>=', '<', '<=', '=']/g

// Write a spec to test each string format with each acceptable conditional

// ie.

// take the following values of:

// selected value: 01-01-1977
// criteria format 1: 01-01-1978
// criteria format 2: 18years

// loop through our array of operators
// then compare to the expected output

// note: month: === monthIndex
// 0 - 11
const sameDate = { year: 2023, month: 0, day: 1 }
const closerDate = { year: 2023, month: 0, day: 2 } // younger
const furtherDate = { year: 2022, month: 11, day: 30 } // older

// dynamically generate to -18 years from todays date
const age18 = new window.Date(
  new Date().getFullYear() - 18,
  new Date().getMonth(),
  new Date().getDate()
)

const same18 = {
  year: age18.getFullYear(),
  month: age18.getMonth(),
  day: age18.getDate(),
}

// one day earlier
const older18 = {
  year: age18.getFullYear(),
  month: age18.getMonth(),
  day: age18.getDate() - 1,
}

// one day later
const younger18 = {
  year: age18.getFullYear(),
  month: age18.getMonth(),
  day: age18.getDate() + 1,
}

const conditionalsSpecificDate = [
  {
    value: '>01-01-2023', // after 2023 (ie. 2022)
    isSame: false,
    isCloser: true,
    isFurther: false,
  },
  {
    value: '>=01-01-2023', // after or in 2023 (ie. 2022)
    isSame: true,
    isCloser: true,
    isFurther: false,
  },
  {
    value: '<01-01-2023', // before 2023 (ie. 2023-01-01)
    isSame: false,
    isCloser: false,
    isFurther: true,
  },
  {
    value: '<=01-01-2023',
    isSame: true,
    isCloser: false,
    isFurther: true,
  },
  {
    value: '=01-01-2023',
    isSame: true,
    isCloser: false,
    isFurther: false,
  },
]

const conditionalsLengthOfTime = [
  {
    value: '>18years', // more than 18years
    isSame: false,
    isOlder: true,
    isYounger: false,
  },
  {
    value: '>=18years', // 18years years or more
    isSame: true,
    isOlder: true,
    isYounger: false,
  },
  {
    value: '<18years',
    isSame: false,
    isOlder: false,
    isYounger: true,
  },
  {
    value: '<=18years',
    isSame: true,
    isOlder: false,
    isYounger: true,
  },
  {
    value: '=18years',
    isSame: true,
    isOlder: false,
    isYounger: false,
  },
]

const today = new window.Date(
  new Date().getFullYear(),
  new Date().getMonth(),
  new Date().getDate()
)

const conditionalsOthers = [
  {
    value: '<01-01-1978', // before 1978
    selectedValue: { year: 1977, month: 0, day: 1 },
    eval: true,
  },
  {
    value: '>01-01-1978', // after 1978
    selectedValue: { year: 1977, month: 0, day: 1 },
    eval: false,
  },
  {
    // dynamic
    value: '<2years', // less
    selectedValue: {
      year: today.getFullYear(),
      month: today.getMonth(),
      day: today.getDate() - 1,
    },
    eval: true,
  },
  {
    value: '<2years', // more
    selectedValue: {
      year: today.getFullYear() - 3,
      month: today.getMonth(),
      day: today.getDate(),
    },
    eval: false,
  },
  {
    value: '>18years', // older than
    selectedValue: {
      year: 1978,
      month: 0,
      day: 1,
    },
    eval: true,
  },
  {
    value: '<64years', // younger than
    selectedValue: {
      year: 1978,
      month: 0,
      day: 1,
    },
    eval: true,
  },
  {
    // dynamic
    value: '>=62years', // younger than
    selectedValue: {
      year: today.getFullYear() - 61,
      month: today.getMonth(),
      day: today.getDate(),
    },
    eval: false,
  },
  {
    value: '>=62years', // equal to
    selectedValue: {
      year: today.getFullYear() - 62,
      month: today.getMonth(),
      day: today.getDate(),
    },
    eval: true,
  },
  {
    value: '>=62years', // older than
    selectedValue: {
      year: today.getFullYear() - 64,
      month: today.getMonth(),
      day: today.getDate(),
    },
    eval: true,
  },
]

test('correctly accepts and evaluates a date object compared to a specific date of the conditional where the conditional time values are equal in time', () => {
  conditionalsSpecificDate.forEach(conditional =>
    expect(
      apiCalls.UTILS.DateEligibility({
        selectedValue: sameDate,
        conditional: conditional.value,
      })
    ).toBe(conditional.isSame)
  )
})

test('correctly accepts and evaluates a date object compared to a specific date of the conditional where the conditional time values are closer in time', () => {
  conditionalsSpecificDate.forEach(conditional =>
    expect(
      apiCalls.UTILS.DateEligibility({
        selectedValue: closerDate,
        conditional: conditional.value,
      })
    ).toBe(conditional.isCloser)
  )
})

test('correctly accepts and evaluates a date object compared to a specific date of the conditional where the conditional time values are further in time', () => {
  conditionalsSpecificDate.forEach(conditional =>
    expect(
      apiCalls.UTILS.DateEligibility({
        selectedValue: furtherDate,
        conditional: conditional.value,
      })
    ).toBe(conditional.isFurther)
  )
})

test('correctly accepts and evaluates a date object compared to a length of time of the conditional where the conditional time values are the same', () => {
  conditionalsLengthOfTime.forEach(conditional =>
    expect(
      apiCalls.UTILS.DateEligibility({
        selectedValue: same18,
        conditional: conditional.value,
      })
    ).toBe(conditional.isSame)
  )
})

test('correctly accepts and evaluates a date object compared to a length of time of the conditional where the conditional time values are older', () => {
  conditionalsLengthOfTime.forEach(conditional =>
    expect(
      apiCalls.UTILS.DateEligibility({
        selectedValue: older18,
        conditional: conditional.value,
      })
    ).toBe(conditional.isOlder)
  )
})

test('correctly accepts and evaluates a date object compared to a length of time of the conditional where the conditional time values are younger', () => {
  conditionalsLengthOfTime.forEach(conditional =>
    expect(
      apiCalls.UTILS.DateEligibility({
        selectedValue: younger18,
        conditional: conditional.value,
      })
    ).toBe(conditional.isYounger)
  )
})

test('other correctly accepts and evaluates a date object compared to a length of time of the conditional where the conditional time values are different', () => {
  conditionalsOthers.forEach(conditional =>
    expect(
      apiCalls.UTILS.DateEligibility({
        selectedValue: conditional.selectedValue,
        conditional: conditional.value,
      })
    ).toBe(conditional.eval)
  )
})
