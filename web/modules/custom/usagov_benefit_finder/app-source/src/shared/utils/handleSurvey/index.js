const handleSurvey = ({ hide }) => {
  // get element by class
  const surveyElement = document.querySelector('.bf-qual-survey')
  // remove hidden attribute
  if (surveyElement && surveyElement.hidden === !hide) {
    surveyElement.hidden = hide
  }
}

export default handleSurvey
