import buildURIParameter from '../index'

// Mock window.location.href in your Jest test
const mockURI = 'http://localhost:3000/death'

// Define a custom URL you want to set for testing
const expectedURI = `http://localhost:3000/death?applicant_date_of_birth=%7B%22month%22%3A%220%22%2C%22day%22%3A%2222%22%2C%22year%22%3A%222022%22%7D&applicant_relationship_to_the_deceased=Spouse&applicant_marital_status=Married&applicant_citizen_status=Yes&applicant_care_for_child=Yes&applicant_paid_funeral_expenses=Yes&deceased_date_of_death=%7B%22month%22%3A%220%22%2C%22day%22%3A%2222%22%2C%22year%22%3A%222022%22%7D&deceased_death_location_is_US=Yes&deceased_paid_into_SS=Yes&deceased_public_safety_officer=Yes&deceased_miner=Yes&deceased_american_indian=Yes&deceased_died_of_COVID=Yes&deceased_served_in_active_military=No&shared=true`

const mockSelectedData = [
  {
    criteriaKey: 'applicant_date_of_birth',
    values: {
      default: '',
      value: {
        month: '0',
        day: '22',
        year: '2022',
      },
      selected: true,
    },
  },
  {
    criteriaKey: 'applicant_relationship_to_the_deceased',
    values: {
      option: 'Spouse',
      value: 'Spouse',
      selected: true,
    },
  },
  {
    criteriaKey: 'applicant_marital_status',
    values: {
      option: 'Married',
      value: 'Married',
      selected: true,
    },
  },
  {
    criteriaKey: 'applicant_citizen_status',
    values: {
      option: 'Yes',
      value: 'Yes',
      selected: true,
    },
  },
  {
    criteriaKey: 'applicant_care_for_child',
    values: {
      option: 'Yes',
      value: 'Yes',
      selected: true,
    },
  },
  {
    criteriaKey: 'applicant_paid_funeral_expenses',
    values: {
      option: 'Yes',
      value: 'Yes',
      selected: true,
    },
  },
  {
    criteriaKey: 'deceased_date_of_death',
    values: {
      default: '',
      value: {
        month: '0',
        day: '22',
        year: '2022',
      },
      selected: true,
    },
  },
  {
    criteriaKey: 'deceased_death_location_is_US',
    values: {
      option: 'Yes',
      value: 'Yes',
      selected: true,
    },
  },
  {
    criteriaKey: 'deceased_paid_into_SS',
    values: {
      option: 'Yes',
      value: 'Yes',
      selected: true,
    },
  },
  {
    criteriaKey: 'deceased_public_safety_officer',
    values: {
      option: 'Yes',
      value: 'Yes',
      selected: true,
    },
  },
  {
    criteriaKey: 'deceased_miner',
    values: {
      option: 'Yes',
      value: 'Yes',
      selected: true,
    },
  },
  {
    criteriaKey: 'deceased_american_indian',
    values: {
      option: 'Yes',
      value: 'Yes',
      selected: true,
    },
  },
  {
    criteriaKey: 'deceased_died_of_COVID',
    values: {
      option: 'Yes',
      value: 'Yes',
      selected: true,
    },
  },
  {
    criteriaKey: 'deceased_served_in_active_military',
    values: {
      option: 'No',
      value: 'No',
      selected: true,
    },
  },
]

// Mock window.location.href
Object.defineProperty(window, 'location', {
  value: {
    href: mockURI,
  },
  writable: true,
})

describe('test buildURIParameter utility', () => {
  it('returns a URI string', () => {
    expect(buildURIParameter(window.location.href, mockSelectedData)).toBe(
      expectedURI
    )
  })
})
