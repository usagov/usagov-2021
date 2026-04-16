const a11yTitles = (location, locale) => {
  // create a hidden liveRegion to announce titles to screen-readers
  const liveRegion = document.getElementById('a11y-titles')

  // get the last part of the location Path
  // remove the hyphen
  const pathValue = location.split('/').pop()
  const title = pathValue.replace('-', ' ')

  // create language specific titles
  const enTitle = `Benefit Finder: ${title.toLowerCase()} | USAGov`
  const esTitle = `Buscador de beneficios: ${title.toLowerCase()} | USAGov`
  const defaultTitle = `${title} | USAGov`

  // assign title based on locale
  const assertiveTitle = !locale
    ? defaultTitle
    : locale === 'es'
      ? esTitle
      : enTitle

  // update the live region
  // delay the update to a11y-dom sees the change
  setTimeout(function () {
    if (liveRegion) {
      liveRegion.textContent = assertiveTitle
    }
  }, 200)

  // update the page title
  document.title = assertiveTitle

  return
}

export default a11yTitles
