const IndexHTML = () => {
  return (
    <div data-testid="time-indicator">
      <select data-testid="input-month" id="date_of_birth_month" />
      <input
        data-testid="input-day"
        id="date_of_birth_day"
        inputMode="numeric"
      />
      <input
        data-testid="input-year"
        id="date_of_birth_year"
        inputMode="numeric"
      />
    </div>
  )
}

export default IndexHTML
