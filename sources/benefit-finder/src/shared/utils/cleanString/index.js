const cleanString = string => {
  const cleanStr = string.toLowerCase().replace(/[^a-zA-Z0-9\s]/g, '')
  return cleanStr.replace(/ /g, '-')
}

export default cleanString
