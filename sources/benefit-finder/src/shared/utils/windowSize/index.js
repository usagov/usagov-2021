const windowSize = w => {
  const desktopWidth = 1049
  const windowWidth = w.innerWidth

  const isDesktop = !(windowWidth < desktopWidth)

  return { width: windowWidth, desktop: isDesktop }
}

export default windowSize
